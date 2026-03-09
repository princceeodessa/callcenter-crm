<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Services\Integrations\TelegramApiClient;
use App\Services\Integrations\VkApiClient;
use App\Services\Integrations\AvitoApiClient;
use App\Services\Integrations\AvitoOAuthService;

class IntegrationController extends Controller
{
    private const PROVIDERS = [
        'megafon_vats' => 'МегаФон Виртуальная АТС',
        'vk' => 'ВКонтакте',
        'avito' => 'Avito',
        'telegram' => 'Telegram',
    ];

    public function index()
    {
        $accountId = Auth::user()->account_id;

        $connections = IntegrationConnection::query()
            ->where('account_id', $accountId)
            ->get()
            ->keyBy('provider');

        // Ensure all providers exist in UI
        $providers = collect(self::PROVIDERS)->map(function ($title, $provider) use ($connections, $accountId) {
            $conn = $connections->get($provider);
            if (!$conn) {
                $conn = new IntegrationConnection([
                    'account_id' => $accountId,
                    'provider' => $provider,
                    'status' => 'disabled',
                    'settings' => [],
                ]);
            }

            $lastEvent = IntegrationEvent::query()
                ->where('account_id', $accountId)
                ->where('provider', $provider)
                ->orderByDesc('received_at')
                ->first();

            return [
                'provider' => $provider,
                'title' => $title,
                'connection' => $conn,
                'last_event_at' => $lastEvent?->received_at,
            ];
        })->values();

        return view('settings.integrations.index', compact('providers'));
    }

    public function show(string $provider)
    {
        $this->assertProvider($provider);
        $accountId = Auth::user()->account_id;

        $connection = IntegrationConnection::query()
            ->where('account_id', $accountId)
            ->where('provider', $provider)
            ->first();

        $events = IntegrationEvent::query()
            ->where('account_id', $accountId)
            ->where('provider', $provider)
            ->orderByDesc('received_at')
            ->limit(100)
            ->get();

        return view('settings.integrations.show', [
            'provider' => $provider,
            'providerTitle' => self::PROVIDERS[$provider],
            'connection' => $connection,
            'events' => $events,
        ]);
    }

    public function connect(Request $request, string $provider)
    {
        $this->assertProvider($provider);
        $accountId = Auth::user()->account_id;

        $data = match ($provider) {
            'megafon_vats' => $request->validate([
                'ats_api_base_url' => ['required', 'string', 'max:255'],
                'ats_api_key' => ['required', 'string', 'max:255'],
                'crm_webhook_token' => ['nullable', 'string', 'max:255'],
            ]),
            'telegram' => $request->validate([
                'bot_token' => ['required', 'string', 'max:255'],
            ]),
            'vk' => $request->validate([
                'group_id' => ['required', 'string', 'max:50'],
                'access_token' => ['required', 'string', 'max:255'],
            ]),
            'avito' => $request->validate([
                'client_id' => ['nullable', 'string', 'max:255'],
                'client_secret' => ['nullable', 'string', 'max:255'],
                'access_token' => ['nullable', 'string', 'max:255'],
                'refresh_token' => ['nullable', 'string', 'max:255'],
                'user_id' => ['nullable', 'string', 'max:50'],
            ]),
            default => [],
        };

        if ($provider === 'avito') {
            $hasToken = !empty(trim((string)($data['access_token'] ?? '')));
            $hasClient = !empty(trim((string)($data['client_id'] ?? ''))) && !empty(trim((string)($data['client_secret'] ?? '')));
            if (!$hasToken && !$hasClient) {
                return back()->withErrors(['access_token' => 'Для Avito укажи либо access_token, либо client_id + client_secret.']);
            }
        }

        $connection = IntegrationConnection::query()
            ->firstOrNew(['account_id' => $accountId, 'provider' => $provider]);

        $settings = $connection->settings ?? [];

        if ($provider === 'megafon_vats') {
            $settings['ats_api_base_url'] = trim($data['ats_api_base_url']);
            $settings['ats_api_key'] = trim($data['ats_api_key']);
            $settings['crm_webhook_token'] = trim($data['crm_webhook_token'] ?? '') ?: ($settings['crm_webhook_token'] ?? Str::random(40));
        } elseif ($provider === 'telegram') {
            $settings['bot_token'] = trim($data['bot_token']);
            $settings['crm_webhook_token'] = $settings['crm_webhook_token'] ?? Str::random(40);
            $settings['webhook_secret'] = $settings['webhook_secret'] ?? TelegramApiClient::makeSecretToken();

            // Best-effort: auto set Telegram webhook (requires https APP_URL)
            try {
                $webhookUrl = url('/webhooks/telegram?token='.$settings['crm_webhook_token']);
                $tg = new TelegramApiClient($settings['bot_token']);
                $resp = $tg->setWebhook($webhookUrl, $settings['webhook_secret']);
                if (!($resp['ok'] ?? false)) {
                    $settings['last_setup_error'] = $resp['description'] ?? 'Telegram setWebhook failed';
                } else {
                    unset($settings['last_setup_error']);
                }
            } catch (\Throwable $e) {
                $settings['last_setup_error'] = 'Telegram setWebhook exception: '.$e->getMessage();
            }
        } elseif (in_array($provider, ['vk', 'avito'], true)) {
            foreach ($data as $k => $v) {
                if ($v === null) {
                    continue;
                }
                $settings[$k] = is_string($v) ? trim($v) : $v;
            }

            // Generate webhook token/secret for providers that may use webhooks
            $settings['crm_webhook_token'] = $settings['crm_webhook_token'] ?? Str::random(40);
            if ($provider === 'vk') {
                $settings['webhook_secret'] = $settings['webhook_secret'] ?? VkApiClient::makeSecret();

                // Best-effort: fetch confirmation code (useful for VK callback setup)
                try {
                    $vk = new VkApiClient($settings['access_token']);
                    $resp = $vk->getCallbackConfirmationCode($settings['group_id']);
                    $code = $resp['response'] ?? null;
                    if (is_string($code) && $code !== '') {
                        $settings['confirmation_code'] = $code;
                        unset($settings['last_setup_error']);
                    } elseif (isset($resp['error'])) {
                        $settings['last_setup_error'] = 'VK confirmation_code error: '.json_encode($resp['error'], JSON_UNESCAPED_UNICODE);
                    }
                } catch (\Throwable $e) {
                    $settings['last_setup_error'] = 'VK confirmation_code exception: '.$e->getMessage();
                }
            } elseif ($provider === 'avito') {
                unset($settings['last_setup_error']);
                $clientId = trim((string) ($settings['client_id'] ?? ''));
                $clientSecret = trim((string) ($settings['client_secret'] ?? ''));
                $accessToken = trim((string) ($settings['access_token'] ?? ''));
                if ($accessToken === '' && $clientId !== '' && $clientSecret !== '') {
                    try {
                        $oauth = app(AvitoOAuthService::class);
                        $resp = $oauth->clientCredentials($clientId, $clientSecret);
                        $accessToken = trim((string) ($resp['access_token'] ?? ''));
                        if ($accessToken !== '') {
                            $settings['access_token'] = $accessToken;
                            if (!empty($resp['expires_in'])) {
                                $settings['token_expires_at'] = now()->addSeconds((int) $resp['expires_in'])->toDateTimeString();
                            }
                            if (!empty($resp['refresh_token'])) {
                                $settings['refresh_token'] = (string) $resp['refresh_token'];
                            }
                        } else {
                            $settings['last_setup_error'] = 'Avito token error: '.json_encode($resp, JSON_UNESCAPED_UNICODE);
                        }
                    } catch (\Throwable $e) {
                        $settings['last_setup_error'] = 'Avito token exception: '.$e->getMessage();
                    }
                }

                if (!empty($settings['access_token']) && empty($settings['user_id'])) {
                    try {
                        $av = new AvitoApiClient((string) $settings['access_token']);
                        $self = $av->getSelfAccount();
                        $uid = $self['id'] ?? data_get($self, 'account.id') ?? data_get($self, 'result.id') ?? data_get($self, 'data.id');
                        if (is_scalar($uid) && (string) $uid !== '') {
                            $settings['user_id'] = (string) $uid;
                        }
                    } catch (\Throwable $e) {
                        $settings['last_setup_error'] = $settings['last_setup_error'] ?? ('Avito self account exception: '.$e->getMessage());
                    }
                }

                if (!empty($settings['access_token']) && empty($settings['user_id'])) {
                    $settings['last_setup_error'] = $settings['last_setup_error'] ?? 'Токен Avito получен, но user_id (account id) Авито не вернул. Укажи user_id вручную — в рабочем боте он называется AVITO_USER_ID.';
                }
            }
        }

        $connection->fill([
            'account_id' => $accountId,
            'provider' => $provider,
            'status' => $provider === 'avito'
                ? $this->resolveAvitoConnectionStatus($settings)
                : (empty($settings['access_token']) ? 'disabled' : 'active'),
            'settings' => $settings,
            'last_error' => $settings['last_setup_error'] ?? null,
        ]);

        $connection->save();

        return redirect()->route('settings.integrations.index')->with('status', 'Интеграция сохранена.');
    }

    /**
     * Start Avito OAuth flow (redirect user to Avito).
     * Callback will come back to /webhooks/avito (GET) where we finalize token exchange.
     */
    public function avitoOauthStart(Request $request)
    {
        $accountId = Auth::user()->account_id;

        $connection = IntegrationConnection::query()
            ->firstOrNew(['account_id' => $accountId, 'provider' => 'avito']);

        $settings = $connection->settings ?? [];
        $clientId = trim((string)($settings['client_id'] ?? ''));
        $clientSecret = trim((string)($settings['client_secret'] ?? ''));

        if ($clientId === '' || $clientSecret === '') {
            return redirect()->route('settings.integrations.show', 'avito')
                ->withErrors(['client_id' => 'Сначала сохрани client_id и client_secret для Avito']);
        }

        $settings['crm_webhook_token'] = $settings['crm_webhook_token'] ?? Str::random(40);
        $settings['oauth_state'] = Str::random(48);
        $connection->fill([
            'account_id' => $accountId,
            'provider' => 'avito',
            'status' => $connection->status ?: 'disabled',
            'settings' => $settings,
        ])->save();

        $redirectUri = $this->avitoRedirectUri($request);
        $settings['oauth_redirect_uri'] = $redirectUri;
        $connection->update(['settings' => $settings]);

        $authorizeBase = trim((string) env('AVITO_OAUTH_AUTHORIZE_URL', 'https://www.avito.ru/oauth'));
        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $settings['oauth_state'],
        ];

        $scope = trim((string) env('AVITO_OAUTH_SCOPE', 'messenger:read messenger:write'));
        if ($scope !== '') {
            $params['scope'] = $scope;
        }

        return redirect()->away(rtrim($authorizeBase, '?').'?'.http_build_query($params));
    }

    public function testSend(Request $request, string $provider)
    {
        $this->assertProvider($provider);
        $accountId = Auth::user()->account_id;

        $connection = IntegrationConnection::query()
            ->where('account_id', $accountId)
            ->where('provider', $provider)
            ->where('status', 'active')
            ->first();

        if (!$connection) {
            return back()->with('status', 'Интеграция не активна.');
        }

        $settings = $connection->settings ?? [];

        $data = match ($provider) {
            'telegram' => $request->validate([
                'chat_id' => ['required', 'string', 'max:64'],
                'text' => ['required', 'string', 'max:4000'],
            ]),
            'vk' => $request->validate([
                'peer_id' => ['required', 'string', 'max:64'],
                'text' => ['required', 'string', 'max:4000'],
            ]),
            'avito' => $request->validate([
                'chat_id' => ['required', 'string', 'max:128'],
                'text' => ['required', 'string', 'max:4000'],
            ]),
            default => [],
        };

        try {
            $result = null;

            if ($provider === 'telegram') {
                $tg = new TelegramApiClient($settings['bot_token'] ?? '');
                $result = $tg->sendMessage($data['chat_id'], $data['text']);
            } elseif ($provider === 'vk') {
                $vk = new VkApiClient($settings['access_token'] ?? '');
                $result = $vk->sendMessage($data['peer_id'], $data['text']);
            } elseif ($provider === 'avito') {
                $userId = $settings['user_id'] ?? null;
                if (!$userId) {
                    return back()->with('status', 'Для Avito нужен user_id (account id). Укажи его в настройках интеграции.');
                }
                $token = $this->ensureAvitoAccessToken($connection);
                if ($token === '') {
                    return back()->with('status', 'Не удалось получить access_token для Avito. Проверь client_id / client_secret и last_error.');
                }
                $result = (new AvitoApiClient($token))->sendText($userId, $data['chat_id'], $data['text']);
            }

            IntegrationEvent::create([
                'account_id' => $accountId,
                'provider' => $provider,
                'direction' => 'out',
                'event_type' => 'test_send',
                'external_id' => null,
                'payload' => ['request' => $data, 'response' => $result],
                'received_at' => now(),
            ]);

            return back()->with('status', 'Тестовое сообщение отправлено (см. лог событий).');
        } catch (\Throwable $e) {
            $connection->update(['status' => 'error', 'last_error' => $e->getMessage()]);

            IntegrationEvent::create([
                'account_id' => $accountId,
                'provider' => $provider,
                'direction' => 'out',
                'event_type' => 'test_send_error',
                'external_id' => null,
                'payload' => ['request' => $data, 'error' => $e->getMessage()],
                'received_at' => now(),
            ]);

            return back()->with('status', 'Ошибка отправки: '.$e->getMessage());
        }
    }

    public function disconnect(string $provider)
    {
        $this->assertProvider($provider);
        $accountId = Auth::user()->account_id;

        IntegrationConnection::query()
            ->where('account_id', $accountId)
            ->where('provider', $provider)
            ->update(['status' => 'disabled']);

        return redirect()->route('settings.integrations.index')->with('status', 'Интеграция отключена.');
    }

    private function assertProvider(string $provider): void
    {
        if (!array_key_exists($provider, self::PROVIDERS)) {
            abort(404);
        }
    }


    private function resolveAvitoConnectionStatus(array $settings): string
    {
        $hasToken = trim((string) ($settings['access_token'] ?? '')) !== '';
        $hasClient = trim((string) ($settings['client_id'] ?? '')) !== ''
            && trim((string) ($settings['client_secret'] ?? '')) !== '';
        $hasUserId = trim((string) ($settings['user_id'] ?? '')) !== '';

        if (!$hasToken && !$hasClient) {
            return 'disabled';
        }

        if (!$hasUserId) {
            return 'error';
        }

        return 'active';
    }

    private function ensureAvitoAccessToken(IntegrationConnection $connection): string
    {
        $settings = is_array($connection->settings) ? $connection->settings : [];
        $token = trim((string) ($settings['access_token'] ?? ''));
        if ($token !== '') {
            return $token;
        }

        $clientId = trim((string) ($settings['client_id'] ?? ''));
        $clientSecret = trim((string) ($settings['client_secret'] ?? ''));
        if ($clientId === '' || $clientSecret === '') {
            return '';
        }

        try {
            $oauth = app(AvitoOAuthService::class);
            $resp = $oauth->clientCredentials($clientId, $clientSecret);
            $token = trim((string) ($resp['access_token'] ?? ''));
            if ($token === '') {
                $settings['last_setup_error'] = 'Avito token error: '.json_encode($resp, JSON_UNESCAPED_UNICODE);
                $connection->update([
                    'settings' => $settings,
                    'status' => $this->resolveAvitoConnectionStatus($settings),
                    'last_error' => $settings['last_setup_error'],
                ]);
                return '';
            }

            $settings['access_token'] = $token;
            if (!empty($resp['expires_in'])) {
                $settings['token_expires_at'] = now()->addSeconds((int) $resp['expires_in'])->toDateTimeString();
            }
            if (!empty($resp['refresh_token'])) {
                $settings['refresh_token'] = (string) $resp['refresh_token'];
            }
            unset($settings['last_setup_error']);

            $connection->update([
                'settings' => $settings,
                'status' => $this->resolveAvitoConnectionStatus($settings),
                'last_error' => null,
            ]);

            return $token;
        } catch (\Throwable $e) {
            $settings['last_setup_error'] = 'Avito token exception: '.$e->getMessage();
            $connection->update([
                'settings' => $settings,
                'status' => $this->resolveAvitoConnectionStatus($settings),
                'last_error' => $settings['last_setup_error'],
            ]);
            return '';
        }
    }

    private function avitoRedirectUri(Request $request): string
    {
        $override = trim((string) env('AVITO_OAUTH_REDIRECT_URI', ''));
        if ($override !== '') {
            return $override;
        }

        $appUrl = trim((string) config('app.url'));
        if ($appUrl !== '') {
            return rtrim($appUrl, '/').'/webhooks/avito';
        }

        return $request->getSchemeAndHttpHost().'/webhooks/avito';
    }
}

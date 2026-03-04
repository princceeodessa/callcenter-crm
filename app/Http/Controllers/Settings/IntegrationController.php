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
                'access_token' => ['required', 'string', 'max:255'],
                'user_id' => ['nullable', 'string', 'max:50'],
            ]),
            default => [],
        };

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
                $settings[$k] = trim($v);
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
            }
        }

        $connection->fill([
            'account_id' => $accountId,
            'provider' => $provider,
            'status' => 'active',
            'settings' => $settings,
            'last_error' => $settings['last_setup_error'] ?? null,
        ]);

        $connection->save();

        return redirect()->route('settings.integrations.index')->with('status', 'Интеграция сохранена.');
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
                $av = new AvitoApiClient($settings['access_token'] ?? '');
                $result = $av->sendText($userId, $data['chat_id'], $data['text']);
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
}

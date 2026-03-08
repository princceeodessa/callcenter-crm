<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Services\Chat\ChatIngestService;
use App\Services\Integrations\AvitoApiClient;
use App\Services\Integrations\AvitoOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AvitoWebhookController extends Controller
{
    /**
     * Incoming Avito webhooks (if enabled in your Avito product).
     *
     * Note: Many Avito setups use polling instead of webhooks.
     * This endpoint is still useful for products that can push events.
     */
    public function handle(Request $request)
    {
        // OAuth redirect (GET): /webhooks/avito?code=...&state=...
        if ($request->isMethod('get')) {
            if (is_string($request->query('error'))) {
                // Avito can return an error directly on redirect_uri.
                $err = (string)$request->query('error');
                $desc = (string)($request->query('error_description') ?? '');
                return response('avito_oauth_error: '.$err.($desc ? ' '.$desc : ''), 400);
            }
            if (is_string($request->query('code')) && is_string($request->query('state'))) {
                return $this->handleOauthCallback($request);
            }
        }

        $token = $request->query('token')
            ?? $request->header('X-Webhook-Token')
            ?? $request->input('token');

        $connection = null;
        if ($token) {
            $connection = IntegrationConnection::query()
                ->where('provider', 'avito')
                ->where('status', 'active')
                ->where('settings->crm_webhook_token', $token)
                ->first();
        }

        $accountId = $connection?->account_id;

        $payload = $request->all();
        $eventType = $request->input('type') ?? $request->input('event') ?? 'event';
        $externalId = $request->input('id') ?? $request->input('message_id') ?? $request->input('chat_id');

        IntegrationEvent::create([
            'account_id' => $accountId,
            'provider' => 'avito',
            'direction' => 'in',
            'event_type' => is_string($eventType) ? $eventType : null,
            'external_id' => is_scalar($externalId) ? (string)$externalId : null,
            'payload' => $payload,
            'received_at' => now(),
        ]);

        if ($connection) {
            $connection->update(['last_synced_at' => now(), 'last_error' => null]);

            try {
                app(ChatIngestService::class)->ingestFromAvito($connection, $payload);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return response()->json(['ok' => true]);
    }

    private function handleOauthCallback(Request $request)
    {
        $code = (string)$request->query('code');
        $state = (string)$request->query('state');

        $conn = IntegrationConnection::query()
            ->where('provider', 'avito')
            ->where('settings->oauth_state', $state)
            ->orderByDesc('id')
            ->first();

        if (!$conn) {
            return response('avito_oauth_invalid_state', 400);
        }

        $settings = $conn->settings ?? [];
        $clientId = trim((string)($settings['client_id'] ?? ''));
        $clientSecret = trim((string)($settings['client_secret'] ?? ''));
        if ($clientId === '' || $clientSecret === '') {
            $conn->update(['status' => 'error', 'last_error' => 'Avito OAuth: missing client_id/client_secret']);
            return response('avito_oauth_client_credentials_missing', 500);
        }

        try {
            $oauth = app(AvitoOAuthService::class);
            $redirectUri = (string)($settings['oauth_redirect_uri'] ?? $this->resolveAvitoRedirectUri($request));
            $tokenResp = $oauth->exchangeCode($clientId, $clientSecret, $code, $redirectUri);

            $accessToken = (string)($tokenResp['access_token'] ?? '');
            $refreshToken = (string)($tokenResp['refresh_token'] ?? '');
            $expiresIn = (int)($tokenResp['expires_in'] ?? 0);

            if ($accessToken === '') {
                $conn->update([
                    'status' => 'error',
                    'last_error' => 'Avito OAuth: token exchange failed: '.json_encode($tokenResp, JSON_UNESCAPED_UNICODE),
                ]);
                return response('avito_oauth_token_exchange_failed', 500);
            }

            $settings['access_token'] = $accessToken;
            if ($refreshToken !== '') {
                $settings['refresh_token'] = $refreshToken;
            }
            if ($expiresIn > 0) {
                $settings['token_expires_at'] = now()->addSeconds($expiresIn)->toDateTimeString();
            }
            unset($settings['oauth_state']);
            unset($settings['oauth_redirect_uri']);

            // Fetch user_id from /core/v1/accounts/self (needs user:read scope)
            $av = new AvitoApiClient($accessToken);
            $self = $av->getSelfAccount();
            $uid = $self['id']
                ?? data_get($self, 'account.id')
                ?? data_get($self, 'result.id')
                ?? data_get($self, 'data.id');
            if (is_scalar($uid) && (string)$uid !== '') {
                $settings['user_id'] = (string)$uid;
            }

            $settings['crm_webhook_token'] = $settings['crm_webhook_token'] ?? Str::random(40);

            $conn->update([
                'status' => 'active',
                'settings' => $settings,
                'last_error' => null,
                'last_synced_at' => now(),
            ]);

            IntegrationEvent::create([
                'account_id' => $conn->account_id,
                'provider' => 'avito',
                'direction' => 'in',
                'event_type' => 'oauth_connected',
                'external_id' => null,
                'payload' => ['self' => $self, 'token' => array_diff_key($tokenResp, ['access_token' => 1, 'refresh_token' => 1])],
                'received_at' => now(),
            ]);

            if (auth()->check()) {
                return redirect()->route('settings.integrations.show', 'avito')->with('status', 'Avito подключено');
            }
            return response('ok', 200);
        } catch (\Throwable $e) {
            $conn->update(['status' => 'error', 'last_error' => 'Avito OAuth exception: '.$e->getMessage()]);
            return response('avito_oauth_exception', 500);
        }
    }

    private function resolveAvitoRedirectUri(Request $request): string
    {
        $override = trim((string) env('AVITO_OAUTH_REDIRECT_URI', ''));
        if ($override !== '') {
            return $override;
        }

        $appUrl = trim((string) config('app.url'));
        if ($appUrl !== '') {
            return rtrim($appUrl, '/').'/webhooks/avito';
        }

        return $request->url();
    }
}

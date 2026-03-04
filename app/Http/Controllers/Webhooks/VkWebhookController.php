<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Services\Integrations\VkApiClient;
use Illuminate\Http\Request;

class VkWebhookController extends Controller
{
    /**
     * VK Callback API handler.
     *
     * VK expects plain text responses:
     * - for confirmation: confirmation_code
     * - for events: "ok"
     */
    public function handle(Request $request)
    {
        $payload = $request->all();
        $type = is_array($payload) ? ($payload['type'] ?? null) : null;
        $groupId = is_array($payload) ? ($payload['group_id'] ?? null) : null;
        $secret = is_array($payload) ? ($payload['secret'] ?? null) : null;

        // VK confirmation request usually contains only: {"type":"confirmation","group_id":...}
        // So we MUST be able to match a connection by group_id only.
        $connection = IntegrationConnection::query()
            ->where('provider', 'vk')
            // allow inbound even if status is "error" (e.g. a prior test_send failure)
            ->whereIn('status', ['active', 'error'])
            ->when($groupId, fn ($q) => $q->where('settings->group_id', (string) $groupId))
            ->when($secret, fn ($q) => $q->where('settings->webhook_secret', (string) $secret))
            ->first();

        // If secret/group_id not provided or not matched, fall back to query token.
        if (!$connection) {
            $token = $request->query('token') ?? $request->header('X-Webhook-Token');
            if ($token) {
                $connection = IntegrationConnection::query()
                    ->where('provider', 'vk')
                    ->whereIn('status', ['active', 'error'])
                    ->where('settings->crm_webhook_token', $token)
                    ->first();
            }
        }

        $accountId = $connection?->account_id;

        // confirmation handshake
        if ($type === 'confirmation') {
            if (!$connection) {
                // VK will show an error, but this is the most honest answer.
                return response('connection_not_found', 404)
                    ->header('Content-Type', 'text/plain; charset=utf-8');
            }

            $settings = $connection->settings ?? [];
            $code = $settings['confirmation_code'] ?? null;

            // Best-effort: if code is not saved, try to fetch it from VK API.
            // Note: VK may require a token with "manage" rights for this method.
            if (!$code) {
                try {
                    $vk = new VkApiClient($settings['access_token'] ?? '');
                    $resp = $vk->getCallbackConfirmationCode($settings['group_id'] ?? '');
                    $fetched = $resp['response'] ?? null;
                    if (is_string($fetched) && $fetched !== '') {
                        $code = $fetched;
                        $settings['confirmation_code'] = $code;
                        $connection->update([
                            'settings' => $settings,
                            'last_error' => null,
                        ]);
                    }
                } catch (\Throwable $e) {
                    // ignore and fall through
                }
            }

            if (!is_string($code) || $code === '') {
                // VK requires HTTP 200 with the confirmation code text.
                // If we can't provide it, confirmation will fail.
                return response('confirmation_code_not_set', 500)
                    ->header('Content-Type', 'text/plain; charset=utf-8');
            }

            return response($code, 200)
                ->header('Content-Type', 'text/plain; charset=utf-8');
        }

        // Best-effort external id
        $externalId = null;
        if (is_array($payload) && isset($payload['object']['message'])) {
            $externalId = $payload['object']['message']['conversation_message_id']
                ?? $payload['object']['message']['id']
                ?? null;
        }

        IntegrationEvent::create([
            'account_id' => $accountId,
            'provider' => 'vk',
            'direction' => 'in',
            'event_type' => is_string($type) ? $type : null,
            'external_id' => is_scalar($externalId) ? (string) $externalId : null,
            'payload' => $payload,
            'received_at' => now(),
        ]);

        if ($connection) {
            $connection->update(['last_synced_at' => now(), 'last_error' => null]);
        }

        return response('ok', 200)->header('Content-Type', 'text/plain; charset=utf-8');
    }
}

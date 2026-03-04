<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
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

        $connection = IntegrationConnection::query()
            ->where('provider', 'vk')
            ->where('status', 'active')
            ->when($groupId, fn($q) => $q->whereRaw("JSON_EXTRACT(settings, '$.group_id') = ?", [(string)$groupId]))
            ->when($secret, fn($q) => $q->whereRaw("JSON_EXTRACT(settings, '$.webhook_secret') = ?", [(string)$secret]))
            ->first();

        // If secret/group_id not provided or not matched, fall back to query token.
        if (!$connection) {
            $token = $request->query('token') ?? $request->header('X-Webhook-Token');
            if ($token) {
                $connection = IntegrationConnection::query()
                    ->where('provider', 'vk')
                    ->where('status', 'active')
                    ->whereRaw("JSON_EXTRACT(settings, '$.crm_webhook_token') = ?", [$token])
                    ->first();
            }
        }

        $accountId = $connection?->account_id;

        // confirmation handshake
        if ($type === 'confirmation') {
            $code = $connection?->settings['confirmation_code'] ?? null;
            if (!$code) {
                // Return 400: VK will show error and won't enable callback until confirmation_code is correct.
                return response('confirmation_code_not_set', 400)->header('Content-Type', 'text/plain; charset=utf-8');
            }
            return response((string)$code, 200)->header('Content-Type', 'text/plain; charset=utf-8');
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
            'external_id' => is_scalar($externalId) ? (string)$externalId : null,
            'payload' => $payload,
            'received_at' => now(),
        ]);

        if ($connection) {
            $connection->update(['last_synced_at' => now(), 'last_error' => null]);
        }

        return response('ok', 200)->header('Content-Type', 'text/plain; charset=utf-8');
    }
}

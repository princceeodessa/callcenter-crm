<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Services\Chat\ChatIngestService;
use App\Services\Integrations\VkLeadFormSync;
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
            ->whereIn('status', ['active', 'error'])
            ->when($groupId, fn ($query) => $query->where('settings->group_id', (string) $groupId))
            ->when($secret, fn ($query) => $query->where('settings->webhook_secret', (string) $secret))
            ->first();

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

        if ($type === 'confirmation') {
            $code = $connection?->settings['confirmation_code'] ?? null;
            if (!$code) {
                return response('confirmation_code_not_set', 500)->header('Content-Type', 'text/plain; charset=utf-8');
            }

            return response((string) $code, 200)->header('Content-Type', 'text/plain; charset=utf-8');
        }

        $event = IntegrationEvent::create([
            'account_id' => $accountId,
            'provider' => 'vk',
            'direction' => 'in',
            'event_type' => is_string($type) ? $type : null,
            'external_id' => $this->extractExternalId($payload, is_string($type) ? $type : null),
            'payload' => $payload,
            'received_at' => now(),
        ]);

        if ($connection) {
            $connection->update(['last_synced_at' => now(), 'last_error' => null]);

            try {
                if ($this->isLeadFormEvent(is_string($type) ? $type : null)) {
                    app(VkLeadFormSync::class)->handle($connection, $event);
                } elseif ($type === 'message_new') {
                    app(ChatIngestService::class)->ingestFromVk($connection, $payload);
                }
            } catch (\Throwable $e) {
                $connection->update(['last_error' => $e->getMessage()]);
            }
        }

        return response('ok', 200)->header('Content-Type', 'text/plain; charset=utf-8');
    }

    private function extractExternalId(array $payload, ?string $type): ?string
    {
        if ($this->isLeadFormEvent($type)) {
            $object = is_array($payload['object'] ?? null) ? $payload['object'] : [];
            foreach (['lead_id', 'id', 'request_id'] as $key) {
                $value = $object[$key] ?? null;
                if (is_scalar($value) && trim((string) $value) !== '') {
                    return trim((string) $value);
                }
            }

            return null;
        }

        if (isset($payload['object']['message']) && is_array($payload['object']['message'])) {
            $message = $payload['object']['message'];
            $value = $message['conversation_message_id'] ?? $message['id'] ?? null;
            return is_scalar($value) ? (string) $value : null;
        }

        return null;
    }

    private function isLeadFormEvent(?string $type): bool
    {
        if (!is_string($type) || trim($type) === '') {
            return false;
        }

        $type = trim($type);
        return $type === 'lead_forms_new' || str_starts_with($type, 'lead_forms');
    }
}
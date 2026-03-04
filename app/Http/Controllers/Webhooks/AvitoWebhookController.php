<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Services\Chat\ChatIngestService;
use Illuminate\Http\Request;

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
}

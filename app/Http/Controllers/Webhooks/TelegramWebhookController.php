<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    /**
     * Incoming Telegram updates (setWebhook).
     *
     * Verification:
     * - query token (?token=...) OR
     * - X-Telegram-Bot-Api-Secret-Token header (recommended)
     */
    public function handle(Request $request)
    {
        $token = $request->query('token')
            ?? $request->header('X-Webhook-Token');

        $secretHeader = $request->header('X-Telegram-Bot-Api-Secret-Token');

        $connection = null;
        if ($token) {
            $connection = IntegrationConnection::query()
                ->where('provider', 'telegram')
                ->whereIn('status', ['active','error'])
                ->where('settings->crm_webhook_token', $token)
                ->first();
        }

        // If token not provided (or not matched), try secret header match.
        if (!$connection && $secretHeader) {
            $connection = IntegrationConnection::query()
                ->where('provider', 'telegram')
                ->whereIn('status', ['active','error'])
                ->where('settings->webhook_secret', $secretHeader)
                ->first();
        }

        $accountId = $connection?->account_id;

        // Best-effort event type / external id
        $payload = $request->all();
        $eventType = 'update';
        $externalId = null;

        if (is_array($payload)) {
            $eventType = isset($payload['message']) ? 'message' : (isset($payload['callback_query']) ? 'callback_query' : 'update');
            $externalId = $payload['update_id'] ?? null;
        }

        IntegrationEvent::create([
            'account_id' => $accountId,
            'provider' => 'telegram',
            'direction' => 'in',
            'event_type' => $eventType,
            'external_id' => is_scalar($externalId) ? (string)$externalId : null,
            'payload' => $payload,
            'received_at' => now(),
        ]);

        if ($connection) {
            $connection->update(['last_synced_at' => now(), 'last_error' => null]);
        }

        return response()->json(['ok' => true]);
    }
}

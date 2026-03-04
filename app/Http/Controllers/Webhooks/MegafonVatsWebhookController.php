<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use Illuminate\Http\Request;
use App\Services\Integrations\MegafonVatsDealSync;

class MegafonVatsWebhookController extends Controller
{
    /**
     * Incoming callbacks from MegaFon Virtual PBX (VATS).
     *
     * We intentionally keep this handler tolerant: it stores the payload to integration_events.
     * When we have your exact VATS REST API/callback schema, we will parse call events and
     * create/update deals, contacts, tasks, and attach recordings.
     */
    public function handle(Request $request)
    {
        // Try to find matching account by token (if configured)
        $token = $request->header('X-Webhook-Token')
            ?? $request->query('token')
            ?? $request->input('token')
            ?? $request->input('crm_token');

        $connection = null;
        if ($token) {
            $connection = IntegrationConnection::query()
                ->where('provider', 'megafon_vats')
                ->where('status', 'active')
                ->where('settings->crm_webhook_token', $token)
                ->first();
        }

        $accountId = $connection?->account_id;

        // Best-effort event type
        $type = $request->input('type') ?? $request->input('event') ?? $request->header('X-Event-Type');
        $cmd = $request->input('cmd');
        $eventType = is_string($cmd) && $cmd !== ''
            ? (string)($cmd.':'.(is_string($type) ? $type : 'event'))
            : (is_string($type) ? $type : null);

        $externalId = $request->input('callid')
            ?? $request->input('call_id')
            ?? $request->input('callId')
            ?? $request->input('id')
            ?? $request->input('uuid');

        $event = IntegrationEvent::create([
            'account_id' => $accountId,
            'provider' => 'megafon_vats',
            'direction' => 'in',
            'event_type' => is_string($eventType) ? $eventType : null,
            'external_id' => is_string($externalId) ? $externalId : null,
            'payload' => $request->all(),
            'received_at' => now(),
        ]);

        // Turn call events into deals + activities (idempotent by callid)
        if ($connection && $accountId) {
            MegafonVatsDealSync::handle($connection, $event);
        }

        if ($connection) {
            $connection->update(['last_synced_at' => now(), 'last_error' => null]);
        }

        // Return 200 to avoid repeated retries
        return response()->json(['ok' => true]);
    }
}

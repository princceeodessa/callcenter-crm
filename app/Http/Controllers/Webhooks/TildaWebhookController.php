<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Services\Integrations\TildaLeadSync;
use Illuminate\Http\Request;

class TildaWebhookController extends Controller
{
    public function handle(Request $request, TildaLeadSync $sync)
    {
        $payload = $request->all();
        $isTestPing = $this->isTestPing($payload);
        $connection = $this->resolveConnection($request);
        $accountId = $connection?->account_id;

        $event = IntegrationEvent::create([
            'account_id' => $accountId,
            'provider' => 'tilda',
            'direction' => 'in',
            'event_type' => $isTestPing ? 'test_ping' : 'form_submission',
            'external_id' => $this->extractExternalId($payload),
            'payload' => $payload,
            'received_at' => now(),
        ]);

        if (! $connection) {
            if ($isTestPing) {
                return response('ok', 200)->header('Content-Type', 'text/plain; charset=utf-8');
            }

            return response('forbidden', 403)->header('Content-Type', 'text/plain; charset=utf-8');
        }

        try {
            $sync->handle($connection, $event, $request);

            $connection->update([
                'status' => 'active',
                'last_synced_at' => now(),
                'last_error' => null,
            ]);
        } catch (\Throwable $e) {
            $connection->update([
                'status' => 'error',
                'last_error' => $e->getMessage(),
            ]);

            return response('processing_error', 500)->header('Content-Type', 'text/plain; charset=utf-8');
        }

        return response('ok', 200)->header('Content-Type', 'text/plain; charset=utf-8');
    }

    private function resolveConnection(Request $request): ?IntegrationConnection
    {
        $baseQuery = IntegrationConnection::query()
            ->where('provider', 'tilda')
            ->whereIn('status', ['active', 'error']);

        foreach ($this->collectTokenCandidates($request) as $token) {
            $matched = (clone $baseQuery)
                ->where('settings->crm_webhook_token', $token)
                ->first();

            if ($matched) {
                return $matched;
            }
        }

        $connections = (clone $baseQuery)->get();
        foreach ($connections as $connection) {
            $settings = is_array($connection->settings) ? $connection->settings : [];
            $fieldName = trim((string) ($settings['api_field_name'] ?? 'crm_token'));
            $expectedToken = trim((string) ($settings['crm_webhook_token'] ?? ''));
            if ($fieldName === '' || $expectedToken === '') {
                continue;
            }

            foreach ([$request->input($fieldName), $request->header($fieldName)] as $candidate) {
                $candidate = is_scalar($candidate) ? trim((string) $candidate) : '';
                if ($candidate !== '' && hash_equals($expectedToken, $candidate)) {
                    return $connection;
                }
            }
        }

        return null;
    }

    private function collectTokenCandidates(Request $request): array
    {
        $candidates = [
            $request->query('token'),
            $request->header('X-Webhook-Token'),
            $request->input('token'),
            $request->input('crm_token'),
        ];

        $tokens = [];
        foreach ($candidates as $candidate) {
            $candidate = is_scalar($candidate) ? trim((string) $candidate) : '';
            if ($candidate !== '') {
                $tokens[] = $candidate;
            }
        }

        return array_values(array_unique($tokens));
    }

    private function isTestPing(array $payload): bool
    {
        return isset($payload['test']) && (string) $payload['test'] === 'test';
    }

    private function extractExternalId(array $payload): ?string
    {
        foreach (['tranid', 'lead_id', 'id', 'formid'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_scalar($value)) {
                $value = trim((string) $value);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }
}
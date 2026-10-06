<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Services\Onec\RetailDayExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API для станции (компьютера с доступом к 1С «Обувь»): какие дни продаж белых пар выгрузить и что вышло.
 * Доступ — по токену подключения `onec_obuv` (заголовок X-Onec-Token).
 */
class OnecRetailController extends Controller
{
    public function pending(Request $request, RetailDayExport $export): JsonResponse
    {
        $connection = $this->connection($request);
        $connection->forceFill(['last_synced_at' => now()])->save();
        $start = $export->startDay($connection);

        return response()->json([
            'account_id' => $connection->account_id,
            'start_day' => $start,
            'days' => $export->pending($connection->account_id, $start),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function result(Request $request, string $day, RetailDayExport $export): JsonResponse
    {
        $connection = $this->connection($request);
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1, 404);

        $data = $request->validate([
            'status' => ['required', 'in:done,partial,error'],
            'hash' => ['required_unless:status,error', 'nullable', 'string', 'max:64'],
            'onec_uuid' => ['nullable', 'string', 'max:36'],
            'onec_number' => ['nullable', 'string', 'max:32'],
            'pairs' => ['nullable', 'integer', 'min:0'],
            'amount' => ['nullable', 'numeric'],
            'mapped' => ['nullable', 'array'],
            'mapped.*.deal_id' => ['required', 'integer'],
            'mapped.*.kind' => ['nullable', 'in:sale,return'],
            'mapped.*.card_code' => ['required', 'string', 'max:32'],
            'unmapped' => ['nullable', 'array'],
            'unmapped.*.deal_id' => ['required', 'integer'],
            'unmapped.*.reason' => ['nullable', 'string', 'max:500'],
            'error' => ['nullable', 'string', 'max:5000'],
        ]);

        $rec = $export->applyResult($connection->account_id, $day, $data);
        $connection->forceFill([
            'last_synced_at' => now(),
            'last_error' => $data['status'] === 'error' ? mb_substr($day.': '.($data['error'] ?? ''), 0, 1000) : null,
        ])->save();

        return response()->json(['ok' => true, 'status' => $rec->status]);
    }

    private function connection(Request $request): IntegrationConnection
    {
        $token = (string) $request->header('X-Onec-Token', '');
        abort_if(strlen($token) < 32, 401);
        $connections = IntegrationConnection::withoutGlobalScopes()->where('provider', RetailDayExport::PROVIDER)->get();
        foreach ($connections as $c) {
            $known = $c->settings['token'] ?? null;
            if (is_string($known) && $known !== '' && hash_equals($known, $token)) {
                return $c;
            }
        }
        abort(401);
    }
}

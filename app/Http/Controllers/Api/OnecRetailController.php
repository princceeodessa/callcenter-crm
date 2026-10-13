<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Services\Onec\SaleDocExport;
use App\Services\Onec\SaleImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API для станции (компьютера с доступом к 1С «Обувь»). До 13.10.2026: какие продажи белых пар занести в 1С и что вышло.
 * С 13.10.2026 белые пары продают в 1С чеками: станция спрашивает, с какой даты читать (importSince), и привозит
 * чеки, возвраты и отчёты без чеков (imports). Доступ — по токену подключения `onec_obuv` (заголовок X-Onec-Token).
 */
class OnecRetailController extends Controller
{
    public function pending(Request $request, SaleDocExport $export): JsonResponse
    {
        $connection = $this->connection($request);
        $connection->forceFill(['last_synced_at' => now()])->save();
        $start = $export->startDay($connection);

        return response()->json([
            'account_id' => $connection->account_id,
            'start_day' => $start,
            'docs' => $export->pending($connection->account_id, $start),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function results(Request $request, SaleDocExport $export): JsonResponse
    {
        $connection = $this->connection($request);
        $data = $request->validate([
            'results' => ['present', 'array'],
            'results.*.deal_id' => ['required', 'integer'],
            'results.*.kind' => ['required', 'in:sale,return'],
            'results.*.status' => ['required', 'in:done,waiting,error'],
            'results.*.hash' => ['nullable', 'string', 'max:64'],
            'results.*.onec_uuid' => ['nullable', 'string', 'max:36'],
            'results.*.onec_number' => ['nullable', 'string', 'max:32'],
            'results.*.onec_date' => ['nullable', 'string', 'max:32'],
            'results.*.card_code' => ['nullable', 'string', 'max:32'],
            'results.*.amount' => ['nullable', 'numeric'],
            'results.*.reason' => ['nullable', 'string', 'max:5000'],
            'results.*.error' => ['nullable', 'string', 'max:5000'],
        ]);

        $n = $export->applyResults($connection->account_id, $data['results']);
        $errors = collect($data['results'])->where('status', 'error');
        $connection->forceFill([
            'last_synced_at' => now(),
            'last_error' => $errors->isNotEmpty() ? mb_substr($errors->map(fn ($r) => '#'.$r['deal_id'].': '.($r['error'] ?? $r['reason'] ?? ''))->implode('; '), 0, 1000) : null,
        ])->save();

        return response()->json(['ok' => true, 'saved' => $n]);
    }

    public function importSince(Request $request, SaleImport $import): JsonResponse
    {
        $connection = $this->connection($request);
        $connection->forceFill(['last_synced_at' => now()])->save();

        return response()->json(['since' => $import->since($connection)]);
    }

    public function imports(Request $request, SaleImport $import): JsonResponse
    {
        $connection = $this->connection($request);
        $data = $request->validate([
            'docs' => ['present', 'array', 'max:500'],
            'docs.*.uuid' => ['required', 'string', 'max:36'],
            'docs.*.type' => ['required', 'in:check,return,report'],
            'docs.*.number' => ['nullable', 'string', 'max:32'],
            'docs.*.date' => ['required', 'date'],
            'docs.*.posted' => ['boolean'],
            'docs.*.deleted' => ['boolean'],
            'docs.*.base_uuid' => ['nullable', 'string', 'max:36'],
            'docs.*.payment' => ['nullable', 'in:'.implode(',', array_keys(\App\Models\Deal::PAYMENT_METHODS))],
            'docs.*.rows' => ['present', 'array'],
            'docs.*.rows.*.row' => ['required'],
            'docs.*.rows.*.code' => ['nullable', 'string', 'max:32'],
            'docs.*.rows.*.name' => ['required', 'string', 'max:500'],
            'docs.*.rows.*.qty' => ['required', 'numeric'],
            'docs.*.rows.*.price' => ['nullable', 'numeric'],
            'docs.*.rows.*.amount' => ['required', 'numeric'],
            'docs.*.rows.*.mark' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $results = $import->import((int) $connection->account_id, $data['docs']);
            $connection->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();
        } catch (\Throwable $e) {
            $connection->forceFill(['last_synced_at' => now(), 'last_error' => mb_substr('приём из 1С: '.$e->getMessage(), 0, 1000)])->save();
            throw $e;
        }

        return response()->json(['ok' => true, 'results' => $results]);
    }

    private function connection(Request $request): IntegrationConnection
    {
        $token = (string) $request->header('X-Onec-Token', '');
        abort_if(strlen($token) < 32, 401);
        $connections = IntegrationConnection::withoutGlobalScopes()->where('provider', SaleDocExport::PROVIDER)->get();
        foreach ($connections as $c) {
            $known = $c->settings['token'] ?? null;
            if (is_string($known) && $known !== '' && hash_equals($known, $token)) {
                return $c;
            }
        }
        abort(401);
    }
}

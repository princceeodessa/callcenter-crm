<?php

namespace App\Services\Onec;

use App\Models\Deal;
use App\Models\IntegrationConnection;
use App\Models\OnecRetailDay;
use App\Models\Purchase;
use App\Models\WarehouseProduct;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Продажи белых пар → 1С «Обувь»: один «Отчёт о розничных продажах» на день.
 *
 * CRM собирает строки дня — продажи белых пар и возвраты уже выгруженных продаж — и считает отпечаток
 * их состава. Станция (компьютер с доступом к 1С) забирает дни, которых в 1С ещё нет или чей состав
 * поменялся, сама подбирает карточки 1С (по артикулу, где есть остаток), создаёт или обновляет отчёт и
 * присылает результат. Выгружаются только законченные дни (до вчера включительно).
 */
class RetailDayExport
{
    public const PROVIDER = 'onec_obuv';

    /** Частично выгруженный или упавший день станция пробует снова не чаще, чем раз в столько часов. */
    public const RETRY_HOURS = 6;

    public function connection(int $accountId): ?IntegrationConnection
    {
        return IntegrationConnection::withoutGlobalScopes()
            ->where('account_id', $accountId)->where('provider', self::PROVIDER)->first();
    }

    /** С какого дня выгружать (продажи раньше этого дня в 1С не идут). */
    public function startDay(IntegrationConnection $connection): string
    {
        $day = (string) ($connection->settings['start_day'] ?? '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) ? $day : now()->toDateString();
    }

    /**
     * Строки отчётов по дням за период.
     *
     * Продажа ещё не выгружена — она в дне списания со склада; уже выгружена — остаётся в своём дне
     * (и после возврата тоже). Возврат идёт в день возврата и только по выгруженной продаже: продажа и
     * возврат до выгрузки взаимно гасятся и в 1С не попадают.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function rowsByDay(int $accountId, string $from, string $to): array
    {
        $fromTs = Carbon::parse($from)->startOfDay();
        $toTs = Carbon::parse($to)->endOfDay();

        $sales = Deal::withoutGlobalScopes()->where('account_id', $accountId)->where('stock_white', true)
            ->where(function ($q) use ($fromTs, $toTs, $from, $to) {
                $q->where(fn ($a) => $a->whereNull('onec_sale_day')->whereNotNull('stock_deducted_at')
                    ->whereBetween('stock_deducted_at', [$fromTs, $toTs]))
                    ->orWhere(fn ($b) => $b->whereBetween('onec_sale_day', [$from, $to])
                        ->where(fn ($c) => $c->whereNotNull('stock_deducted_at')->orWhereNotNull('returned_at')));
            })
            ->with('warehouseItem')->get();
        $returns = Deal::withoutGlobalScopes()->where('account_id', $accountId)->where('stock_white', true)
            ->whereNotNull('onec_sale_day')->whereNotNull('returned_at')->whereBetween('returned_at', [$fromTs, $toTs])
            ->with('warehouseItem')->get();

        $articles = $this->articles($accountId, $sales->merge($returns));
        $days = [];
        foreach ($sales as $deal) {
            $day = $deal->onec_sale_day ? $deal->onec_sale_day->toDateString() : $deal->stock_deducted_at->toDateString();
            $days[$day][] = $this->row($deal, 'sale', $articles);
        }
        foreach ($returns as $deal) {
            $days[$deal->returned_at->toDateString()][] = $this->row($deal, 'return', $articles);
        }
        ksort($days);
        foreach ($days as &$rows) {
            usort($rows, fn ($a, $b) => [$a['deal_id'], $a['kind']] <=> [$b['deal_id'], $b['kind']]);
        }

        return $days;
    }

    /** Отпечаток состава дня: поменялся — день выгружается заново. */
    public static function hash(array $rows): string
    {
        return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Дни, которые станции нужно выгрузить сейчас: новых в 1С нет, состав изменился, или прошлая
     * попытка была частичной/с ошибкой и прошло RETRY_HOURS.
     *
     * @return list<array{day:string, hash:string, onec_uuid:?string, rows:list<array>}>
     */
    public function pending(int $accountId, string $startDay): array
    {
        $yesterday = now()->subDay()->toDateString();
        if ($startDay > $yesterday) {
            return [];
        }
        $byDay = $this->rowsByDay($accountId, $startDay, $yesterday);
        $records = OnecRetailDay::withoutGlobalScopes()->where('account_id', $accountId)
            ->whereBetween('day', [$startDay, $yesterday])->get()
            ->keyBy(fn (OnecRetailDay $r) => $r->day->toDateString());

        $days = array_unique(array_merge(array_keys($byDay), $records->keys()->all()));
        sort($days);
        $out = [];
        foreach ($days as $day) {
            $rows = $byDay[$day] ?? [];
            $hash = self::hash($rows);
            $rec = $records[$day] ?? null;
            $due = match (true) {
                $rec === null => $rows !== [],
                $rec->rows_hash !== $hash => true,
                in_array($rec->status, ['partial', 'error'], true) => ! $rec->attempted_at || $rec->attempted_at->lt(now()->subHours(self::RETRY_HOURS)),
                default => false,
            };
            if ($due) {
                $out[] = ['day' => $day, 'hash' => $hash, 'onec_uuid' => $rec?->onec_uuid, 'rows' => $rows];
            }
        }

        return $out;
    }

    /**
     * Результат станции по дню.
     *
     * @param  array{status:string, hash?:string, onec_uuid?:?string, onec_number?:?string, pairs?:int, amount?:float,
     *               mapped?:list<array{deal_id:int, kind?:string, card_code:string}>, unmapped?:list<array>, error?:?string}  $r
     */
    public function applyResult(int $accountId, string $day, array $r): OnecRetailDay
    {
        $rec = OnecRetailDay::withoutGlobalScopes()->firstOrNew(['account_id' => $accountId, 'day' => $day]);
        $rec->attempted_at = now();

        if ($r['status'] === 'error') {
            // Состав не запоминаем — день попробуем снова.
            $rec->status = 'error';
            $rec->error = mb_substr((string) ($r['error'] ?? 'ошибка без текста'), 0, 2000);
            if (! $rec->exists) {
                $rec->rows_hash = null;
            }
            $rec->save();

            return $rec;
        }

        $rec->fill([
            'status' => $r['status'] === 'partial' ? 'partial' : 'done',
            'rows_hash' => (string) ($r['hash'] ?? ''),
            'onec_uuid' => $r['onec_uuid'] ?? $rec->onec_uuid,
            'onec_number' => $r['onec_number'] ?? $rec->onec_number,
            'pairs' => (int) ($r['pairs'] ?? 0),
            'amount' => (float) ($r['amount'] ?? 0),
            'unmapped' => ! empty($r['unmapped']) ? array_values($r['unmapped']) : null,
            'error' => null,
            'exported_at' => now(),
        ]);
        $rec->save();

        // Продажи, ушедшие в отчёт этого дня: запоминаем карточку 1С (возврат пойдёт на неё же) и день.
        $mapped = [];
        foreach ($r['mapped'] ?? [] as $m) {
            if (($m['kind'] ?? 'sale') !== 'sale') {
                continue;
            }
            Deal::withoutGlobalScopes()->where('account_id', $accountId)->whereKey((int) $m['deal_id'])
                ->update(['onec_card_code' => (string) $m['card_code'], 'onec_sale_day' => $day]);
            $mapped[] = (int) $m['deal_id'];
        }
        // Продажи, которые раньше были в этом дне, а теперь нет (передумали / отменили) — отвязать.
        Deal::withoutGlobalScopes()->where('account_id', $accountId)->where('onec_sale_day', $day)
            ->when($mapped !== [], fn ($q) => $q->whereNotIn('id', $mapped))
            ->update(['onec_sale_day' => null, 'onec_card_code' => null]);

        return $rec;
    }

    /** @return array<string, mixed> */
    private function row(Deal $deal, string $kind, array $articles): array
    {
        $qty = max(1, (int) $deal->sold_quantity);
        $amount = round((float) $deal->amount, 2);
        $item = $deal->warehouseItem;

        return [
            'deal_id' => $deal->id,
            'kind' => $kind,
            'article' => $articles[$deal->warehouse_item_id] ?? '',
            'brand' => (string) ($item?->brand ?? ''),
            'model' => (string) ($item?->model ?? ''),
            'size' => (string) ($item?->size ?? ''),
            'qty' => $qty,
            'price' => round($amount / $qty, 2),
            'amount' => $amount,
            'payment' => $deal->payment_method,
            'card_code' => $kind === 'return' ? $deal->onec_card_code : null,
        ];
    }

    /**
     * Артикул производителя по позициям склада: из белой закупки этой позиции, иначе из карточки товара.
     *
     * @param  Collection<int, Deal>  $deals
     * @return array<int, string>
     */
    private function articles(int $accountId, Collection $deals): array
    {
        $itemIds = $deals->pluck('warehouse_item_id')->filter()->unique()->values()->all();
        if ($itemIds === []) {
            return [];
        }
        $res = Purchase::withoutGlobalScopes()->where('account_id', $accountId)->whereIn('warehouse_item_id', $itemIds)
            ->where('is_white', true)->whereNotNull('article')->where('article', '!=', '')
            ->orderBy('id')->pluck('article', 'warehouse_item_id')->map(fn ($a) => trim((string) $a))->all();

        $missing = $deals->filter(fn (Deal $d) => $d->warehouse_item_id && empty($res[$d->warehouse_item_id]) && $d->warehouseItem);
        if ($missing->isNotEmpty()) {
            $products = WarehouseProduct::withoutGlobalScopes()->where('account_id', $accountId)->get()
                ->keyBy(fn (WarehouseProduct $p) => mb_strtolower(trim($p->brand.'|'.$p->model)));
            foreach ($missing as $deal) {
                $p = $products[mb_strtolower(trim($deal->warehouseItem->brand.'|'.$deal->warehouseItem->model))] ?? null;
                if ($p && $p->article) {
                    $res[$deal->warehouse_item_id] = trim((string) $p->article);
                }
            }
        }

        return $res;
    }
}

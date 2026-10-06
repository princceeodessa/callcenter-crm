<?php

namespace App\Services\Onec;

use App\Models\Deal;
use App\Models\IntegrationConnection;
use App\Models\OnecSaleDoc;
use App\Models\Purchase;
use App\Models\WarehouseProduct;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Продажи белых пар → 1С «Обувь»: на каждую продажу — свой «Отчёт о розничных продажах» (на одну пару),
 * на возврат — отдельный такой же документ на ту же карточку 1С.
 *
 * CRM отдаёт станции (компьютер с доступом к 1С) документы, которых в 1С ещё нет или чьё содержимое поменялось;
 * станция через обработку CrmKrossovki в 1С подбирает карточку (по артикулу, где есть остаток), создаёт или
 * обновляет документ, проводит и присылает результат. Продажа уходит не раньше, чем через COOLING_MINUTES:
 * продавец успевает поправить оплату или «белая / серая».
 */
class SaleDocExport
{
    public const PROVIDER = 'onec_obuv';

    public const COOLING_MINUTES = 10;

    /** Ждущий прихода в 1С или упавший документ станция пробует снова не чаще, чем раз в столько часов. */
    public const RETRY_HOURS = 3;

    public function connection(int $accountId): ?IntegrationConnection
    {
        return IntegrationConnection::withoutGlobalScopes()
            ->where('account_id', $accountId)->where('provider', self::PROVIDER)->first();
    }

    /** С какого дня продажи идут в 1С. */
    public function startDay(IntegrationConnection $connection): string
    {
        $day = (string) ($connection->settings['start_day'] ?? '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) ? $day : now()->toDateString();
    }

    /**
     * Все документы, которые должны быть в 1С (продажи белых пар с начала выгрузки и возвраты уже ушедших продаж),
     * с их содержимым и отпечатком. Ключ — «sale-ID» / «return-ID».
     *
     * Продажа уже ушла в 1С — остаётся с той же датой и после возврата. Не ушла и вернули — в 1С не идут ни продажа,
     * ни возврат. Сделку перестали считать продажей или белой после выгрузки — документа в списке нет, это видно
     * на странице «1С» (cancelledInCrm).
     *
     * @return array<string, array<string, mixed>>
     */
    public function expected(int $accountId, string $startDay): array
    {
        $since = Carbon::parse($startDay)->startOfDay();
        $cool = now()->subMinutes(self::COOLING_MINUTES);
        $docs = OnecSaleDoc::withoutGlobalScopes()->where('account_id', $accountId)->get()
            ->keyBy(fn (OnecSaleDoc $d) => $d->kind.'-'.$d->deal_id);
        $exportedSales = $docs->filter(fn (OnecSaleDoc $d) => $d->kind === 'sale' && $d->status === 'done')->pluck('deal_id')->all();

        $deals = Deal::withoutGlobalScopes()->where('account_id', $accountId)->where('stock_white', true)
            ->where(function ($q) use ($since, $cool, $exportedSales) {
                $q->where(fn ($a) => $a->whereNotNull('stock_deducted_at')->whereBetween('stock_deducted_at', [$since, $cool]))
                    ->orWhereIn('id', $exportedSales ?: [0]);
            })
            ->with('warehouseItem')->get();
        $articles = $this->articles($accountId, $deals);

        $out = [];
        foreach ($deals as $deal) {
            $saleDoc = $docs['sale-'.$deal->id] ?? null;
            $saleExported = $saleDoc && $saleDoc->status === 'done';
            if ($deal->stock_deducted_at || $saleExported) {
                // дата продажи: у выгруженной — та, с которой ушла в 1С (после возврата stock_deducted_at пуст)
                $date = $saleExported && $saleDoc->onec_date ? $saleDoc->onec_date : $deal->stock_deducted_at;
                if ($date) {
                    $out['sale-'.$deal->id] = $this->doc($deal, 'sale', $date, $articles, null, $saleDoc);
                }
            }
            if ($deal->returned_at && $saleExported && $deal->returned_at->gte($since)) {
                $out['return-'.$deal->id] = $this->doc($deal, 'return', $deal->returned_at, $articles, $saleDoc->card_code, $docs['return-'.$deal->id] ?? null);
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * Что станции нужно создать или обновить сейчас: документа в 1С нет, содержимое поменялось, или прошлая
     * попытка («ждёт прихода», ошибка) была больше RETRY_HOURS назад.
     *
     * @return list<array<string, mixed>>
     */
    public function pending(int $accountId, string $startDay): array
    {
        $docs = OnecSaleDoc::withoutGlobalScopes()->where('account_id', $accountId)->get()
            ->keyBy(fn (OnecSaleDoc $d) => $d->kind.'-'.$d->deal_id);
        $out = [];
        foreach ($this->expected($accountId, $startDay) as $key => $doc) {
            $rec = $docs[$key] ?? null;
            $due = match (true) {
                $rec === null => true,
                $rec->rows_hash !== $doc['hash'] => true,
                $rec->status === 'done' => false,
                default => ! $rec->attempted_at || $rec->attempted_at->lt(now()->subHours(self::RETRY_HOURS)),
            };
            if ($due) {
                $out[] = $doc;
            }
        }
        // По времени: в 1С остаток на дату продажи считается правильно, если документы идут по порядку.
        usort($out, fn ($a, $b) => [$a['date'], $a['key']] <=> [$b['date'], $b['key']]);

        return $out;
    }

    /**
     * Результаты станции.
     *
     * @param  list<array<string, mixed>>  $results
     * @return int сколько записей обновлено
     */
    public function applyResults(int $accountId, array $results): int
    {
        $n = 0;
        foreach ($results as $r) {
            $dealId = (int) ($r['deal_id'] ?? 0);
            $kind = ($r['kind'] ?? '') === 'return' ? 'return' : 'sale';
            if (! $dealId || ! Deal::withoutGlobalScopes()->where('account_id', $accountId)->whereKey($dealId)->exists()) {
                continue;
            }
            $status = in_array($r['status'] ?? '', ['done', 'waiting', 'error'], true) ? $r['status'] : 'error';
            $rec = OnecSaleDoc::withoutGlobalScopes()->firstOrNew(['account_id' => $accountId, 'deal_id' => $dealId, 'kind' => $kind]);
            $rec->fill([
                'status' => $status,
                'rows_hash' => isset($r['hash']) ? (string) $r['hash'] : $rec->rows_hash,
                'reason' => $status === 'done' ? null : mb_substr((string) ($r['reason'] ?? $r['error'] ?? ''), 0, 2000),
                'attempted_at' => now(),
            ]);
            if ($status === 'done') {
                $rec->fill([
                    'onec_uuid' => $r['onec_uuid'] ?? $rec->onec_uuid,
                    'onec_number' => $r['onec_number'] ?? $rec->onec_number,
                    'onec_date' => ! empty($r['onec_date']) ? Carbon::parse($r['onec_date']) : $rec->onec_date,
                    'card_code' => $r['card_code'] ?? $rec->card_code,
                    'amount' => (float) ($r['amount'] ?? 0),
                    'exported_at' => now(),
                ]);
            } elseif (! empty($r['onec_uuid'])) {
                // документ в 1С есть, но не проведён (например, после пересборки не нашлось карточки)
                $rec->onec_uuid = $r['onec_uuid'];
                $rec->onec_number = $r['onec_number'] ?? $rec->onec_number;
            }
            $rec->save();
            $n++;
        }

        return $n;
    }

    /**
     * Выгруженные продажи, которые в CRM больше не белые продажи (отменили, сделали серой) — документ в 1С
     * остался, его надо пометить на удаление в 1С вручную.
     *
     * @return Collection<int, OnecSaleDoc>
     */
    public function cancelledInCrm(int $accountId): Collection
    {
        return OnecSaleDoc::withoutGlobalScopes()->where('account_id', $accountId)->where('kind', 'sale')->where('status', 'done')
            ->with('deal.warehouseItem')->get()
            ->filter(fn (OnecSaleDoc $d) => ! $d->deal || $d->deal->stock_white !== true
                || (! $d->deal->stock_deducted_at && ! $d->deal->returned_at))
            ->values();
    }

    /** Отпечаток содержимого документа: поменялся — документ в 1С пересобирается. */
    public static function hash(array $row): string
    {
        return hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, mixed> */
    private function doc(Deal $deal, string $kind, Carbon $date, array $articles, ?string $cardCode, ?OnecSaleDoc $rec): array
    {
        $qty = max(1, (int) $deal->sold_quantity);
        $amount = round((float) $deal->amount, 2);
        $item = $deal->warehouseItem;
        $row = [
            'article' => $articles[$deal->warehouse_item_id] ?? '',
            'brand' => (string) ($item?->brand ?? ''),
            'model' => (string) ($item?->model ?? ''),
            'size' => (string) ($item?->size ?? ''),
            'qty' => $qty,
            'price' => round($amount / $qty, 2),
            'amount' => $amount,
            'payment' => $deal->payment_method,
            'card_code' => $cardCode,
        ];
        $content = ['kind' => $kind, 'date' => $date->format('Y-m-d\TH:i:s'), 'row' => $row];

        return [
            'key' => $kind.'-'.$deal->id,
            'deal_id' => $deal->id,
            'kind' => $kind,
            'date' => $content['date'],
            'hash' => self::hash($content),
            'onec_uuid' => $rec?->onec_uuid,
            'row' => $row,
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

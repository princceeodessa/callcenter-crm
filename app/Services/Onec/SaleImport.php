<?php

namespace App\Services\Onec;

use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\DealStageHistory;
use App\Models\IntegrationConnection;
use App\Models\OnecSaleImport;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Purchase;
use App\Models\StockMark;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Warehouse\WarehouseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Белые продажи из 1С «Обувь» в CRM (решение владельца 13.10.2026): белые пары продают в 1С чеками, CRM их не продаёт,
 * а подтягивает — в статистику и на склад, чтобы остатки не расходились.
 *
 * Станция читает в 1С чеки ККМ, чеки на возврат и отчёты о розничных продажах, в смене которых нет ни одного чека
 * (продажи, внесённые без чеков), и присылает строки. Каждая строка продажи — белая сделка с датой чека; пара на складе
 * ищется по коду маркировки, затем по артикулу и размеру, затем по артикулу в любом размере; не нашлась — продажа
 * встаёт в «Продажи без списания» на складе. Возврат возвращает пару исходной продажи на склад. Чек, отменённый в 1С
 * после приёма, только помечается — сделку руками.
 */
class SaleImport
{
    /** С этого дня белые продажи идут из 1С. */
    public const START_DAY = '2026-10-13';

    /** Сколько дней назад перечитывать: база 1С может откатиться из копии, а повтор строки ничего не задваивает. */
    public const REREAD_DAYS = 3;

    public function __construct(private readonly WarehouseService $warehouse)
    {
    }

    /** С какой даты станции читать документы 1С. */
    public function since(IntegrationConnection $connection): string
    {
        $start = Carbon::parse((string) ($connection->settings['import_start_day'] ?? self::START_DAY))->startOfDay();
        $last = OnecSaleImport::withoutGlobalScopes()->where('account_id', $connection->account_id)->max('onec_date');
        $since = $last ? Carbon::parse($last)->subDays(self::REREAD_DAYS)->startOfDay() : $start;

        return ($since->lt($start) ? $start : $since)->format('Y-m-d\TH:i:s');
    }

    /**
     * @param  list<array<string, mixed>>  $docs  {uuid, type: check|return|report, number, date, posted, deleted, base_uuid?, payment?,
     *                                            rows: [{row, code, name, qty, price?, amount, mark?}]}
     * @return list<array<string, mixed>>
     */
    public function import(int $accountId, array $docs): array
    {
        // продажи раньше возвратов: возврат может ссылаться на чек из той же пачки
        usort($docs, fn ($a, $b) => [($a['type'] ?? '') === 'return' ? 1 : 0, (string) ($a['date'] ?? '')]
            <=> [($b['type'] ?? '') === 'return' ? 1 : 0, (string) ($b['date'] ?? '')]);
        $out = [];
        foreach ($docs as $doc) {
            $out[] = DB::transaction(fn () => $this->one($accountId, $doc));
        }

        return $out;
    }

    private function one(int $accountId, array $doc): array
    {
        $uuid = (string) $doc['uuid'];
        $active = ! empty($doc['posted']) && empty($doc['deleted']);
        $date = Carbon::parse((string) $doc['date']);
        $res = ['uuid' => $uuid, 'number' => $doc['number'] ?? null, 'rows' => []];

        foreach ((array) ($doc['rows'] ?? []) as $row) {
            $key = (string) $row['row'];
            $rec = OnecSaleImport::withoutGlobalScopes()->where('account_id', $accountId)
                ->where('onec_uuid', $uuid)->where('row_key', $key)->lockForUpdate()->first();
            if ($rec) {
                if (! $active && $rec->status !== 'cancelled_in_1c') {
                    $rec->forceFill(['status' => 'cancelled_in_1c', 'note' => 'в 1С '.(! empty($doc['deleted']) ? 'документ удалён' : 'проведение отменено')
                        .' после приёма; было: '.(OnecSaleImport::STATUS_LABELS[$rec->status] ?? $rec->status)])->save();
                }
                $res['rows'][] = ['row' => $key, 'status' => $rec->status, 'deal_id' => $rec->deal_id];
                continue;
            }
            $qtyRaw = (float) ($row['qty'] ?? 0);
            $qty = (int) round(abs($qtyRaw));
            if (! $active || $qty < 1) {
                continue;
            }
            $rec = new OnecSaleImport([
                'account_id' => $accountId,
                'onec_uuid' => $uuid,
                'row_key' => $key,
                'doc_type' => (string) $doc['type'],
                'onec_number' => $doc['number'] ?? null,
                'onec_date' => $date,
                'card_code' => $row['code'] ?? null,
                'card_name' => mb_substr((string) ($row['name'] ?? ''), 0, 255),
                'marking_key' => self::markingKey($row['mark'] ?? null),
                'qty' => $qty,
                'amount' => round(abs((float) ($row['amount'] ?? 0)), 2),
                'payment' => in_array($doc['payment'] ?? null, array_keys(Deal::PAYMENT_METHODS), true) ? $doc['payment'] : null,
            ]);
            if ($doc['type'] === 'return' || $qtyRaw < 0) {
                $this->applyReturn($accountId, $rec, $doc);
            } else {
                $this->applySale($accountId, $rec);
            }
            $rec->save();
            $res['rows'][] = ['row' => $key, 'status' => $rec->status, 'deal_id' => $rec->deal_id];
        }

        return $res;
    }

    private function applySale(int $accountId, OnecSaleImport $rec): void
    {
        [$item, $how, $mark] = $this->findItem($accountId, $rec);
        $pipeline = Pipeline::withoutGlobalScopes()->where('account_id', $accountId)->orderByDesc('is_default')->first();
        $stage = $pipeline ? PipelineStage::withoutGlobalScopes()->where('account_id', $accountId)->where('pipeline_id', $pipeline->id)
            ->where('is_final', 1)->orderBy('sort')->first() : null;
        if (! $pipeline || ! $stage) {
            throw new \RuntimeException('У кроссовок не настроена воронка продаж');
        }
        $head = User::where('account_id', $accountId)->where('role', 'sneaker_head')->orderBy('id')->value('id');
        $name = $item?->display_name ?? $rec->card_name;

        $deal = Deal::withoutGlobalScopes()->create([
            'account_id' => $accountId,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $stage->id,
            'title' => 'Продажа (1С): '.$name,
            'title_is_custom' => 1,
            'responsible_user_id' => $head,
            'amount' => $rec->amount,
            'currency' => 'RUB',
            'product_category' => 'sneakers',
            'closed_at' => $rec->onec_date,
            'closed_result' => 'won',
            'closed_by_user_id' => $head,
            'warehouse_item_id' => $item?->id,
            'sold_quantity' => $rec->qty,
            'stock_white' => true,
            'payment_method' => $rec->payment,
        ]);
        DealStageHistory::create([
            'account_id' => $accountId, 'deal_id' => $deal->id, 'from_stage_id' => null, 'to_stage_id' => $stage->id,
            'changed_by_user_id' => $head, 'changed_at' => $rec->onec_date,
        ]);
        DealActivity::create([
            'account_id' => $accountId, 'deal_id' => $deal->id, 'author_user_id' => null, 'type' => 'system',
            'body' => 'Продажа из 1С: '.(OnecSaleImport::TYPE_LABELS[$rec->doc_type] ?? $rec->doc_type).' № '.$rec->onec_number
                .' от '.$rec->onec_date->format('d.m.Y H:i').' · '.$rec->card_name.' × '.$rec->qty.' на '.number_format((float) $rec->amount, 0, ',', ' ').' ₽'
                .($rec->payment ? ' · '.mb_strtolower(Deal::PAYMENT_METHODS[$rec->payment]) : '').' · '.$how,
        ]);
        if ($mark) {
            $mark->forceFill(['deal_id' => $deal->id])->save();
        }
        if ($item) {
            $this->warehouse->syncDealStock($deal);
        }
        // дата продажи — дата чека (статистика по дням), даже если станция привезла его позже
        $deal->refresh()->forceFill(['stock_deducted_at' => $rec->onec_date])->save();
        if ($mark) {
            $mark->refresh()->forceFill(['status' => 'sold', 'sold_at' => $rec->onec_date])->save();
        }

        $rec->deal_id = $deal->id;
        $rec->status = $item ? 'linked' : 'unlinked';
        $rec->note = mb_substr($how, 0, 255);
    }

    private function applyReturn(int $accountId, OnecSaleImport $rec, array $doc): void
    {
        $deal = null;
        $how = '';
        $base = (string) ($doc['base_uuid'] ?? '');
        if ($base !== '') {
            $orig = OnecSaleImport::withoutGlobalScopes()->where('account_id', $accountId)->where('onec_uuid', $base)
                ->whereIn('status', ['linked', 'unlinked'])->whereNotNull('deal_id')
                ->when($rec->card_code, fn ($q) => $q->orderByRaw('card_code = ? DESC', [$rec->card_code]))
                ->with('deal')->get()->first(fn (OnecSaleImport $o) => $o->deal && ! $o->deal->returned_at);
            if ($orig) {
                $deal = $orig->deal;
                $how = 'по исходному чеку № '.$orig->onec_number;
            }
        }
        if (! $deal && $rec->marking_key) {
            $mark = $this->markFor($accountId, $rec->marking_key);
            if ($mark && $mark->deal_id) {
                $d = Deal::withoutGlobalScopes()->find($mark->deal_id);
                if ($d && ! $d->returned_at) {
                    $deal = $d;
                    $how = 'по коду маркировки';
                }
            }
        }
        if (! $deal) {
            $rec->status = 'return_unmatched';
            $rec->note = 'не нашёл продажу, к которой этот возврат — разберите на складе вручную';

            return;
        }
        if ($deal->warehouse_item_id && (int) $deal->sold_quantity > 0) {
            $this->warehouse->returnDealStock($deal);
        } else {
            $deal->forceFill(['returned_at' => now(), 'stock_deducted_at' => null])->save();
        }
        DealActivity::create([
            'account_id' => $accountId, 'deal_id' => $deal->id, 'author_user_id' => null, 'type' => 'system',
            'body' => 'Возврат из 1С: '.(OnecSaleImport::TYPE_LABELS[$rec->doc_type] ?? $rec->doc_type).' № '.$rec->onec_number
                .' от '.$rec->onec_date->format('d.m.Y H:i').' · '.$how,
        ]);
        $rec->deal_id = $deal->id;
        $rec->status = 'returned';
        $rec->note = mb_substr($how, 0, 255);
    }

    /**
     * Пара на складе для строки чека: [WarehouseItem|null, как нашли, StockMark|null].
     *
     * @return array{0: ?WarehouseItem, 1: string, 2: ?StockMark}
     */
    private function findItem(int $accountId, OnecSaleImport $rec): array
    {
        if ($rec->marking_key) {
            $mark = $this->markFor($accountId, $rec->marking_key);
            if ($mark && $mark->warehouse_item_id && ! $mark->deal_id) {
                $item = WarehouseItem::withoutGlobalScopes()->find($mark->warehouse_item_id);
                if ($item) {
                    return [$item, 'пара по коду маркировки', $mark];
                }
            }
        }
        [$article, $size] = self::articleAndSize((string) $rec->card_name);
        if ($article === '') {
            return [null, 'в названии карточки 1С нет артикула — пару выберите на складе', null];
        }
        $itemIds = Purchase::withoutGlobalScopes()->where('account_id', $accountId)->where('is_white', true)
            ->whereRaw('UPPER(TRIM(article)) = ?', [mb_strtoupper($article)])->whereNotNull('warehouse_item_id')
            ->distinct()->pluck('warehouse_item_id')->all();
        $items = WarehouseItem::withoutGlobalScopes()->where('account_id', $accountId)->whereIn('id', $itemIds ?: [0])->orderBy('id')->get();
        if ($items->isEmpty()) {
            return [null, 'в CRM нет белой закупки артикула '.$article.' — пару выберите на складе', null];
        }
        $splits = $this->warehouse->whiteSplits($items);
        $hasWhite = fn (WarehouseItem $i) => ($splits[$i->id]['white'] ?? 0) >= $rec->qty;
        $sameSize = fn (WarehouseItem $i) => $size !== null && ($s = self::sizeNumber((string) $i->size)) !== null && abs($s - $size) < 0.02;

        if ($found = $items->first(fn ($i) => $sameSize($i) && $hasWhite($i))) {
            return [$found, 'пара по артикулу '.$article.' и размеру', null];
        }
        if ($found = $items->first($hasWhite)) {
            return [$found, 'пара по артикулу '.$article.' (размер '.$found->size.'; в 1С карточка «размер '.($size ?? '?').'»)', null];
        }

        return [null, 'белых пар артикула '.$article.' на складе CRM не осталось — пару выберите на складе', null];
    }

    private function markFor(int $accountId, string $key): ?StockMark
    {
        $gtin = substr($key, 2, 14);

        return StockMark::withoutGlobalScopes()->where('account_id', $accountId)->where('code', 'like', '%'.$gtin.'%')
            ->get()->first(fn (StockMark $m) => self::markingKey($m->code) === $key);
    }

    /** Неизменная часть кода маркировки «Честного знака»: 01 + GTIN + 21 + серийный номер (без криптохвоста). */
    public static function markingKey(?string $code): ?string
    {
        $s = (string) $code;
        if ($s === '') {
            return null;
        }
        $s = preg_replace('/^\]?[A-Za-z]\d/', '', $s) ?? $s;             // префикс сканера ]C1 / ]d2
        $s = ltrim($s, "\x1D\x1E\x04 ");
        if (! preg_match('/^01(\d{14})21([^\x1D]+)/', $s, $m)) {
            return null;
        }
        $serial = $m[2];
        // без разделителей хвост «91…»/«93…» прилипает к серийному номеру: у обуви серийный номер — 13 знаков
        if (! str_contains($s, "\x1D") && strlen($serial) > 13) {
            $serial = substr($serial, 0, 13);
        }

        return '01'.$m[1].'21'.$serial;
    }

    /** Артикул и размер из названия карточки 1С: «Кроссовки мужские Adidas Adipuff, арт. IF4229, размер 44, цвет черный». */
    public static function articleAndSize(string $name): array
    {
        $article = preg_match('/арт\.\s*([^,]+)/u', $name, $a) ? trim($a[1]) : '';
        if ($article === '' && preg_match('/(\S+)\s+[\d.,]+(?:\s+\d\/\d)?\s*р-р\s*$/u', trim($name), $b)) {
            $article = trim($b[1]);                                   // карточки вида «BRAND MODEL ARTICLE SIZE р-р»
        }
        $size = null;
        if (preg_match('/размер\s*([\d.,]+(?:\s+\d\/\d)?)/u', $name, $m) || preg_match('/([\d.,]+(?:\s+\d\/\d)?)\s*р-р/u', $name, $m)) {
            $size = self::sizeNumber($m[1]);
        }

        return [$article, $size];
    }

    /** Размер числом: «42,5» → 42.5, «42 2/3» → 42.67. */
    public static function sizeNumber(string $size): ?float
    {
        $s = trim(str_replace(',', '.', $size));
        if (preg_match('/^(\d+(?:\.\d+)?)\s+(\d)\/(\d)$/', $s, $m)) {
            return round((float) $m[1] + (int) $m[2] / max(1, (int) $m[3]), 2);
        }

        return is_numeric($s) ? round((float) $s, 2) : null;
    }
}

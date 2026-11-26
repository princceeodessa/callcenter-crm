<?php

namespace App\Support\Sales;

use App\Models\Deal;
use App\Models\GiftCertificate;
use App\Models\GiftCertificateSale;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Подарочные сертификаты кроссовок (10.11.2026, решения владельца): учёт по количеству без номеров; продажа сертификата —
 * не выручка (отдельная строка), выручка — когда сертификатом платят за пару; сертификат одноразовый: дороже — доплата
 * деньгами. Продают в CRM, в оплату принимают и в CRM, и в 1С (белые пары).
 *
 * С 26.11.2026 (просьба владельца): пара дешевле сертификата — остаток выдают сдачей (наличными, на карту или
 * переводом) или, если так решили, он сгорает; сертификат можно обналичить целиком (журнал, kind = cashout).
 * У сделки `pay_extra_method` — деньги сверх сертификата: при цене выше сертификата это доплата клиента, при цене
 * ниже — способ сдачи; пусто при цене ниже — остаток сгорел. Выручка и маржа — всегда от цены пары (amount), сдача и
 * обналичивание — деньги из кассы, но не выручка и не расход.
 */
class GiftCertificates
{
    /** Способы оплаты деньгами — сертификат за сертификат не продают, доплату им не вносят. @return array<string, string> */
    public static function moneyMethods(): array
    {
        return array_diff_key(Deal::PAYMENT_METHODS, ['certificate' => true]);
    }

    /** @return Collection<int, GiftCertificate> */
    public static function nominals(int $accountId): Collection
    {
        return GiftCertificate::withoutGlobalScopes()->where('account_id', $accountId)->orderBy('nominal')->get();
    }

    /**
     * Сумма сертификатов, которыми платит клиент: [номинал => штук] только из номиналов аккаунта.
     *
     * @param  array<int|string, mixed>  $raw
     */
    public static function value(int $accountId, array $raw): float
    {
        $known = self::nominals($accountId)->pluck('nominal')->all();
        $sum = 0;
        foreach ($raw as $nominal => $qty) {
            if (in_array((int) $nominal, $known, true) && (int) $qty > 0) {
                $sum += (int) $nominal * min((int) $qty, 20);
            }
        }

        return (float) $sum;
    }

    /** «Сертификат 10 000 + карта 3 000» — как оплатили продажу. */
    public static function paymentLabel(?string $method, mixed $certValue = null, ?string $extra = null, mixed $amount = null): ?string
    {
        if ($method === null || $method === '') {
            return null;
        }
        if ($method !== 'certificate') {
            return Deal::PAYMENT_METHODS[$method] ?? $method;
        }
        $cert = (float) ($certValue ?? 0);
        $label = 'Сертификат'.($cert > 0 ? ' '.self::rub($cert) : '');
        $rest = $cert > 0 ? (float) ($amount ?? 0) - $cert : 0;
        if ($rest > 0.009) {
            $label .= ' + '.($extra ? mb_strtolower(self::moneyMethods()[$extra] ?? $extra).' ' : 'доплата ').self::rub($rest);
        } elseif ($rest < -0.009) {
            $label .= $extra ? ' · сдача '.self::changeWord($extra).' '.self::rub(-$rest) : ' (сгорело '.self::rub(-$rest).')';
        }

        return $label;
    }

    public static function dealLabel(Deal $deal): ?string
    {
        return self::paymentLabel($deal->payment_method, $deal->cert_value, $deal->pay_extra_method, $deal->amount);
    }

    /**
     * Итоги за период: продано сертификатов (шт, ₽), обналичено, оплачено ими пар (номиналы, выручка и маржа этих пар,
     * сдача, сгорело), в обращении — за всё время (продано − потрачено на пары − обналичено).
     *
     * @return array{sold_qty: int, sold_amount: float, cashed_qty: int, cashed_amount: float, redeemed: float, redeemed_count: int,
     *     revenue: float, profit: float, no_cost: int, change: float, burned: float, outstanding: float, by_nominal: array<int, int>}
     */
    public static function summary(int $accountId, CarbonInterface $from, CarbonInterface $to): array
    {
        $journal = fn () => GiftCertificateSale::withoutGlobalScopes()->where('account_id', $accountId)->active();
        $sales = $journal()->sales()->whereBetween('sold_at', [$from, $to])->get();
        $cashouts = $journal()->cashouts()->whereBetween('sold_at', [$from, $to])->get();
        $paid = self::redemptions($accountId)->whereBetween('stock_deducted_at', [$from, $to])->with('warehouseItem:id,avg_cost')->get();
        $profits = $paid->map(fn (Deal $d) => $d->sale_profit);

        return [
            'sold_qty' => (int) $sales->sum('qty'),
            'sold_amount' => (float) $sales->sum('amount'),
            'cashed_qty' => (int) $cashouts->sum('qty'),
            'cashed_amount' => (float) $cashouts->sum('amount'),
            'redeemed' => (float) $paid->sum(fn (Deal $d) => self::used($d)),
            'redeemed_count' => $paid->count(),
            // выручка и маржа пар, оплаченных сертификатами — от цены пары, сдача и сгоревший остаток в них не входят
            'revenue' => (float) $paid->sum(fn (Deal $d) => (float) ($d->amount ?? 0)),
            'profit' => (float) $profits->filter(fn ($p) => $p !== null)->sum(),
            'no_cost' => $profits->filter(fn ($p) => $p === null)->count(),
            'change' => (float) $paid->sum(fn (Deal $d) => self::change($d)),
            'burned' => (float) $paid->sum(fn (Deal $d) => self::burned($d)),
            'outstanding' => self::outstanding($accountId),
            'by_nominal' => $sales->groupBy('nominal')->map(fn ($g) => (int) $g->sum('qty'))->sortKeys()->all(),
        ];
    }

    /** На руках у клиентов за всё время: продано − потрачено на пары − обналичено. */
    public static function outstanding(int $accountId): float
    {
        $journal = fn () => GiftCertificateSale::withoutGlobalScopes()->where('account_id', $accountId)->active();
        $sold = (float) $journal()->sales()->sum('amount');
        $cashed = (float) $journal()->cashouts()->sum('amount');
        $paid = self::redemptions($accountId)->get(['amount', 'cert_value'])->sum(fn (Deal $d) => self::used($d));

        return $sold - $paid - $cashed;
    }

    /** Сдача клиенту с сертификата: пара дешевле и указано, как её выдали. */
    public static function change(Deal $deal): float
    {
        $rest = self::overpaid($deal);

        return $rest > 0 && $deal->pay_extra_method ? $rest : 0.0;
    }

    /** Сгоревший остаток сертификата: пара дешевле, сдачу не выдали. */
    public static function burned(Deal $deal): float
    {
        $rest = self::overpaid($deal);

        return $rest > 0 && ! $deal->pay_extra_method ? $rest : 0.0;
    }

    /** «наличными», «на карту», «переводом» — как выдали сдачу или обналичили. */
    public static function changeWord(?string $method): string
    {
        return match ($method) {
            'cash' => 'наличными',
            'card' => 'на карту',
            'transfer' => 'переводом',
            default => mb_strtolower((string) (self::moneyMethods()[$method] ?? $method)),
        };
    }

    /** Насколько сертификаты больше цены пары (0 — не больше). */
    private static function overpaid(Deal $deal): float
    {
        if ($deal->payment_method !== 'certificate' || $deal->cert_value === null) {
            return 0.0;
        }
        $rest = (float) $deal->cert_value - (float) ($deal->amount ?? 0);

        return $rest > 0.009 ? round($rest, 2) : 0.0;
    }

    /** Продажи пар, оплаченные сертификатом (без возвратов). */
    private static function redemptions(int $accountId)
    {
        return Deal::withoutGlobalScopes()->where('account_id', $accountId)->where('payment_method', 'certificate')
            ->whereNotNull('stock_deducted_at')->whereNull('returned_at');
    }

    /** Сколько сертификатов ушло на продажу: номиналы, а если не указаны (выбрали в карточке) — сумма продажи. */
    private static function used(Deal $deal): float
    {
        return (float) ($deal->cert_value ?? $deal->amount ?? 0);
    }

    private static function rub(float $v): string
    {
        return number_format($v, 0, ',', ' ').' ₽';
    }
}

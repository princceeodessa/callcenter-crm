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
 * деньгами, дешевле — остаток сгорает, сдачи нет. Продают в CRM, в оплату принимают и в CRM, и в 1С (белые пары).
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
            $label .= ' (сгорело '.self::rub(-$rest).')';
        }

        return $label;
    }

    public static function dealLabel(Deal $deal): ?string
    {
        return self::paymentLabel($deal->payment_method, $deal->cert_value, $deal->pay_extra_method, $deal->amount);
    }

    /**
     * Итоги за период: продано сертификатов (шт, ₽), оплачено ими пар (номиналы, сгорело), в обращении — за всё время.
     *
     * @return array{sold_qty: int, sold_amount: float, redeemed: float, redeemed_count: int, burned: float, outstanding: float, by_nominal: array<int, int>}
     */
    public static function summary(int $accountId, CarbonInterface $from, CarbonInterface $to): array
    {
        $sales = GiftCertificateSale::withoutGlobalScopes()->where('account_id', $accountId)->active()->whereBetween('sold_at', [$from, $to])->get();
        $paid = self::redemptions($accountId)->whereBetween('stock_deducted_at', [$from, $to])->get(['amount', 'cert_value']);
        $allSold = (float) GiftCertificateSale::withoutGlobalScopes()->where('account_id', $accountId)->active()->sum('amount');
        $allPaid = self::redemptions($accountId)->get(['amount', 'cert_value'])->sum(fn (Deal $d) => self::used($d));

        return [
            'sold_qty' => (int) $sales->sum('qty'),
            'sold_amount' => (float) $sales->sum('amount'),
            'redeemed' => (float) $paid->sum(fn (Deal $d) => self::used($d)),
            'redeemed_count' => $paid->count(),
            'burned' => (float) $paid->sum(fn (Deal $d) => max(0, (float) $d->cert_value - (float) $d->amount)),
            'outstanding' => $allSold - $allPaid,
            'by_nominal' => $sales->groupBy('nominal')->map(fn ($g) => (int) $g->sum('qty'))->sortKeys()->all(),
        ];
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

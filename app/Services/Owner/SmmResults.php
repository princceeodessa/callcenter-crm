<?php

namespace App\Services\Owner;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * «SMM-контент» → «Результат для бизнеса»: что продвижение во ВКонтакте дало в CRM за период — без имён и телефонов.
 * Потолки: обратившиеся из ВК (сообщения, лид-формы, звонки на номер из ВК), нецелевые, записанные на замер, цена
 * целевого клиента и замера по расходу кабинета VK Рекламы. Кроссовки: продажи из ВК (источник «ВК» или сделка из
 * сообщений ВК) и цена продажи; сколько продаж без источника; обращения из сообщений ВК — если они подключены к CRM.
 */
class SmmResults
{
    /** Расход кабинета за период: сумма по дням (клипы, лид-формы и прочее) из SmmReport::cabinet(). */
    public static function spend(array $cabinet): float
    {
        return round((float) collect($cabinet['days'] ?? [])->sum(fn ($d) => (float) ($d['clip'] ?? 0) + (float) ($d['lead'] ?? 0) + (float) ($d['other'] ?? 0)), 2);
    }

    public static function forBusiness(string $business, Carbon $from, Carbon $toExclusive, float $spend): array
    {
        return $business === 'ceilings'
            ? self::ceilings($from, $toExclusive, $spend)
            : self::sneakers($from, $toExclusive, $spend);
    }

    private static function ceilings(Carbon $from, Carbon $to, float $spend): array
    {
        $r = (new CeilingsKpi((int) config('owner.ceilings_account_id')))->groupResults($from, $to, 'vk');
        $target = $r['leads'] - $r['non_target'];

        return [
            'business' => 'ceilings',
            'spend' => $spend,
            'leads' => $r['leads'],
            'booked' => $r['booked'],
            'non_target' => $r['non_target'],
            'target' => $target,
            'conversion' => $target > 0 ? round($r['booked'] / $target * 100) : null,      // доля целевых
            'per_lead' => $r['leads'] > 0 && $spend > 0 ? round($spend / $r['leads']) : null,
            'per_target' => $target > 0 && $spend > 0 ? round($spend / $target) : null,
            'per_booked' => $r['booked'] > 0 && $spend > 0 ? round($spend / $r['booked']) : null,
            'sources' => $r['sources'],
        ];
    }

    private static function sneakers(Carbon $from, Carbon $to, float $spend): array
    {
        $acc = (int) User::where('role', 'sneaker_head')->orderBy('id')->value('account_id');
        $kpi = new CeilingsKpi($acc);
        // обращения: сделки периода, у которых первый сигнал — сообщения или лид-форма ВК
        $created = DB::table('deals')->where('account_id', $acc)->where('created_at', '>=', $from)->where('created_at', '<', $to)->pluck('id')->all();
        $leads = count(array_filter($kpi->leadSourcesFor($created), fn ($s) => $s === 'vk'));
        // продажи: списаны со склада в периоде, источник «ВК» или сделка из сообщений ВК
        $sold = DB::table('deals')->where('account_id', $acc)->whereNotNull('stock_deducted_at')
            ->where('stock_deducted_at', '>=', $from)->where('stock_deducted_at', '<', $to)->get(['id', 'manual_source']);
        $fromChat = DB::table('conversations')->whereIn('deal_id', $sold->pluck('id')->all() ?: [0])->where('channel', 'vk')->pluck('deal_id')->flip();
        $sales = $sold->filter(fn ($d) => mb_strtolower(trim((string) $d->manual_source)) === 'вк' || isset($fromChat[$d->id]))->count();

        return [
            'business' => 'sneakers',
            'spend' => $spend,
            // сообщения сообщества приходят в CRM? Если нет — обращений из ВК не посчитать (не «0»)
            'vk_connected' => DB::table('conversations')->where('account_id', $acc)->where('channel', 'vk')->exists(),
            'leads' => $leads,
            'sales' => $sales,
            'sales_total' => $sold->count(),
            'no_source' => $sold->filter(fn ($d) => trim((string) $d->manual_source) === '' && ! isset($fromChat[$d->id]))->count(),
            'per_lead' => $leads > 0 && $spend > 0 ? round($spend / $leads) : null,
            'per_sale' => $sales > 0 && $spend > 0 ? round($spend / $sales) : null,
        ];
    }
}

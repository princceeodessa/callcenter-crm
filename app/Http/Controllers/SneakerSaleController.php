<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\DealStageHistory;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\WarehouseItem;
use App\Models\WarehouseProduct;
use App\Services\Warehouse\WarehouseService;
use App\Support\Ui\Sneakers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * «Быстрая продажа» — один экран для продавца:
 * нашёл товар → ткнул размер → нажал «Продать».
 * Сделка, списание со склада, уведомление руководителю и чек — автоматически.
 */
class SneakerSaleController extends Controller
{
    public function form(WarehouseService $warehouse)
    {
        $user = Auth::user();
        $accId = $user->account_id;

        $items = WarehouseItem::where('account_id', $accId)
            ->orderBy('brand')->orderBy('model')->orderBy('size')
            ->get();
        // Белые пары (закупки «в белую») уходят в 1С; если в размере есть и белые, и серые — продавец уточняет.
        $splits = $warehouse->whiteSplits($items);

        $productRows = WarehouseProduct::with('photos')->where('account_id', $accId)->get();
        $productMap = [];
        foreach ($productRows as $p) {
            $productMap[mb_strtolower(trim(($p->brand ?? '').'|'.($p->model ?? '')))] = $p;
        }

        $products = [];
        $seesCost = Sneakers::seesCost($user);
        foreach ($items->groupBy(fn ($i) => mb_strtolower(trim(($i->brand ?? '').'|'.($i->model ?? '')))) as $key => $group) {
            $first = $group->first();
            $p = $productMap[$key] ?? null;
            $autoName = trim(($first->brand ?? '').' '.($first->model ?? '')) ?: 'Без названия';
            $sizes = [];
            foreach ($group as $i) {
                $sizes[] = [
                    'id' => $i->id,
                    'size' => (string) $i->size,
                    'available' => (int) $i->available,
                    'price' => $i->sale_price !== null ? (float) $i->sale_price : null,
                    'white' => $splits[$i->id]['white'] ?? 0,
                    'other' => $splits[$i->id]['other'] ?? 0,
                    // закупка — для «прибыль · маржа» в листе продажи, только руководителю: продавцу в разметку не попадает
                    'cost' => $seesCost && $i->avg_cost !== null ? (float) $i->avg_cost : null,
                ];
            }
            // цена модели — общая у размеров; разная — «от» самой низкой
            $prices = collect($sizes)->pluck('price')->filter(fn ($v) => $v !== null)->unique()->sort()->values();
            $products[] = [
                'name' => $p && $p->custom_name ? $p->custom_name : $autoName,
                'article' => $p ? (string) $p->article : '',
                'image_url' => $p ? $p->image_url : null,
                'brand' => trim((string) ($first->brand ?? '')),
                'sizes' => collect($sizes)->sortBy('size', SORT_NATURAL)->values()->all(),
                'total_available' => (int) $group->sum(fn ($i) => max(0, $i->available)),
                'price' => $prices->first(),
                'price_from' => $prices->count() > 1,
            ];
        }
        usort($products, fn ($a, $b) => strcmp(mb_strtoupper($a['name']), mb_strtoupper($b['name'])));

        // бренды — чипы над списком (как в прототипе: по первому слову названия — в поле «бренд» склада часто всё название
        // целиком и разный регистр); моделей у бренда, больше — левее; редкие (меньше 3 моделей) — одним чипом «Другие»
        foreach ($products as &$prod) {
            $prod['brand_key'] = Sneakers::brandOf($prod['name']);
        }
        unset($prod);
        $counts = collect($products)->countBy('brand_key')->sortDesc();
        $brands = $counts->filter(fn ($c) => $c >= 3);
        $otherBrands = (int) $counts->reject(fn ($c) => $c >= 3)->sum();
        foreach ($products as &$prod) {
            $prod['brand_chip'] = $brands->has($prod['brand_key']) ? $prod['brand_key'] : '__other';
        }
        unset($prod);

        // «Последние продажи» по дням; прибыль и себестоимость — только руководителю (решение 28.10.2026)
        $images = [];
        foreach ($productRows as $p) {
            $images[mb_strtolower(trim(($p->brand ?? '').'|'.($p->model ?? '')))] = $p->image_url;
        }
        $saleRow = function (Deal $d) use ($seesCost, $images) {
            $item = $d->warehouseItem;
            $name = $item ? trim(($item->brand ?? '').' '.($item->model ?? '')) : '';

            return [
                'id' => $d->id,
                'at' => $d->stock_deducted_at,
                'name' => $name !== '' ? $name : (trim((string) preg_replace('/^Продажа:\s*/u', '', (string) $d->title)) ?: 'Без названия'),
                'size' => $item && trim((string) $item->size) !== '' ? trim((string) $item->size) : null,
                'brand' => trim((string) ($item?->brand ?? '')),
                'qty' => (int) $d->sold_quantity,
                'amount' => (float) ($d->amount ?? 0),
                'cost' => $seesCost && $d->sold_unit_cost !== null ? (float) $d->sold_unit_cost * (int) $d->sold_quantity : null,
                'profit' => $seesCost ? $d->sale_profit : null,
                'source' => $d->manual_source,
                'payment' => $d->payment_method,
                'seller' => $d->responsible?->name,
                'image' => $item ? ($images[mb_strtolower(trim(($item->brand ?? '').'|'.($item->model ?? '')))] ?? null) : null,
            ];
        };
        $recentSales = Deal::where('account_id', $accId)
            ->whereNotNull('stock_deducted_at')
            ->whereNull('returned_at')
            ->with(['warehouseItem:id,brand,model,size,avg_cost', 'responsible:id,name'])
            ->orderByDesc('stock_deducted_at')
            ->limit(60)
            ->get()->map($saleRow);
        $today = Deal::where('account_id', $accId)->whereNull('returned_at')
            ->whereBetween('stock_deducted_at', [now()->startOfDay(), now()->endOfDay()])
            ->with('warehouseItem:id,avg_cost')->get();
        $todaySum = [
            'revenue' => (float) $today->sum(fn ($d) => (float) ($d->amount ?? 0)),
            'pairs' => (int) $today->sum('sold_quantity'),
            'count' => $today->count(),
            'profit' => $seesCost ? (float) $today->sum(fn ($d) => (float) ($d->sale_profit ?? 0)) : null,
        ];

        $done = request()->filled('sold') ? $this->soldInfo((int) request('sold'), $user) : null;

        return view('sale.sneakers', compact('products', 'brands', 'otherBrands', 'recentSales', 'todaySum', 'seesCost', 'done'));
    }

    public function store(Request $request, WarehouseService $warehouse)
    {
        $user = Auth::user();
        $data = $request->validate([
            'warehouse_item_id' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:1', 'max:1000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'source' => ['nullable', 'string', 'max:50'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:32'],
            'white' => ['nullable', 'in:0,1'],
            'payment' => ['nullable', 'in:'.implode(',', array_keys(Deal::PAYMENT_METHODS))],
        ]);

        $item = WarehouseItem::where('account_id', $user->account_id)
            ->whereKey((int) $data['warehouse_item_id'])
            ->first();
        if (! $item) {
            return back()->withErrors(['warehouse_item_id' => 'Товар не найден на складе.']);
        }

        $qty = (int) $data['qty'];

        // Белые пары продаются в 1С чеками (решение 13.10.2026): CRM их не продаёт, а подтягивает продажи из 1С.
        // В размере только белые — отказ; есть и белые, и серые — продаётся серая, если серых хватает.
        $split = $warehouse->whiteSplit($item);
        if ($split['white'] > 0 && $split['other'] < $qty) {
            return redirect()->route('sale.quick', ['item' => $item->id])->withInput()->withErrors(['white' => $split['other'] > 0
                ? 'Серых пар этого размера на складе '.$split['other'].' шт — остальные белые, их продают через 1С (чек на кассе).'
                : 'Белая пара продаётся через 1С (чек на кассе), а не в CRM — продажа подтянется в CRM сама.']);
        }
        $white = false;

        $price = ($data['price'] ?? null) !== null && $data['price'] !== ''
            ? (float) $data['price']
            : ($item->sale_price !== null ? (float) $item->sale_price : null);
        $amount = $price !== null ? round($price * $qty, 2) : null;

        // Клиент (не обязателен). С телефоном — тот же клиент по телефону; без телефона — новый контакт с этим именем.
        // Раньше без телефона искался контакт «с пустым телефоном» — и все такие продажи липли к одному («Рома», 12.10.2026).
        $contact = null;
        $clientName = trim((string) ($data['client_name'] ?? ''));
        $clientPhone = trim((string) ($data['client_phone'] ?? ''));
        if ($clientPhone !== '') {
            $contact = Contact::where('account_id', $user->account_id)->where('phone', $clientPhone)->first();
            if ($contact && $clientName !== '' && trim((string) $contact->name) === '') {
                $contact->forceFill(['name' => $clientName])->save();
            }
        }
        if (! $contact && ($clientName !== '' || $clientPhone !== '')) {
            $contact = Contact::create([
                'account_id' => $user->account_id,
                'name' => $clientName !== '' ? $clientName : null,
                'phone' => $clientPhone !== '' ? $clientPhone : null,
            ]);
        }

        $pipeline = Pipeline::where('account_id', $user->account_id)->orderByDesc('is_default')->first();
        $finalStage = PipelineStage::where('account_id', $user->account_id)
            ->when($pipeline, fn ($q) => $q->where('pipeline_id', $pipeline->id))
            ->where('is_final', 1)->orderBy('sort')->first();
        if (! $pipeline || ! $finalStage) {
            return back()->withErrors(['warehouse_item_id' => 'Воронка продаж не настроена — обратитесь к руководителю.']);
        }

        $deal = Deal::create([
            'account_id' => $user->account_id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $finalStage->id,
            'title' => 'Продажа: '.$item->display_name,
            'title_is_custom' => 1,
            'contact_id' => $contact?->id,
            'responsible_user_id' => $user->id,
            'amount' => $amount,
            'currency' => 'RUB',
            'product_category' => 'sneakers',
            'closed_at' => now(),
            'closed_result' => 'won',
            'closed_by_user_id' => $user->id,
            'warehouse_item_id' => $item->id,
            'sold_quantity' => $qty,
            'manual_source' => trim((string) ($data['source'] ?? '')) ?: null,
            'stock_white' => $white,
            'payment_method' => $data['payment'] ?? null,
        ]);

        DealStageHistory::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'from_stage_id' => null,
            'to_stage_id' => $finalStage->id,
            'changed_by_user_id' => $user->id,
            'changed_at' => now(),
        ]);

        DealActivity::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => $user->id,
            'type' => 'system',
            'body' => 'Быстрая продажа: '.$item->display_name.' × '.$qty.($amount !== null ? ' на '.number_format($amount, 0, ',', ' ').' ₽' : '')
                .(! empty($data['payment']) ? ' · '.mb_strtolower(Deal::PAYMENT_METHODS[$data['payment']]) : ''),
        ]);

        // Списание со склада + уведомление руководителю
        $warehouse->syncDealStock($deal);

        // Итог продажи (чек, сделка, прибыль руководителю) страница берёт из самой сделки по ?sold= — не из флеш-сообщения
        // сессии: его съедал любой запрос между продажей и открытием страницы (опрос уведомлений раз в 25 с).
        return redirect()->route('sale.quick', ['sold' => $deal->id]);
    }

    /** Баннер «Продано» — своя продажа за последний час; прибыль — только руководителю */
    private function soldInfo(int $dealId, $user): ?array
    {
        $deal = Deal::with('warehouseItem')->where('account_id', $user->account_id)->whereKey($dealId)
            ->whereNotNull('stock_deducted_at')->where('stock_deducted_at', '>=', now()->subHour())->first();
        if (! $deal) {
            return null;
        }
        $seesCost = Sneakers::seesCost($user);

        return [
            'deal_id' => $deal->id,
            'name' => $deal->warehouseItem?->display_name ?? $deal->title,
            'qty' => (int) $deal->sold_quantity,
            'amount' => $deal->amount !== null ? (float) $deal->amount : null,
            'low' => $deal->warehouseItem && $deal->warehouseItem->available <= 0,
            'profit' => $seesCost ? $deal->sale_profit : null,
            'margin' => $seesCost ? $deal->sale_margin_percent : null,
        ];
    }
}

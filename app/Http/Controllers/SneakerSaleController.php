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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * «Быстрая продажа» — один экран для продавца:
 * нашёл товар → ткнул размер → нажал «Продать».
 * Сделка, списание со склада, уведомление руководителю и чек — автоматически.
 */
class SneakerSaleController extends Controller
{
    public function form()
    {
        $user = Auth::user();
        $accId = $user->account_id;

        $items = WarehouseItem::where('account_id', $accId)
            ->orderBy('brand')->orderBy('model')->orderBy('size')
            ->get();

        $productRows = WarehouseProduct::with('photos')->where('account_id', $accId)->get();
        $productMap = [];
        foreach ($productRows as $p) {
            $productMap[mb_strtolower(trim(($p->brand ?? '').'|'.($p->model ?? '')))] = $p;
        }

        $products = [];
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
                ];
            }
            $products[] = [
                'name' => $p && $p->custom_name ? $p->custom_name : $autoName,
                'article' => $p ? (string) $p->article : '',
                'image_url' => $p ? $p->image_url : null,
                'brand' => (string) ($first->brand ?? ''),
                'sizes' => $sizes,
                'total_available' => (int) $group->sum(fn ($i) => max(0, $i->available)),
            ];
        }
        usort($products, fn ($a, $b) => strcmp(mb_strtoupper($a['name']), mb_strtoupper($b['name'])));

        return view('sale.quick', compact('products'));
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
        ]);

        $item = WarehouseItem::where('account_id', $user->account_id)
            ->whereKey((int) $data['warehouse_item_id'])
            ->first();
        if (! $item) {
            return back()->withErrors(['warehouse_item_id' => 'Товар не найден на складе.']);
        }

        $qty = (int) $data['qty'];
        $price = $data['price'] !== null && $data['price'] !== ''
            ? (float) $data['price']
            : ($item->sale_price !== null ? (float) $item->sale_price : null);
        $amount = $price !== null ? round($price * $qty, 2) : null;

        // Клиент (не обязателен)
        $contact = null;
        $clientName = trim((string) ($data['client_name'] ?? ''));
        $clientPhone = trim((string) ($data['client_phone'] ?? ''));
        if ($clientName !== '' || $clientPhone !== '') {
            $contact = Contact::firstOrCreate(
                ['account_id' => $user->account_id, 'phone' => $clientPhone !== '' ? $clientPhone : null],
                ['name' => $clientName !== '' ? $clientName : null]
            );
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
            'body' => 'Быстрая продажа: '.$item->display_name.' × '.$qty.($amount !== null ? ' на '.number_format($amount, 0, ',', ' ').' ₽' : ''),
        ]);

        // Списание со склада + уведомление руководителю
        $warehouse->syncDealStock($deal);

        $request->session()->flash('quick_sale', [
            'deal_id' => $deal->id,
            'name' => $item->display_name,
            'qty' => $qty,
            'amount' => $amount,
            'low' => $item->fresh()->available <= 0,
        ]);

        return redirect()->route('sale.quick');
    }
}

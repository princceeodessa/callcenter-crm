<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\OnecSaleDoc;
use App\Models\Purchase;
use App\Services\Onec\SaleDocExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * «1С: продажи белых пар» — что ушло в 1С «Обувь» (по документу на каждую продажу и возврат), что ждёт,
 * что надо уточнить. Сама выгрузка идёт со станции (у CRM нет доступа к 1С), страница показывает и даёт поправить.
 */
class OnecSalesController extends Controller
{
    public function index(SaleDocExport $export)
    {
        $user = Auth::user();
        abort_unless($user->role === 'sneaker_head', 403);
        $accId = $user->account_id;

        $connection = $export->connection($accId);
        $start = $connection ? $export->startDay($connection) : null;
        $since = ($start ?? now()->toDateString()).' 00:00:00';

        $docs = OnecSaleDoc::where('account_id', $accId)->with('deal.warehouseItem')
            ->orderByRaw('COALESCE(onec_date, created_at) DESC')->orderByDesc('id')->limit(100)->get();
        $queue = $start ? collect($export->pending($accId, $start)) : collect();
        $queueDeals = $queue->isEmpty() ? collect() : Deal::where('account_id', $accId)->whereIn('id', $queue->pluck('deal_id'))
            ->with('warehouseItem')->get()->keyBy('id');
        $cancelled = $export->cancelledInCrm($accId);
        // Продажа ещё в «окне» перед выгрузкой — продавец может поправить оплату или «белая / серая».
        $cooling = Deal::where('account_id', $accId)->where('stock_white', true)->whereNotNull('stock_deducted_at')
            ->where('stock_deducted_at', '>=', $since)->where('stock_deducted_at', '>', now()->subMinutes(SaleDocExport::COOLING_MINUTES))
            ->count();

        $whiteItems = Purchase::where('account_id', $accId)->where('is_white', true)->whereNotNull('stocked_at')
            ->whereNotNull('warehouse_item_id')->distinct()->pluck('warehouse_item_id');
        // Продано из позиции, где есть белые пары, а какая именно — не отмечено.
        $ambiguous = Deal::where('account_id', $accId)->whereNull('stock_white')->whereNotNull('stock_deducted_at')
            ->where('stock_deducted_at', '>=', $since)->whereIn('warehouse_item_id', $whiteItems)
            ->with('warehouseItem')->orderByDesc('stock_deducted_at')->get();
        // Белая продажа без способа оплаты — в 1С уйдёт как наличные.
        $noPayment = Deal::where('account_id', $accId)->where('stock_white', true)->whereNull('payment_method')
            ->whereNotNull('stock_deducted_at')->where('stock_deducted_at', '>=', $since)
            ->with('warehouseItem')->orderByDesc('stock_deducted_at')->get();

        return view('onec.index', compact('connection', 'start', 'docs', 'queue', 'queueDeals', 'cancelled', 'cooling', 'ambiguous', 'noPayment'));
    }

    /** Белая/серая и способ оплаты у проданной пары (из карточки сделки и со страницы 1С). */
    public function setFlags(Request $request, Deal $deal)
    {
        $user = Auth::user();
        abort_unless($deal->account_id === $user->account_id, 403);
        $data = $request->validate([
            'white' => ['nullable', 'in:0,1'],
            'payment' => ['nullable', 'in:'.implode(',', array_keys(Deal::PAYMENT_METHODS))],
        ]);

        $changes = [];
        if ($request->has('white')) {
            $white = ($data['white'] ?? null) === null ? null : $data['white'] === '1';
            if ($white !== $deal->stock_white) {
                $deal->stock_white = $white;
                $changes[] = 'пара: '.($white === null ? 'не указано' : ($white ? 'белая' : 'серая'));
            }
        }
        if ($request->has('payment')) {
            $payment = $data['payment'] ?? null;
            if ($payment !== $deal->payment_method) {
                $deal->payment_method = $payment;
                $changes[] = 'оплата: '.($payment ? mb_strtolower(Deal::PAYMENT_METHODS[$payment]) : 'не указана');
            }
        }
        if ($changes === []) {
            return back()->with('status', 'Ничего не изменилось.');
        }
        $deal->save();
        $inOnec = OnecSaleDoc::where('deal_id', $deal->id)->where('status', 'done')->exists();
        DealActivity::create([
            'account_id' => $deal->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => $user->id,
            'type' => 'system',
            'body' => 'Для 1С: '.implode(', ', $changes).($inOnec ? ' — документ в 1С обновится при следующей выгрузке' : ''),
        ]);

        return back()->with('status', 'Сохранено: '.implode(', ', $changes).'.');
    }
}

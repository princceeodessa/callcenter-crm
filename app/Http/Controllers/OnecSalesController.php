<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\OnecRetailDay;
use App\Models\Purchase;
use App\Services\Onec\RetailDayExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * «1С: продажи белых пар» — что ушло в 1С «Обувь», что ждёт, что надо уточнить.
 * Сама выгрузка идёт со станции (у CRM нет доступа к 1С), страница только показывает и даёт поправить сделки.
 */
class OnecSalesController extends Controller
{
    public function index(RetailDayExport $export)
    {
        $user = Auth::user();
        abort_unless($user->role === 'sneaker_head', 403);
        $accId = $user->account_id;

        $connection = $export->connection($accId);
        $start = $connection ? $export->startDay($connection) : null;
        $today = now()->toDateString();

        $days = OnecRetailDay::where('account_id', $accId)->orderByDesc('day')->limit(60)->get();
        $todayRows = $start && $start <= $today ? ($export->rowsByDay($accId, $today, $today)[$today] ?? []) : [];
        $waiting = $start ? collect($export->pending($accId, $start)) : collect();

        $whiteItems = Purchase::where('account_id', $accId)->where('is_white', true)->whereNotNull('stocked_at')
            ->whereNotNull('warehouse_item_id')->distinct()->pluck('warehouse_item_id');
        $since = $start ? $start.' 00:00:00' : $today.' 00:00:00';
        // Продано из позиции, где есть белые пары, а какая именно — не отмечено.
        $ambiguous = Deal::where('account_id', $accId)->whereNull('stock_white')->whereNotNull('stock_deducted_at')
            ->where('stock_deducted_at', '>=', $since)->whereIn('warehouse_item_id', $whiteItems)
            ->with('warehouseItem')->orderByDesc('stock_deducted_at')->get();
        // Белая продажа без способа оплаты — в отчёт 1С уйдёт как наличные.
        $noPayment = Deal::where('account_id', $accId)->where('stock_white', true)->whereNull('payment_method')
            ->whereNotNull('stock_deducted_at')->where('stock_deducted_at', '>=', $since)
            ->with('warehouseItem')->orderByDesc('stock_deducted_at')->get();

        return view('onec.index', compact('connection', 'start', 'days', 'todayRows', 'waiting', 'ambiguous', 'noPayment'));
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
        DealActivity::create([
            'account_id' => $deal->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => $user->id,
            'type' => 'system',
            'body' => 'Для 1С: '.implode(', ', $changes).($deal->onec_sale_day ? ' — отчёт за '.$deal->onec_sale_day->format('d.m.Y').' обновится при следующей выгрузке' : ''),
        ]);

        return back()->with('status', 'Сохранено: '.implode(', ', $changes).'.');
    }
}

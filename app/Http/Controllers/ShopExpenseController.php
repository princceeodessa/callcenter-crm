<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OwnerPeriod;
use App\Models\ShopExpense;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Sales\ShopExpenses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * «Расходы» кроссовок (24.11.2026): сотрудник вносит трату магазина (сумма, на что, категория, чем оплачено, фото чека).
 * Продавец видит свои траты; руководитель и владелец — все, с итогами по категориям и сотрудникам, отмечают
 * «деньги вернули» по тратам своими деньгами. Итог за период — и в сводке владельца («Чистыми останется»).
 */
class ShopExpenseController extends Controller
{
    use OwnerPeriod;

    public function index(Request $request)
    {
        $user = Auth::user();
        $accId = $user->account_id;
        $all = ShopExpenses::seesAll($user);
        [$period, $start, $endExclusive] = $this->period($request);
        $end = $endExclusive->copy()->subDay();

        $category = array_key_exists((string) $request->query('category'), ShopExpenses::CATEGORIES) ? (string) $request->query('category') : null;
        $who = $all && $request->filled('user') ? (int) $request->query('user') : null;
        $owedOnly = $all && $request->boolean('owed');

        $list = ShopExpense::where('account_id', $accId)->with(['user:id,name', 'reimburser:id,name'])
            ->unless($all, fn ($q) => $q->where('user_id', $user->id))
            ->when($who, fn ($q) => $q->where('user_id', $who))
            ->when($category, fn ($q) => $q->where('category', $category))
            // «к возврату» — все невозвращённые траты своими деньгами, за любой период
            ->when($owedOnly, fn ($q) => $q->where('paid_from', 'own')->whereNull('reimbursed_at'),
                fn ($q) => $q->whereBetween('spent_on', [$start->toDateString(), $end->toDateString()]))
            ->orderByDesc('spent_on')->orderByDesc('id')->limit(500)->get();

        $summary = ShopExpenses::summary($accId, $start, $end, $all ? $who : $user->id);
        $byUser = $all
            ? ShopExpense::where('account_id', $accId)->whereBetween('spent_on', [$start->toDateString(), $end->toDateString()])
                ->with('user:id,name')->get()->groupBy('user_id')
                ->map(fn ($g) => ['name' => $g->first()->user?->name ?? '—', 'id' => (int) $g->first()->user_id, 'total' => (float) $g->sum('amount'), 'count' => $g->count()])
                ->sortByDesc('total')->values()
            : collect();
        $people = $all ? User::where('account_id', $accId)->whereIn('role', ['sneaker_head', 'sneaker_operator', 'sneaker_owner'])->orderBy('name')->get(['id', 'name']) : collect();
        $added = $request->filled('added') ? ShopExpense::where('account_id', $accId)->whereKey((int) $request->query('added'))->first() : null;

        return view('expenses.index', [
            'list' => $list, 'summary' => $summary, 'byUser' => $byUser, 'people' => $people, 'added' => $added,
            'seesAll' => $all, 'manages' => ShopExpenses::manages($user),
            'period' => $period, 'periods' => self::PERIODS, 'fromValue' => $start->toDateString(), 'toValue' => $end->toDateString(),
            'category' => $category, 'who' => $who, 'owedOnly' => $owedOnly,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'description' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(ShopExpenses::CATEGORIES))],
            'paid_from' => ['required', Rule::in(array_keys(ShopExpenses::PAID_FROM))],
            'spent_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.now()->subDays(370)->toDateString()],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:12288'],
        ], [
            'amount.required' => 'Укажите сумму.',
            'amount.min' => 'Сумма — от 1 ₽.',
            'description.required' => 'Напишите, на что потратили.',
            'category.required' => 'Выберите категорию.',
            'paid_from.required' => 'Выберите, чем заплатили.',
            'spent_on.before_or_equal' => 'Дата траты не может быть в будущем.',
            'receipt.mimes' => 'Чек — фото (jpg, png, heic) или pdf.',
            'receipt.max' => 'Файл чека больше 12 МБ.',
        ]);

        $path = $request->hasFile('receipt')
            ? $request->file('receipt')->store('expenses/'.$user->account_id.'/'.now()->format('Y-m'), 'local')
            : null;
        $e = ShopExpense::create([
            'account_id' => $user->account_id,
            'spent_on' => $data['spent_on'],
            'amount' => round((float) $data['amount'], 2),
            'category' => $data['category'],
            'paid_from' => $data['paid_from'],
            'description' => trim($data['description']),
            'receipt_path' => $path,
            'user_id' => $user->id,
        ]);
        $this->notifyManagement($e, $user);

        return redirect()->route('expenses.index', ['added' => $e->id]);
    }

    public function destroy(ShopExpense $expense)
    {
        $user = Auth::user();
        abort_unless((int) $expense->account_id === (int) $user->account_id, 404);
        abort_unless(ShopExpenses::canDelete($user, $expense), 403, 'Удалить можно только свою трату в день, когда внесли. Остальное — руководителю.');
        if ($expense->receipt_path) {
            Storage::disk('local')->delete($expense->receipt_path);
        }
        UserNotification::where('account_id', $expense->account_id)->where('type', 'shop_expense')->where('source_id', $expense->id)->delete();
        $expense->delete();

        return back()->with('status', 'Трата удалена.');
    }

    /** Трата своими деньгами: руководство отмечает, что деньги сотруднику вернули (или снимает отметку) */
    public function reimburse(Request $request, ShopExpense $expense)
    {
        $user = Auth::user();
        abort_unless((int) $expense->account_id === (int) $user->account_id, 404);
        abort_unless(ShopExpenses::manages($user), 403);
        abort_unless($expense->paid_from === 'own', 422);
        $back = $request->boolean('undo');
        $expense->forceFill(['reimbursed_at' => $back ? null : now(), 'reimbursed_by' => $back ? null : $user->id])->save();

        return back()->with('status', $back ? 'Отметка «вернули» снята.' : 'Отмечено: деньги вернули.');
    }

    public function receipt(ShopExpense $expense)
    {
        $user = Auth::user();
        abort_unless((int) $expense->account_id === (int) $user->account_id && $expense->receipt_path, 404);
        abort_unless(ShopExpenses::seesAll($user) || (int) $expense->user_id === (int) $user->id, 403);
        abort_unless(Storage::disk('local')->exists($expense->receipt_path), 404);

        return Storage::disk('local')->response($expense->receipt_path, 'chek-'.$expense->id.'.'.pathinfo($expense->receipt_path, PATHINFO_EXTENSION),
            ['Cache-Control' => 'private, max-age=3600']);
    }

    /** Руководителю — уведомление (колокольчик) о трате, которую внёс кто-то другой */
    private function notifyManagement(ShopExpense $e, User $author): void
    {
        $heads = User::where('account_id', $e->account_id)->where('role', 'sneaker_head')->where('is_active', true)
            ->where('id', '!=', $author->id)->pluck('id');
        $body = $author->name.': '.number_format((float) $e->amount, 0, ',', ' ').' ₽ — '.$e->description
            .' · '.(ShopExpenses::CATEGORIES[$e->category] ?? $e->category).' · '.mb_strtolower(ShopExpenses::PAID_FROM[$e->paid_from] ?? '');
        foreach ($heads as $uid) {
            UserNotification::create([
                'account_id' => $e->account_id, 'user_id' => $uid, 'type' => 'shop_expense', 'title' => 'Новый расход',
                'body' => $body, 'source_type' => 'shop_expense', 'source_id' => $e->id, 'payload' => ['expense_id' => $e->id], 'is_read' => false,
            ]);
        }
    }
}

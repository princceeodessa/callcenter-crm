<?php

namespace App\Support\Sales;

use App\Models\ShopExpense;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * «Расходы» кроссовок (24.11.2026): категории, чем оплачено, итоги за период (страница «Расходы» и сводка владельца).
 * Видимость: продавец — только свои траты; руководитель и владелец — все.
 */
class ShopExpenses
{
    public const CATEGORIES = [
        'rent' => 'Аренда',
        'salary' => 'Зарплата',
        'delivery' => 'Доставка товара',
        'ads' => 'Реклама',
        'packaging' => 'Упаковка и расходники',
        'household' => 'Хозтовары и уборка',
        'transport' => 'Транспорт и такси',
        'services' => 'Связь, интернет, сервисы',
        'bank' => 'Банк, налоги, комиссии',
        'other' => 'Прочее',
    ];

    public const PAID_FROM = [
        'cash' => 'Из кассы',
        'card' => 'Карта / счёт магазина',
        'own' => 'Свои деньги — вернуть',
    ];

    /** Все траты видят руководитель и владелец; продавец — только свои */
    public static function seesAll(User $user): bool
    {
        return in_array($user->role, ['sneaker_head', 'sneaker_owner'], true);
    }

    /** Отмечать «деньги вернули» и удалять чужие траты — руководитель и владелец */
    public static function manages(User $user): bool
    {
        return self::seesAll($user);
    }

    /** Продавец удаляет свою трату только в день, когда внёс (исправить ошибку); руководство — любую */
    public static function canDelete(User $user, ShopExpense $e): bool
    {
        return self::manages($user) || ((int) $e->user_id === (int) $user->id && $e->created_at?->isToday());
    }

    /**
     * Итоги за период [from, to] по дате траты. $userId — только траты этого сотрудника.
     *
     * @return array{total: float, count: int, by_category: array<string, float>, by_paid: array<string, float>, owed: float, owed_count: int}
     */
    public static function summary(int $accountId, CarbonInterface $from, CarbonInterface $to, ?int $userId = null): array
    {
        $base = ShopExpense::query()->where('account_id', $accountId)->when($userId, fn ($q) => $q->where('user_id', $userId));
        $rows = (clone $base)->whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])->get(['amount', 'category', 'paid_from']);
        $byCategory = $rows->groupBy('category')->map(fn ($g) => (float) $g->sum('amount'))->sortDesc()->all();
        $byPaid = $rows->groupBy('paid_from')->map(fn ($g) => (float) $g->sum('amount'))->all();
        // к возврату — за всё время, не только за период: долг сотруднику не «сгорает» со сменой месяца
        $owed = (clone $base)->where('paid_from', 'own')->whereNull('reimbursed_at');

        return [
            'total' => (float) $rows->sum('amount'),
            'count' => $rows->count(),
            'by_category' => $byCategory,
            'by_paid' => $byPaid,
            'owed' => (float) (clone $owed)->sum('amount'),
            'owed_count' => (clone $owed)->count(),
        ];
    }
}

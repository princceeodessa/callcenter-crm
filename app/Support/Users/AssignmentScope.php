<?php

namespace App\Support\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AssignmentScope
{
    public const GROUP_ACCOUNT = 'account';
    public const GROUP_CALL_CENTER = 'call_center';

    public static function callCenterRoles(): array
    {
        return ['operator', 'main_operator'];
    }

    public static function canAssignToAll(User $actor): bool
    {
        return in_array((string) $actor->role, ['admin', 'main_operator', 'operator'], true);
    }

    public static function groupForAll(User $actor): string
    {
        return in_array((string) $actor->role, self::callCenterRoles(), true)
            ? self::GROUP_CALL_CENTER
            : self::GROUP_ACCOUNT;
    }

    public static function groupUserIds(int $accountId, ?string $group): array
    {
        $group = trim((string) $group);
        if ($group === '') {
            return [];
        }

        $query = User::query()
            ->where('account_id', $accountId)
            ->where('is_active', true);

        if ($group === self::GROUP_CALL_CENTER) {
            $query->whereIn('role', self::callCenterRoles());
        }

        return $query
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->values()
            ->all();
    }

    public static function query(User $actor): Builder
    {
        $query = User::query()
            ->where('account_id', $actor->account_id)
            ->where('is_active', true);

        // Оператор колл-центра видит только сотрудников колл-центра и руководителя КЦ.
        if ((string) $actor->role === 'operator') {
            $query->whereIn('role', ['operator', 'main_operator']);
        }

        return $query;
    }
}

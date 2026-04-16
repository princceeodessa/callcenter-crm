<?php

namespace App\Support\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AssignmentScope
{
    public static function canAssignToAll(User $actor): bool
    {
        return in_array((string) $actor->role, ['admin', 'main_operator'], true);
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

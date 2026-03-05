<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Multi-tenant safety net.
 *
 * Adds a global scope by current user's account_id and auto-fills account_id on create.
 *
 * IMPORTANT: do NOT use this trait on the User model (auth queries happen without an account context).
 */
trait BelongsToAccount
{
    protected static function bootBelongsToAccount(): void
    {
        static::addGlobalScope('account', function (Builder $builder) {
            $user = Auth::user();
            if ($user && $user->account_id) {
                $table = $builder->getModel()->getTable();
                $builder->where("{$table}.account_id", $user->account_id);
            }
        });

        static::creating(function ($model) {
            $user = Auth::user();
            if ($user && $user->account_id && empty($model->account_id)) {
                $model->account_id = $user->account_id;
            }
        });
    }
}

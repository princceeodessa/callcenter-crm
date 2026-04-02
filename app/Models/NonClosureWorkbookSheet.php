<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class NonClosureWorkbookSheet extends Model
{
    use BelongsToAccount;

    public const CATEGORY_DIRECTORY = 'directory';
    public const CATEGORY_SUMMARY = 'summary';
    public const CATEGORY_ANALYTICS = 'analytics';
    public const CATEGORY_PRODUCTS = 'products';
    public const CATEGORY_SALES = 'sales';
    public const CATEGORY_OTHER = 'other';

    protected $fillable = [
        'account_id',
        'workbook_id',
        'name',
        'slug',
        'category',
        'position',
        'owner_user_id',
        'header_row_index',
        'row_count',
        'column_count',
        'header',
        'rows',
        'notes',
        'preview_text',
        'meta',
    ];

    protected $casts = [
        'header' => 'array',
        'rows' => 'array',
        'meta' => 'array',
    ];

    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_DIRECTORY => 'Справочник',
            self::CATEGORY_SUMMARY => 'Итоги',
            self::CATEGORY_ANALYTICS => 'Аналитика',
            self::CATEGORY_PRODUCTS => 'Товары',
            self::CATEGORY_SALES => 'Продажи',
            self::CATEGORY_OTHER => 'Другое',
        ];
    }

    public function workbook()
    {
        return $this->belongsTo(NonClosureWorkbook::class, 'workbook_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function sharedUsers()
    {
        return $this->belongsToMany(User::class, 'non_closure_workbook_sheet_user', 'workbook_sheet_id', 'user_id')
            ->withPivot('can_edit')
            ->withTimestamps()
            ->orderBy('name');
    }

    public function rowStates()
    {
        return $this->hasMany(NonClosureSheetRowState::class, 'workbook_sheet_id')
            ->orderBy('row_index');
    }

    public function rowActivities()
    {
        return $this->hasMany(NonClosureSheetRowActivity::class, 'workbook_sheet_id')
            ->latest('created_at')
            ->latest('id');
    }

    public function scopeAccessibleFor($query, User $user)
    {
        if ($user->role === 'admin') {
            return $query;
        }

        return $query->where(function ($inner) use ($user) {
            $inner->where('owner_user_id', $user->id)
                ->orWhereHas('workbook', function ($workbook) use ($user) {
                    $workbook->where('owner_user_id', $user->id);
                })
                ->orWhereHas('sharedUsers', function ($shared) use ($user) {
                    $shared->where('users.id', $user->id);
                });
        });
    }
}

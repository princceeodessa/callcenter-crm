<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class NonClosureSheetRowState extends Model
{
    use BelongsToAccount;

    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CALL = 'call';
    public const STATUS_FOLLOW_UP = 'follow_up';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'account_id',
        'workbook_sheet_id',
        'row_index',
        'status',
        'comment',
        'assigned_user_id',
        'updated_by_user_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW => 'Новая',
            self::STATUS_IN_PROGRESS => 'В работе',
            self::STATUS_CALL => 'Позвонить',
            self::STATUS_FOLLOW_UP => 'Дожать',
            self::STATUS_WAITING => 'Ждёт ответа',
            self::STATUS_SUCCESS => 'Готово',
            self::STATUS_ARCHIVED => 'Неактуально',
        ];
    }

    public static function statusToneMap(): array
    {
        return [
            self::STATUS_NEW => 'slate',
            self::STATUS_IN_PROGRESS => 'blue',
            self::STATUS_CALL => 'orange',
            self::STATUS_FOLLOW_UP => 'violet',
            self::STATUS_WAITING => 'amber',
            self::STATUS_SUCCESS => 'green',
            self::STATUS_ARCHIVED => 'neutral',
        ];
    }

    public function sheet()
    {
        return $this->belongsTo(NonClosureWorkbookSheet::class, 'workbook_sheet_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function activities()
    {
        return $this->hasMany(NonClosureSheetRowActivity::class, 'row_state_id')
            ->latest('created_at')
            ->latest('id');
    }

    public function getStatusLabelAttribute(): string
    {
        return static::statusOptions()[$this->status] ?? $this->status;
    }

    public function getStatusToneAttribute(): string
    {
        return static::statusToneMap()[$this->status] ?? 'neutral';
    }
}

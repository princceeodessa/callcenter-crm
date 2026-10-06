<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

/** Выгрузка дня продаж белых пар в 1С «Обувь» (один «Отчёт о розничных продажах» на день). */
class OnecRetailDay extends Model
{
    use BelongsToAccount;

    public const STATUS_LABELS = [
        'pending' => 'ждёт выгрузки',
        'done' => 'в 1С',
        'partial' => 'частично: не всем парам нашлась карточка с остатком',
        'error' => 'ошибка',
    ];

    protected $fillable = [
        'account_id', 'day', 'status', 'rows_hash', 'onec_uuid', 'onec_number',
        'pairs', 'amount', 'unmapped', 'error', 'exported_at', 'attempted_at',
    ];

    protected $casts = [
        'day' => 'date',
        'unmapped' => 'array',
        'amount' => 'decimal:2',
        'pairs' => 'integer',
        'exported_at' => 'datetime',
        'attempted_at' => 'datetime',
    ];
}

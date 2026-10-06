<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

/** Документ 1С «Обувь» по сделке: «Отчёт о розничных продажах» на одну продажу белой пары или на её возврат. */
class OnecSaleDoc extends Model
{
    use BelongsToAccount;

    public const KIND_LABELS = ['sale' => 'продажа', 'return' => 'возврат'];

    public const STATUS_LABELS = [
        'pending' => 'ждёт выгрузки',
        'done' => 'в 1С',
        'waiting' => 'ждёт прихода в 1С',
        'error' => 'ошибка',
    ];

    protected $fillable = [
        'account_id', 'deal_id', 'kind', 'status', 'rows_hash', 'onec_uuid', 'onec_number', 'onec_date',
        'card_code', 'amount', 'reason', 'exported_at', 'attempted_at',
    ];

    protected $casts = [
        'onec_date' => 'datetime',
        'amount' => 'decimal:2',
        'exported_at' => 'datetime',
        'attempted_at' => 'datetime',
    ];

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }
}

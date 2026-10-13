<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

/** Строка чека 1С «Обувь», принятая в CRM: белая продажа (или возврат) и пара, на которую она легла. */
class OnecSaleImport extends Model
{
    use BelongsToAccount;

    public const TYPE_LABELS = ['check' => 'чек', 'return' => 'чек на возврат', 'report' => 'отчёт без чеков'];

    public const STATUS_LABELS = [
        'linked' => 'продажа, пара списана',
        'unlinked' => 'продажа, пара не найдена',
        'returned' => 'возврат, пара на складе',
        'return_unmatched' => 'возврат, продажа не найдена',
        'cancelled_in_1c' => 'в 1С документ отменён — проверьте',
    ];

    protected $fillable = [
        'account_id', 'onec_uuid', 'row_key', 'doc_type', 'onec_number', 'onec_date', 'card_code', 'card_name',
        'marking_key', 'qty', 'amount', 'payment', 'deal_id', 'status', 'note',
    ];

    protected $casts = [
        'onec_date' => 'datetime',
        'qty' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }
}

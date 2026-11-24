<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

/** Трата магазина кроссовок (страница «Расходы»), см. App\Support\Sales\ShopExpenses. */
class ShopExpense extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'spent_on', 'amount', 'category', 'paid_from', 'description', 'receipt_path',
        'user_id', 'reimbursed_at', 'reimbursed_by',
    ];

    protected $casts = [
        'spent_on' => 'date',
        'amount' => 'decimal:2',
        'reimbursed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reimburser()
    {
        return $this->belongsTo(User::class, 'reimbursed_by');
    }

    /** Свои деньги сотрудника, ещё не вернули */
    public function getOwedAttribute(): bool
    {
        return $this->paid_from === 'own' && $this->reimbursed_at === null;
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Продажа подарочных сертификатов: деньги в кассе, но не выручка — выручка, когда сертификатом платят за пару. */
class GiftCertificateSale extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'nominal', 'qty', 'amount', 'payment_method', 'user_id', 'client_name', 'client_phone', 'sold_at', 'cancelled_at', 'cancelled_by'];

    protected $casts = [
        'nominal' => 'integer',
        'qty' => 'integer',
        'amount' => 'decimal:2',
        'sold_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Журнал подарочных сертификатов. kind = sale — продали (деньги в кассе, но не выручка — выручка, когда сертификатом
 * платят за пару); kind = cashout — обналичили (26.11.2026: клиент сдал сертификат и получил деньги — из кассы,
 * не выручка и не расход; сертификат вернулся в остаток).
 */
class GiftCertificateSale extends Model
{
    use BelongsToAccount;

    public const KIND_SALE = 'sale';
    public const KIND_CASHOUT = 'cashout';

    protected $attributes = ['kind' => self::KIND_SALE];

    protected $fillable = ['account_id', 'kind', 'nominal', 'qty', 'amount', 'payment_method', 'user_id', 'client_name', 'client_phone', 'sold_at', 'cancelled_at', 'cancelled_by'];

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

    /** Только продажи сертификатов (без обналичиваний). */
    public function scopeSales(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_SALE);
    }

    public function scopeCashouts(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_CASHOUT);
    }

    public function isCashout(): bool
    {
        return $this->kind === self::KIND_CASHOUT;
    }
}

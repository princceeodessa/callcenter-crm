<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class WarehouseItem extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'brand',
        'model',
        'size',
        'quantity',
        'reserved',
        'consigned',
        'sale_price',
        'avg_cost',
        'low_stock_threshold',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved' => 'integer',
        'consigned' => 'integer',
        'sale_price' => 'decimal:2',
        'avg_cost' => 'decimal:2',
        'low_stock_threshold' => 'integer',
    ];

    public function movements()
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('id');
    }

    public function consignments()
    {
        return $this->hasMany(WarehouseConsignment::class)->orderByDesc('id');
    }

    public function getDisplayNameAttribute(): string
    {
        $name = trim(implode(' ', array_filter([$this->brand, $this->model])));
        if ($name === '') {
            $name = 'Без названия';
        }
        if (!empty($this->size)) {
            $name .= ' · р. '.$this->size;
        }

        return $name;
    }

    public function getAvailableAttribute(): int
    {
        return (int) $this->quantity - (int) $this->reserved - (int) $this->consigned;
    }

    public function getIsLowAttribute(): bool
    {
        return $this->low_stock_threshold > 0 && $this->available <= $this->low_stock_threshold;
    }
}

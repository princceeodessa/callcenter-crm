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
        'sale_price',
        'low_stock_threshold',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'sale_price' => 'decimal:2',
        'low_stock_threshold' => 'integer',
    ];

    public function movements()
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('id');
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

    public function getIsLowAttribute(): bool
    {
        return $this->low_stock_threshold > 0 && $this->quantity <= $this->low_stock_threshold;
    }
}

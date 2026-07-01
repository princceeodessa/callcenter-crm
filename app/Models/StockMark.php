<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class StockMark extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'warehouse_item_id', 'deal_id', 'code', 'status', 'sold_at',
    ];

    protected $casts = [
        'sold_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(WarehouseItem::class, 'warehouse_item_id');
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }
}

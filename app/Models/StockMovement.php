<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'warehouse_item_id',
        'type',
        'quantity',
        'source_type',
        'source_id',
        'user_id',
        'note',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function item()
    {
        return $this->belongsTo(WarehouseItem::class, 'warehouse_item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

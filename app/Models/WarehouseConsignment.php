<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class WarehouseConsignment extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'warehouse_item_id',
        'consignee',
        'quantity',
        'unit_cost',
        'status',
        'note',
        'user_id',
        'given_at',
        'resolved_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'given_at' => 'datetime',
        'resolved_at' => 'datetime',
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

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'purchase_stage_id',
        'title',
        'brand',
        'model',
        'size',
        'quantity',
        'supplier',
        'cost',
        'currency',
        'expected_sale_price',
        'responsible_user_id',
        'article',
        'is_white',
        'notes',
        'sort',
        'closed_at',
        'stocked_at',
        'stocked_quantity',
        'warehouse_item_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'cost' => 'decimal:2',
        'expected_sale_price' => 'decimal:2',
        'is_white' => 'boolean',
        'closed_at' => 'datetime',
        'stocked_at' => 'datetime',
        'stocked_quantity' => 'integer',
    ];

    public function stage()
    {
        return $this->belongsTo(PurchaseStage::class, 'purchase_stage_id');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function warehouseItem()
    {
        return $this->belongsTo(WarehouseItem::class, 'warehouse_item_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class WarehouseProductPhoto extends Model
{
    protected $fillable = ['warehouse_product_id', 'path', 'sort'];

    public function product()
    {
        return $this->belongsTo(WarehouseProduct::class, 'warehouse_product_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}

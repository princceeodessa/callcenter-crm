<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class WarehouseProduct extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'brand', 'model', 'custom_name', 'image_path',
    ];

    public function getDisplayNameAttribute(): string
    {
        if (! empty($this->custom_name)) {
            return $this->custom_name;
        }
        $n = trim(($this->brand ?? '').' '.($this->model ?? ''));
        return $n !== '' ? $n : 'Без названия';
    }

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image_path)) {
            return null;
        }
        return Storage::disk('public')->url($this->image_path);
    }
}

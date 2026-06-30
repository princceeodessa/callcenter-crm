<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class PurchaseStage extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'name', 'sort', 'color', 'is_final'];

    protected $casts = ['is_final' => 'boolean'];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}

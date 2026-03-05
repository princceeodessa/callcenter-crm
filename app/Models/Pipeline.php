<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Pipeline extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id','name','is_default'];

    protected $casts = ['is_default' => 'boolean'];

    public function stages()
    {
        return $this->hasMany(PipelineStage::class);
    }
}

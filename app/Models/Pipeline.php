<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pipeline extends Model
{
    protected $fillable = ['account_id','name','is_default'];

    protected $casts = ['is_default' => 'boolean'];

    public function stages()
    {
        return $this->hasMany(PipelineStage::class);
    }
}

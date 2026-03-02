<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PipelineStage extends Model
{
    protected $fillable = ['account_id','pipeline_id','name','sort','color','is_final'];

    protected $casts = ['is_final' => 'boolean'];

    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class);
    }
}

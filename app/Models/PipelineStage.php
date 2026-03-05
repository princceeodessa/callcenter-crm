<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class PipelineStage extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id','pipeline_id','name','sort','color','is_final'];

    protected $casts = ['is_final' => 'boolean'];

    public function pipeline()
    {
        return $this->belongsTo(Pipeline::class);
    }
}

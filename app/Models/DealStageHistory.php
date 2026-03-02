<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealStageHistory extends Model
{
    protected $table = 'deal_stage_history';

    public $timestamps = false;

    protected $fillable = [
        'account_id','deal_id','from_stage_id','to_stage_id','changed_by_user_id','changed_at'
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];
}
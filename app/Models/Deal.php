<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    protected $fillable = [
        'account_id','pipeline_id','stage_id',
        'title','contact_id','responsible_user_id',
        'amount','currency',
        'readiness_status','is_unread','has_script_deviation',
        'closed_at','closed_result','closed_reason','closed_by_user_id'
    ];

    protected $casts = [
        'is_unread' => 'boolean',
        'has_script_deviation' => 'boolean',
        'closed_at' => 'datetime',
    ];

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function stage()
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function activities()
    {
        return $this->hasMany(DealActivity::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function callRecordings()
    {
        return $this->hasMany(CallRecording::class);
    }
}

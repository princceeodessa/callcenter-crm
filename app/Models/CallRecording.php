<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallRecording extends Model
{
    protected $fillable = [
        'account_id',
        'deal_id',
        'callid',
        'recording_url',
        'local_path',
        'duration_seconds',
        'transcript_status',
        'transcript_text',
        'transcript_error',
        'transcribed_at',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'transcribed_at' => 'datetime',
    ];

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }
}

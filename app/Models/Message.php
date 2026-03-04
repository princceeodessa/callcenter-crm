<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'account_id',
        'conversation_id',
        'direction',
        'author',
        'body',
        'external_id',
        'payload',
        'status',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'provider',
        'direction',
        'event_type',
        'external_id',
        'payload',
        'received_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
    ];
}

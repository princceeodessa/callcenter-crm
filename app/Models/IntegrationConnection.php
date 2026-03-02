<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationConnection extends Model
{
    protected $fillable = [
        'account_id',
        'provider',
        'status',
        'settings',
        'last_error',
        'last_synced_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'last_synced_at' => 'datetime',
    ];
}

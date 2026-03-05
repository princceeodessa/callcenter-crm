<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class IntegrationConnection extends Model
{
    use BelongsToAccount;

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

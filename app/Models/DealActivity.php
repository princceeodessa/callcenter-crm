<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealActivity extends Model
{
    protected $fillable = [
        'account_id','deal_id','author_user_id',
        'type','body','payload'
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}

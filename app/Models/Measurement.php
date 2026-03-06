<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Measurement extends Model
{
    use HasFactory, BelongsToAccount;

    protected $fillable = [
        'account_id',
        'scheduled_at',
        'duration_minutes',
        'address',
        'phone',
        'status',
        'assigned_user_id',
        'created_by_user_id',
        'callcenter_comment',
        'measurer_comment',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

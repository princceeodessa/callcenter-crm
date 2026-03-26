<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id','user_id',
        'type','title','body',
        'source_type','source_id',
        'payload','is_read'
    ];

    protected $casts = [
        'payload' => 'array',
        'is_read' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getTitleAttribute(?string $value): string
    {
        return TextNormalizer::normalizeMojibake($value);
    }

    public function getBodyAttribute(?string $value): string
    {
        return TextNormalizer::normalizeMojibake($value);
    }
}

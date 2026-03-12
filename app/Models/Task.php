<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use BelongsToAccount;

    private const UNASSIGNED_LABEL = "\u{0412}\u{0441}\u{0435}\u{043C}";

    protected $fillable = [
        'account_id','deal_id','assigned_user_id',
        'title','description','status','due_at','completed_at'
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'notified_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function getAssigneeLabelAttribute(): string
    {
        $name = trim((string) ($this->assignedTo?->name ?? ''));

        if ($name === '' && $this->assigned_user_id) {
            $name = trim((string) ($this->assignedTo()->value('name') ?? ''));
        }

        return $name !== '' ? $name : self::UNASSIGNED_LABEL;
    }
}
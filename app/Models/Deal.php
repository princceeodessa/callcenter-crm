<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id','pipeline_id','stage_id',
        'title','title_is_custom','contact_id','responsible_user_id',
        'amount','currency',
        'readiness_status','is_unread','has_script_deviation',
        'closed_at','closed_result','closed_reason','closed_by_user_id'
    ];

    protected $casts = [
        'is_unread' => 'boolean',
        'has_script_deviation' => 'boolean',
        'closed_at' => 'datetime',
        'title_is_custom' => 'boolean',
    ];

    protected $appends = [
        'is_ready',
        'missing_fields',
    ];

    public function getMissingFieldsAttribute(): array
    {
        $missing = [];
        if (!$this->responsible_user_id) $missing[] = 'responsible';
        if (!$this->amount || (float)$this->amount <= 0) $missing[] = 'amount';
        if (!$this->title_is_custom) $missing[] = 'title';
        return $missing;
    }

    public function getIsReadyAttribute(): bool
    {
        return count($this->missing_fields) === 0;
    }

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

    public function primaryConversation(): ?Conversation
    {
        if ($this->relationLoaded('conversations')) {
            return $this->conversations
                ->sortByDesc(fn ($conversation) => optional($conversation->last_message_at)->getTimestamp() ?? 0)
                ->first();
        }

        return $this->conversations()
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->first();
    }

    public function getLeadDisplayNameAttribute(): ?string
    {
        $contactName = trim((string) ($this->contact?->name ?? ''));
        if ($contactName !== '') {
            return $contactName;
        }

        return $this->primaryConversation()?->lead_name;
    }

    public function getLeadSourceLabelAttribute(): string
    {
        return $this->primaryConversation()?->source_label ?? 'CRM';
    }

    public function getLeadSourceBadgeClassAttribute(): string
    {
        return $this->primaryConversation()?->source_badge_class ?? 'source-badge source-badge-default';
    }

    public function getLeadSourceSurfaceClassAttribute(): string
    {
        return $this->primaryConversation()?->source_surface_class ?? 'source-surface source-surface-default';
    }
}

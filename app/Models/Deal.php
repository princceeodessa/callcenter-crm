<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    use BelongsToAccount;

    private const DEFAULT_SOURCE_LABEL = 'CRM';
    private const DEFAULT_SOURCE_BADGE_CLASS = 'source-badge source-badge-default';
    private const DEFAULT_SOURCE_SURFACE_CLASS = 'source-surface source-surface-default';
    private const DEFAULT_SOURCE_ICON_HTML = '<span class="source-icon source-icon-default"><i class="bi bi-chat-dots-fill"></i></span>';
    private const PHONE_SOURCE_LABEL = "\u{0422}\u{0435}\u{043B}\u{0435}\u{0444}\u{043E}\u{043D}";
    private const PHONE_SOURCE_BADGE_CLASS = 'source-badge source-badge-megafon_vats';
    private const PHONE_SOURCE_SURFACE_CLASS = 'source-surface source-surface-megafon_vats';
    private const PHONE_SOURCE_ICON_HTML = '<span class="source-icon source-icon-megafon_vats"><i class="bi bi-telephone-fill"></i></span>';

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
        try {
            $contactName = trim((string) ($this->contact?->name ?? ''));
            if ($contactName !== '') {
                return $contactName;
            }

            $conversation = $this->primaryConversation();
            return $conversation?->lead_name;
        } catch (\Throwable) {
            return trim((string) ($this->attributes['title'] ?? '')) ?: null;
        }
    }

    public function getLeadSourceLabelAttribute(): string
    {
        if ($conversation = $this->resolveLeadSourceConversation()) {
            return $conversation->source_label;
        }

        return $this->fallbackLeadSourceMeta()['label'];
    }

    public function getLeadSourceBadgeClassAttribute(): string
    {
        if ($conversation = $this->resolveLeadSourceConversation()) {
            return $conversation->source_badge_class;
        }

        return $this->fallbackLeadSourceMeta()['badge_class'];
    }

    public function getLeadSourceIconHtmlAttribute(): string
    {
        if ($conversation = $this->resolveLeadSourceConversation()) {
            return $conversation->source_icon_html;
        }

        return $this->fallbackLeadSourceMeta()['icon_html'];
    }

    public function getLeadSourceChatUrlAttribute(): ?string
    {
        if ($conversation = $this->resolveLeadSourceConversation()) {
            return $conversation->chat_url;
        }

        return null;
    }

    public function getLeadSourceSurfaceClassAttribute(): string
    {
        if ($conversation = $this->resolveLeadSourceConversation()) {
            return $conversation->source_surface_class;
        }

        return $this->fallbackLeadSourceMeta()['surface_class'];
    }

    private function resolveLeadSourceConversation(): ?Conversation
    {
        try {
            return $this->primaryConversation();
        } catch (\Throwable) {
            return null;
        }
    }

    private function fallbackLeadSourceMeta(): array
    {
        if ($this->hasPhoneLeadSource()) {
            return [
                'label' => self::PHONE_SOURCE_LABEL,
                'badge_class' => self::PHONE_SOURCE_BADGE_CLASS,
                'surface_class' => self::PHONE_SOURCE_SURFACE_CLASS,
                'icon_html' => self::PHONE_SOURCE_ICON_HTML,
            ];
        }

        return [
            'label' => self::DEFAULT_SOURCE_LABEL,
            'badge_class' => self::DEFAULT_SOURCE_BADGE_CLASS,
            'surface_class' => self::DEFAULT_SOURCE_SURFACE_CLASS,
            'icon_html' => self::DEFAULT_SOURCE_ICON_HTML,
        ];
    }

    private function hasPhoneLeadSource(): bool
    {
        if (array_key_exists('phone_call_activities_count', $this->attributes)
            && (int) $this->attributes['phone_call_activities_count'] > 0) {
            return true;
        }

        if (array_key_exists('phone_call_recordings_count', $this->attributes)
            && (int) $this->attributes['phone_call_recordings_count'] > 0) {
            return true;
        }

        if ($this->relationLoaded('activities') && $this->activities->contains(fn ($activity) => $activity->type === 'call')) {
            return true;
        }

        if ($this->relationLoaded('callRecordings') && $this->callRecordings->isNotEmpty()) {
            return true;
        }

        return $this->activities()->where('type', 'call')->exists()
            || $this->callRecordings()->exists();
    }
}
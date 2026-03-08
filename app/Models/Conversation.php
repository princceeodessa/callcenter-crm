<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'deal_id',
        'channel',
        'external_id',
        'status',
        'unread_count',
        'last_message_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'last_message_at' => 'datetime',
    ];

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function getSourceLabelAttribute(): string
    {
        return match ($this->channel) {
            'vk' => 'VK',
            'telegram' => 'Telegram',
            'avito' => 'Avito',
            'megafon_vats' => 'Звонок',
            default => Str::upper((string) $this->channel),
        };
    }

    public function getSourceBadgeClassAttribute(): string
    {
        return 'source-badge source-badge-'.($this->channel ?: 'default');
    }

    public function getSourceSurfaceClassAttribute(): string
    {
        return 'source-surface source-surface-'.($this->channel ?: 'default');
    }

    public function getLeadNameAttribute(): ?string
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        foreach (['lead_name', 'client_name', 'display_name'] as $key) {
            $value = trim((string) ($meta[$key] ?? ''));
            if ($this->looksLikeHumanName($value)) {
                return $value;
            }
        }

        $contactName = trim((string) ($this->deal?->contact?->name ?? ''));
        if ($this->looksLikeHumanName($contactName)) {
            return $contactName;
        }

        $author = trim((string) ($this->lastMessage?->author ?? ''));
        if ($this->looksLikeHumanName($author)) {
            return $author;
        }

        return null;
    }

    public function getDisplayTitleAttribute(): string
    {
        $leadName = $this->lead_name;
        if ($leadName) {
            return $leadName;
        }

        $title = trim((string) ($this->deal?->title ?? ''));
        if ($title !== '') {
            return $title;
        }

        return 'Диалог';
    }

    public function getDisplaySubtitleAttribute(): string
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        $hint = trim((string) ($meta['source_hint'] ?? ''));
        if ($hint !== '') {
            return $hint;
        }

        return 'Источник: '.$this->source_label;
    }

    private function looksLikeHumanName(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return false;
        }

        $normalized = Str::lower($value);
        foreach (['vk:', 'tg:', 'avito:', 'telegram:', 'user ', 'id ', 'chat ', 'peer '] as $bad) {
            if (str_starts_with($normalized, $bad)) {
                return false;
            }
        }

        if (preg_match('/^\d+$/', $value)) {
            return false;
        }

        return preg_match('/[\p{L}]{2,}/u', $value) === 1;
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Services\Integrations\AvitoApiClient;
use App\Services\Integrations\AvitoTokenManager;
use App\Services\Integrations\VkApiClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'deal_id', 'channel', 'external_id', 'status', 'unread_count', 'last_message_at', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'last_message_at' => 'datetime',
    ];

    public function deal() { return $this->belongsTo(Deal::class); }
    public function messages() { return $this->hasMany(Message::class); }
    public function lastMessage() { return $this->hasOne(Message::class)->latestOfMany(); }

    public function getSourceLabelAttribute(): string
    {
        return match ($this->channel) {
            'vk' => 'VK',
            'telegram' => 'Telegram',
            'avito' => 'Avito',
            'megafon_vats' => "\u{0422}\u{0435}\u{043B}\u{0435}\u{0444}\u{043E}\u{043D}",
            default => Str::upper((string) $this->channel),
        };
    }

    public function getSourceBadgeClassAttribute(): string { return 'source-badge source-badge-'.($this->channel ?: 'default'); }
    public function getSourceSurfaceClassAttribute(): string { return 'source-surface source-surface-'.($this->channel ?: 'default'); }

    public function getSourceIconHtmlAttribute(): string
    {
        return match ($this->channel) {
            'vk' => '<span class="source-icon source-icon-vk">vk</span>',
            'telegram' => '<span class="source-icon source-icon-telegram"><i class="bi bi-send-fill"></i></span>',
            'avito' => '<span class="source-icon source-icon-avito">A</span>',
            'megafon_vats' => '<span class="source-icon source-icon-megafon_vats"><i class="bi bi-telephone-fill"></i></span>',
            default => '<span class="source-icon source-icon-default"><i class="bi bi-chat-dots-fill"></i></span>',
        };
    }

    public function getChatUrlAttribute(): ?string
    {
        try {
            $meta = is_array($this->meta) ? $this->meta : [];
            foreach (['chat_url', 'conversation_url', 'source_url', 'external_url', 'url', 'link'] as $key) {
                $value = trim((string) ($meta[$key] ?? ''));
                if ($this->looksLikeAbsoluteUrl($value)) {
                    return $value;
                }
            }

            if (is_string($this->external_id) && $this->looksLikeAbsoluteUrl($this->external_id)) {
                return $this->external_id;
            }

            $message = $this->relationLoaded('lastMessage') ? $this->lastMessage : null;
            if (!$message) {
                $message = $this->messages()->orderByDesc('id')->first();
            }

            $payload = is_array($message?->payload ?? null) ? $message->payload : [];
            foreach ([
                data_get($payload, 'chat.url'),
                data_get($payload, 'chat.link'),
                data_get($payload, 'chat_url'),
                data_get($payload, 'conversation_url'),
                data_get($payload, 'link'),
                data_get($payload, 'url'),
            ] as $candidate) {
                $candidate = is_scalar($candidate) ? trim((string) $candidate) : '';
                if ($this->looksLikeAbsoluteUrl($candidate)) {
                    return $candidate;
                }
            }

            return match ($this->channel) {
                'vk' => preg_match('/^\d+$/', (string) $this->external_id) === 1
                    ? 'https://vk.com/im/convo/'.(string) $this->external_id.'?entrypoint=list_all'
                    : null,
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    public function getLeadNameAttribute(): ?string
    {
        try {
            $meta = is_array($this->meta) ? $this->meta : [];
            foreach (['lead_name', 'client_name', 'display_name', 'buyer_name', 'user_name'] as $key) {
                $value = trim((string) ($meta[$key] ?? ''));
                if ($this->looksLikeHumanName($value)) {
                    return $value;
                }
            }

            $contactName = trim((string) ($this->deal?->contact?->name ?? ''));
            if ($this->looksLikeHumanName($contactName)) {
                return $contactName;
            }

            $message = $this->relationLoaded('lastMessage') ? $this->lastMessage : null;
            if (!$message) {
                $message = $this->messages()->where('direction', 'in')->orderByDesc('id')->first();
            }
            if (!$message) {
                $message = $this->messages()->orderByDesc('id')->first();
            }

            if ($message) {
                $payloadName = $this->extractLeadNameFromPayload($message->payload);
                if ($payloadName) {
                    return $payloadName;
                }
                $author = trim((string) ($message->author ?? ''));
                if ($this->looksLikeHumanName($author)) {
                    return $author;
                }
            }

            if ($this->channel === 'vk') {
                return $this->fetchVkLeadName();
            }
            if ($this->channel === 'avito') {
                return $this->fetchAvitoLeadName();
            }
        } catch (\Throwable) {
            return null;
        }
        return null;
    }

    public function getDisplayTitleAttribute(): string
    {
        try {
            if ($this->lead_name) {
                return $this->lead_name;
            }
            $title = trim((string) ($this->deal?->title ?? ''));
            if ($title !== '' && !$this->looksLikeGenericChatTitle($title)) {
                return $title;
            }
            if ($this->external_id) {
                return $this->source_label.': '.$this->external_id;
            }
        } catch (\Throwable) {
        }
        return 'Диалог';
    }

    public function getDisplaySubtitleAttribute(): string
    {
        try {
            $meta = is_array($this->meta) ? $this->meta : [];
            $hint = trim((string) ($meta['source_hint'] ?? ''));
            if ($hint === '') {
                $hint = $this->extractSourceHintFromPayload($this->relationLoaded('lastMessage') ? $this->lastMessage?->payload : null) ?? '';
            }
            return $hint !== '' ? $hint : 'Источник: '.$this->source_label;
        } catch (\Throwable) {
            return 'Источник: CRM';
        }
    }

    private function fetchVkLeadName(): ?string
    {
        $userId = $this->extractVkUserId();
        if (!$userId) return null;
        $connection = IntegrationConnection::query()->where('account_id', $this->account_id)->where('provider', 'vk')->where('status', 'active')->first();
        $token = trim((string) ($connection?->settings['access_token'] ?? ''));
        if ($token === '') return null;
        try {
            $client = new VkApiClient($token, '5.131', 4.0);
            $response = $client->usersGet($userId);
            $user = data_get($response, 'response.0');
            if (!is_array($user)) return null;
            $name = trim((string) ($user['first_name'] ?? '').' '.(string) ($user['last_name'] ?? ''));
            return $this->looksLikeHumanName($name) ? $name : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchAvitoLeadName(): ?string
    {
        $connection = IntegrationConnection::query()->where('account_id', $this->account_id)->where('provider', 'avito')->where('status', 'active')->first();
        $settings = is_array($connection?->settings) ? $connection->settings : [];
        $chatId = trim((string) $this->external_id);
        try {
            $token = $connection ? app(AvitoTokenManager::class)->getValidToken($connection) : '';
            $settings = is_array($connection?->fresh()?->settings) ? $connection->fresh()->settings : $settings;
        } catch (\Throwable) {
            return null;
        }
        $userId = trim((string) ($settings['user_id'] ?? ''));
        if ($token === '' || $userId === '' || $chatId === '') return null;
        try {
            $client = new AvitoApiClient($token, 'https://api.avito.ru', 4.0);
            return $this->pickAvitoLeadNameFromChat($client->getChat($userId, $chatId), $userId);
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractVkUserId(): ?int
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        $candidates = [$meta['vk_user_id'] ?? null, $meta['from_id'] ?? null];
        $message = $this->messages()->where('direction', 'in')->orderByDesc('id')->first();
        if ($message) {
            $author = trim((string) ($message->author ?? ''));
            if (preg_match('/^vk:(\d+)$/', $author, $m) === 1) $candidates[] = $m[1];
            $payload = is_array($message->payload) ? $message->payload : [];
            $candidates[] = data_get($payload, 'object.message.from_id');
            $candidates[] = data_get($payload, 'message.from_id');
            $candidates[] = data_get($payload, 'from_id');
        }
        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && preg_match('/^\d+$/', (string) $candidate) === 1) {
                $id = (int) $candidate;
                if ($id > 0 && $id < 2000000000) return $id;
            }
        }
        return null;
    }

    private function pickAvitoLeadNameFromChat(array $chat, string $ownerUserId): ?string
    {
        foreach ([data_get($chat, 'buyer.name'), data_get($chat, 'user.name'), data_get($chat, 'client.name')] as $candidate) {
            $candidate = is_scalar($candidate) ? trim((string) $candidate) : '';
            if ($this->looksLikeHumanName($candidate)) return $candidate;
        }
        foreach ([data_get($chat, 'users'), data_get($chat, 'participants'), data_get($chat, 'chat.users'), data_get($chat, 'data.users')] as $users) {
            if (!is_array($users)) continue;
            foreach ($users as $user) {
                if (!is_array($user)) continue;
                $id = (string) ($user['id'] ?? $user['user_id'] ?? data_get($user, 'user.id') ?? '');
                if ($id !== '' && $id === $ownerUserId) continue;
                foreach ([$user['name'] ?? null, $user['title'] ?? null, trim((string) ($user['first_name'] ?? '').' '.(string) ($user['last_name'] ?? '')), data_get($user, 'profile.name'), data_get($user, 'user.name')] as $candidate) {
                    $candidate = is_scalar($candidate) ? trim((string) $candidate) : '';
                    if ($this->looksLikeHumanName($candidate)) return $candidate;
                }
            }
        }
        return null;
    }


    private function looksLikeAbsoluteUrl(?string $value): bool
    {
        if (!is_string($value)) return false;
        $value = trim($value);
        if ($value === '') return false;
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function extractLeadNameFromPayload(mixed $payload): ?string
    {
        if (!is_array($payload)) return null;
        $candidates = [
            data_get($payload, 'author.name'), data_get($payload, 'user.name'), data_get($payload, 'buyer.name'), data_get($payload, 'sender.name'), data_get($payload, 'client.name'),
            data_get($payload, 'chat.user.name'), data_get($payload, 'chat.buyer.name'), data_get($payload, 'chat.client.name'),
            data_get($payload, 'chat_full.user.name'), data_get($payload, 'chat_full.buyer.name'), data_get($payload, 'chat_full.client.name'),
            data_get($payload, 'message.author.name'), data_get($payload, 'message.sender.name'), data_get($payload, 'message.user.name'), data_get($payload, 'message.client.name'),
        ];
        $firstName = trim((string) data_get($payload, 'from.first_name'));
        $lastName = trim((string) data_get($payload, 'from.last_name'));
        $fullName = trim($firstName.' '.$lastName);
        if ($fullName !== '') $candidates[] = $fullName;
        foreach ($candidates as $candidate) {
            $candidate = is_scalar($candidate) ? trim((string) $candidate) : '';
            if ($this->looksLikeHumanName($candidate)) return $candidate;
        }
        foreach ($this->collectHumanNames($payload) as $candidate) {
            if ($this->looksLikeHumanName($candidate)) return $candidate;
        }
        return null;
    }

    private function collectHumanNames(array $payload): array
    {
        $found = [];
        $walker = function (mixed $node) use (&$walker, &$found) {
            if (!is_array($node)) return;
            $first = trim((string) ($node['first_name'] ?? ''));
            $last = trim((string) ($node['last_name'] ?? ''));
            $full = trim($first.' '.$last);
            if ($full !== '' && $this->looksLikeHumanName($full)) $found[] = $full;
            $name = trim((string) ($node['name'] ?? ''));
            if ($name !== '' && $this->looksLikeHumanName($name)) $found[] = $name;
            foreach ($node as $value) if (is_array($value)) $walker($value);
        };
        $walker($payload);
        return array_values(array_unique($found));
    }

    private function extractSourceHintFromPayload(mixed $payload): ?string
    {
        if (!is_array($payload)) return null;
        foreach ([data_get($payload, 'chat.context.value.title'), data_get($payload, 'chat.context.value.name'), data_get($payload, 'chat.item.title'), data_get($payload, 'chat.ad.title'), data_get($payload, 'item.title'), data_get($payload, 'ad.title')] as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value !== '' && !$this->looksLikeHumanName($value)) return Str::limit($value, 80);
        }
        return null;
    }

    private function looksLikeGenericChatTitle(string $value): bool
    {
        $value = trim(mb_strtolower($value));
        return $value !== '' && preg_match('/^чат\s+(vk|telegram|avito|телеграм|авито|вк)/u', $value) === 1;
    }

    private function looksLikeHumanName(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') return false;
        $normalized = Str::lower($value);
        foreach (['vk:', 'tg:', 'avito:', 'telegram:', 'user ', 'id ', 'chat ', 'peer '] as $bad) {
            if (str_starts_with($normalized, $bad)) return false;
        }
        if (preg_match('/^\d+$/', $value)) return false;
        return preg_match('/[\p{L}]{2,}/u', $value) === 1;
    }
}

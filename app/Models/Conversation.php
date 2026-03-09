<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Services\Integrations\AvitoApiClient;
use App\Services\Integrations\VkApiClient;
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
                $message = $this->messages()
                    ->where('direction', 'in')
                    ->orderByDesc('id')
                    ->first();
            }
            if (!$message) {
                $message = $this->messages()->orderByDesc('id')->first();
            }

            if ($message) {
                $payloadName = $this->extractLeadNameFromPayload($message->payload);
                if ($payloadName) {
                    return $this->persistLeadName($payloadName);
                }

                $author = trim((string) ($message->author ?? ''));
                if ($this->looksLikeHumanName($author)) {
                    return $author;
                }
            }

            if ($this->channel === 'vk') {
                $vkName = $this->fetchVkLeadName();
                if ($vkName) {
                    return $this->persistLeadName($vkName);
                }
            }

            if ($this->channel === 'avito') {
                $avitoName = $this->fetchAvitoLeadName();
                if ($avitoName) {
                    return $this->persistLeadName($avitoName);
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    public function getDisplayTitleAttribute(): string
    {
        try {
            $leadName = $this->lead_name;
            if ($leadName) {
                return $leadName;
            }

            $title = trim((string) ($this->deal?->title ?? ''));
            if ($title !== '' && !$this->looksLikeGenericChatTitle($title)) {
                return $title;
            }

            if ($this->external_id) {
                return $this->source_label.': '.$this->external_id;
            }
        } catch (\Throwable) {
            // ignore
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
            if ($hint !== '') {
                return $hint;
            }

            return 'Источник: '.$this->source_label;
        } catch (\Throwable) {
            return 'Источник: CRM';
        }
    }

    private function fetchVkLeadName(): ?string
    {
        if ($this->channel !== 'vk') {
            return null;
        }

        $userId = $this->extractVkUserId();
        if (!$userId) {
            return null;
        }

        $connection = IntegrationConnection::query()
            ->where('account_id', $this->account_id)
            ->where('provider', 'vk')
            ->where('status', 'active')
            ->first();

        $token = trim((string) ($connection?->settings['access_token'] ?? ''));
        if ($token === '') {
            return null;
        }

        try {
            $client = new VkApiClient($token, '5.131', 4.0);
            $response = $client->usersGet($userId);
            $user = data_get($response, 'response.0');
            if (!is_array($user)) {
                return null;
            }

            $name = trim((string) ($user['first_name'] ?? '').' '.(string) ($user['last_name'] ?? ''));
            return $this->looksLikeHumanName($name) ? $name : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchAvitoLeadName(): ?string
    {
        if ($this->channel !== 'avito') {
            return null;
        }

        $connection = IntegrationConnection::query()
            ->where('account_id', $this->account_id)
            ->where('provider', 'avito')
            ->where('status', 'active')
            ->first();

        $settings = is_array($connection?->settings) ? $connection->settings : [];
        $token = trim((string) ($settings['access_token'] ?? ''));
        $userId = trim((string) ($settings['user_id'] ?? ''));
        $chatId = trim((string) $this->external_id);
        if ($token === '' || $userId === '' || $chatId === '') {
            return null;
        }

        try {
            $client = new AvitoApiClient($token, 'https://api.avito.ru', 4.0);
            $chat = $client->getChat($userId, $chatId);
            return $this->pickAvitoLeadNameFromChat($chat, $userId);
        } catch (\Throwable) {
            return null;
        }
    }


    private function extractVkUserId(): ?int
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        $candidates = [
            $meta['vk_user_id'] ?? null,
            $meta['from_id'] ?? null,
        ];

        $message = $this->messages()
            ->where('direction', 'in')
            ->orderByDesc('id')
            ->first();

        if ($message) {
            $author = trim((string) ($message->author ?? ''));
            if (preg_match('/^vk:(\d+)$/', $author, $m) === 1) {
                $candidates[] = $m[1];
            }
            $payload = is_array($message->payload) ? $message->payload : [];
            $candidates[] = data_get($payload, 'object.message.from_id');
            $candidates[] = data_get($payload, 'message.from_id');
            $candidates[] = data_get($payload, 'from_id');
        }

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && preg_match('/^\d+$/', (string) $candidate) === 1) {
                $id = (int) $candidate;
                if ($id > 0 && $id < 2000000000) {
                    return $id;
                }
            }
        }

        return null;
    }

    private function persistLeadName(string $name): string
    {
        $name = trim($name);
        if (!$this->looksLikeHumanName($name)) {
            return $name;
        }

        $meta = is_array($this->meta) ? $this->meta : [];
        $current = trim((string) ($meta['lead_name'] ?? ''));
        if ($current === $name) {
            return $name;
        }

        $meta['lead_name'] = $name;
        $meta['display_name'] = $name;
        $this->forceFill(['meta' => $meta]);
        try {
            $this->saveQuietly();
        } catch (\Throwable) {
            // ignore persistence failures during rendering
        }

        return $name;
    }

    private function pickAvitoLeadNameFromChat(array $chat, string $ownerUserId): ?string
    {
        foreach ([
            data_get($chat, 'buyer.name'),
            data_get($chat, 'user.name'),
            data_get($chat, 'client.name'),
        ] as $candidate) {
            $candidate = is_scalar($candidate) ? trim((string) $candidate) : '';
            if ($this->looksLikeHumanName($candidate)) {
                return $candidate;
            }
        }

        $users = data_get($chat, 'users');
        if (is_array($users)) {
            foreach ($users as $user) {
                if (!is_array($user)) {
                    continue;
                }
                $id = (string) ($user['id'] ?? $user['user_id'] ?? '');
                if ($id !== '' && $id === $ownerUserId) {
                    continue;
                }
                foreach ([
                    $user['name'] ?? null,
                    $user['title'] ?? null,
                    trim((string) ($user['first_name'] ?? '').' '.(string) ($user['last_name'] ?? '')),
                ] as $candidate) {
                    $candidate = is_scalar($candidate) ? trim((string) $candidate) : '';
                    if ($this->looksLikeHumanName($candidate)) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    private function extractLeadNameFromPayload(mixed $payload): ?string
    {
        if (!is_array($payload)) {
            return null;
        }

        $directCandidates = [
            data_get($payload, 'author.name'),
            data_get($payload, 'user.name'),
            data_get($payload, 'buyer.name'),
            data_get($payload, 'sender.name'),
            data_get($payload, 'client.name'),
            data_get($payload, 'from.first_name'),
            data_get($payload, 'from.last_name'),
            data_get($payload, 'message.author.name'),
            data_get($payload, 'message.sender.name'),
            data_get($payload, 'message.user.name'),
            data_get($payload, 'message.client.name'),
            data_get($payload, 'chat.user.name'),
            data_get($payload, 'chat.buyer.name'),
            data_get($payload, 'chat.client.name'),
        ];

        $firstName = trim((string) data_get($payload, 'from.first_name'));
        $lastName = trim((string) data_get($payload, 'from.last_name'));
        $fullName = trim($firstName.' '.$lastName);
        if ($fullName !== '') {
            $directCandidates[] = $fullName;
        }

        foreach ($directCandidates as $candidate) {
            $candidate = is_scalar($candidate) ? trim((string) $candidate) : '';
            if ($this->looksLikeHumanName($candidate)) {
                return $candidate;
            }
        }

        foreach ($this->collectHumanNames($payload) as $candidate) {
            if ($this->looksLikeHumanName($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function collectHumanNames(array $payload): array
    {
        $found = [];
        $walker = function (mixed $node) use (&$walker, &$found) {
            if (!is_array($node)) {
                return;
            }

            $first = trim((string) ($node['first_name'] ?? ''));
            $last = trim((string) ($node['last_name'] ?? ''));
            $full = trim($first.' '.$last);
            if ($full !== '' && $this->looksLikeHumanName($full)) {
                $found[] = $full;
            }

            $name = trim((string) ($node['name'] ?? ''));
            if ($name !== '' && $this->looksLikeHumanName($name)) {
                $found[] = $name;
            }

            foreach ($node as $value) {
                if (is_array($value)) {
                    $walker($value);
                }
            }
        };

        $walker($payload);

        return array_values(array_unique($found));
    }

    private function extractSourceHintFromPayload(mixed $payload): ?string
    {
        if (!is_array($payload)) {
            return null;
        }

        foreach ([
            data_get($payload, 'chat.context.value.title'),
            data_get($payload, 'chat.context.value.name'),
            data_get($payload, 'chat.item.title'),
            data_get($payload, 'chat.ad.title'),
            data_get($payload, 'item.title'),
            data_get($payload, 'ad.title'),
        ] as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value !== '' && !$this->looksLikeHumanName($value)) {
                return Str::limit($value, 80);
            }
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

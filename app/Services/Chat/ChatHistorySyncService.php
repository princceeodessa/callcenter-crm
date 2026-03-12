<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\IntegrationConnection;
use App\Models\Message;
use App\Services\Integrations\AvitoApiClient;
use App\Services\Integrations\AvitoTokenManager;
use App\Services\Integrations\VkApiClient;
use Carbon\Carbon;

class ChatHistorySyncService
{
    public function syncFull(Conversation $conversation, int $accountId): void
    {
        $this->sync($conversation, $accountId, true);
    }

    public function syncRecent(Conversation $conversation, int $accountId): void
    {
        $this->sync($conversation, $accountId, false);
    }

    private function sync(Conversation $conversation, int $accountId, bool $fullSync): void
    {
        if ($conversation->channel === 'avito') {
            $this->syncAvito($conversation, $accountId, $fullSync ? 20 : 2, $fullSync ? 100 : 50);
            return;
        }

        if ($conversation->channel === 'vk') {
            $this->syncVk($conversation, $accountId, $fullSync ? 15 : 2, $fullSync ? 200 : 100);
        }
    }

    private function syncAvito(Conversation $conversation, int $accountId, int $maxPages, int $pageLimit): void
    {
        $connection = IntegrationConnection::query()
            ->where('account_id', $accountId)
            ->where('provider', 'avito')
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (!$connection) {
            return;
        }

        try {
            $token = app(AvitoTokenManager::class)->getValidToken($connection);
        } catch (\Throwable) {
            return;
        }

        $settings = is_array($connection->fresh()?->settings) ? $connection->fresh()->settings : [];
        $userId = trim((string) ($settings['user_id'] ?? ''));
        $chatId = trim((string) $conversation->external_id);

        if ($token === '' || $userId === '' || $chatId === '') {
            return;
        }

        try {
            $client = new AvitoApiClient($token);
            $latestMessageAt = $conversation->last_message_at;
            $offset = 0;
            $previousSignature = null;

            for ($page = 0; $page < $maxPages; $page++) {
                $messages = array_values(array_filter(
                    $client->listMessages($userId, $chatId, max(1, min($pageLimit, 100)), $offset),
                    fn ($item) => is_array($item)
                ));

                if (empty($messages)) {
                    break;
                }

                $signature = implode('|', array_map(
                    fn (array $message) => (string) ($message['id'] ?? $message['message_id'] ?? ''),
                    $messages
                ));

                if ($signature !== '' && $signature === $previousSignature) {
                    break;
                }
                $previousSignature = $signature;

                usort($messages, function (array $left, array $right) {
                    $leftTime = $this->resolveAvitoSentAt($left)->getTimestamp();
                    $rightTime = $this->resolveAvitoSentAt($right)->getTimestamp();

                    if ($leftTime === $rightTime) {
                        return strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
                    }

                    return $leftTime <=> $rightTime;
                });

                foreach ($messages as $message) {
                    $synced = $this->upsertAvitoMessage($conversation, $message, $userId);
                    if ($synced && ($latestMessageAt === null || $synced->created_at?->gt($latestMessageAt))) {
                        $latestMessageAt = $synced->created_at;
                    }
                }

                if (count($messages) < $pageLimit) {
                    break;
                }

                $offset += $pageLimit;
            }

            $this->syncConversationTimestamp($conversation, $latestMessageAt);
        } catch (\Throwable) {
            // Best-effort sync only.
        }
    }

    private function syncVk(Conversation $conversation, int $accountId, int $maxPages, int $pageLimit): void
    {
        $connection = IntegrationConnection::query()
            ->where('account_id', $accountId)
            ->where('provider', 'vk')
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (!$connection) {
            return;
        }

        $token = trim((string) ($connection->settings['access_token'] ?? ''));
        $peerId = trim((string) $conversation->external_id);

        if ($token === '' || $peerId === '') {
            return;
        }

        try {
            $client = new VkApiClient($token);
            $latestMessageAt = $conversation->last_message_at;
            $offset = 0;
            $previousSignature = null;

            for ($page = 0; $page < $maxPages; $page++) {
                $response = $client->getHistory($peerId, min($pageLimit, 200), $offset);
                $items = array_values(array_filter((array) data_get($response, 'response.items', []), fn ($item) => is_array($item)));

                if (empty($items)) {
                    break;
                }

                $signature = implode('|', array_map(
                    fn (array $message) => (string) ($message['id'] ?? $message['conversation_message_id'] ?? ''),
                    $items
                ));

                if ($signature !== '' && $signature === $previousSignature) {
                    break;
                }
                $previousSignature = $signature;

                usort($items, function (array $left, array $right) {
                    $leftTime = (int) ($left['date'] ?? 0);
                    $rightTime = (int) ($right['date'] ?? 0);

                    if ($leftTime === $rightTime) {
                        return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
                    }

                    return $leftTime <=> $rightTime;
                });

                foreach ($items as $message) {
                    $synced = $this->upsertVkMessage($conversation, $message);
                    if ($synced && ($latestMessageAt === null || $synced->created_at?->gt($latestMessageAt))) {
                        $latestMessageAt = $synced->created_at;
                    }
                }

                if (count($items) < $pageLimit) {
                    break;
                }

                $offset += $pageLimit;
            }

            $this->syncConversationTimestamp($conversation, $latestMessageAt);
        } catch (\Throwable) {
            // Best-effort sync only.
        }
    }

    private function upsertAvitoMessage(Conversation $conversation, array $message, string $ownerUserId): ?Message
    {
        $externalId = trim((string) ($message['id'] ?? $message['message_id'] ?? ''));
        if ($externalId === '') {
            return null;
        }

        $direction = $this->isOutgoingAvitoMessage($message, $ownerUserId) ? 'out' : 'in';
        $authorId = trim((string) ($message['author_id'] ?? $message['authorId'] ?? ''));
        $leadName = $this->resolveAvitoAuthorName($message);
        $author = $direction === 'out'
            ? 'user:avito'
            : ($leadName ?: ($authorId !== '' ? 'avito:'.$authorId : 'avito'));

        $payload = [
            'history_sync' => true,
            'message' => $message,
        ];

        $media = $this->extractAvitoMedia($message);
        if (!empty($media)) {
            $payload['media'] = $media;
        }

        $body = $this->extractAvitoText($message);
        if ($body === '' && !empty($media)) {
            $body = 'Вложение';
        }

        $saved = $this->saveMessage(
            $conversation,
            $externalId,
            $direction,
            $author,
            $body,
            $payload,
            $this->resolveAvitoSentAt($message),
            $direction === 'out' ? 'sent' : 'ok',
        );

        if ($direction === 'in') {
            $this->syncLeadName($conversation, $leadName);
        }

        return $saved;
    }

    private function upsertVkMessage(Conversation $conversation, array $message): ?Message
    {
        $externalId = trim((string) ($message['id'] ?? $message['conversation_message_id'] ?? ''));
        if ($externalId === '') {
            return null;
        }

        $direction = ((int) ($message['out'] ?? 0) === 1) ? 'out' : 'in';
        $meta = is_array($conversation->meta) ? $conversation->meta : [];
        $leadName = $this->cleanLeadName((string) ($meta['lead_name'] ?? $meta['display_name'] ?? ''));
        $author = $direction === 'out'
            ? 'user:vk'
            : ($leadName ?: ('vk:'.trim((string) ($message['from_id'] ?? ''))));

        $payload = [
            'history_sync' => true,
            'message' => $message,
        ];

        $media = $this->extractVkMedia($message);
        if (!empty($media)) {
            $payload['media'] = $media;
        }

        $body = trim((string) ($message['text'] ?? ''));
        if ($body === '' && !empty($media)) {
            $body = 'Вложение';
        }

        $sentAt = isset($message['date']) && (int) $message['date'] > 0
            ? Carbon::createFromTimestamp((int) $message['date'])
            : now();

        return $this->saveMessage(
            $conversation,
            $externalId,
            $direction,
            $author,
            $body,
            $payload,
            $sentAt,
            $direction === 'out' ? 'sent' : 'ok',
        );
    }

    private function saveMessage(
        Conversation $conversation,
        string $externalId,
        string $direction,
        string $author,
        string $body,
        array $payload,
        Carbon $sentAt,
        string $status
    ): Message {
        $message = Message::query()->firstOrNew([
            'conversation_id' => $conversation->id,
            'external_id' => $externalId,
        ]);

        $existingPayload = is_array($message->payload ?? null) ? $message->payload : [];
        $message->account_id = $conversation->account_id;
        $message->direction = $direction;
        $message->author = $author;
        $message->body = $body;
        $message->payload = array_replace_recursive($existingPayload, $payload);
        $message->status = $message->exists && $message->status ? $message->status : $status;
        $message->error = null;
        $message->save();

        if (!$message->created_at || $message->created_at->timestamp !== $sentAt->timestamp) {
            $message->timestamps = false;
            $message->created_at = $sentAt;
            $message->updated_at = $sentAt;
            $message->save();
            $message->timestamps = true;
        }

        return $message->fresh();
    }

    private function syncConversationTimestamp(Conversation $conversation, ?Carbon $latestMessageAt): void
    {
        if (!$latestMessageAt) {
            return;
        }

        if ($conversation->last_message_at === null || $latestMessageAt->gt($conversation->last_message_at)) {
            $conversation->last_message_at = $latestMessageAt;
            $conversation->save();
        }
    }

    private function syncLeadName(Conversation $conversation, ?string $leadName): void
    {
        $leadName = $this->cleanLeadName($leadName);
        if (!$leadName) {
            return;
        }

        $meta = is_array($conversation->meta) ? $conversation->meta : [];
        $current = $this->cleanLeadName((string) ($meta['lead_name'] ?? $meta['display_name'] ?? ''));

        if ($current !== null) {
            return;
        }

        $meta['lead_name'] = $leadName;
        $meta['display_name'] = $leadName;
        $conversation->meta = $meta;
        $conversation->save();
    }

    private function isOutgoingAvitoMessage(array $message, string $ownerUserId): bool
    {
        $direction = strtolower(trim((string) ($message['direction'] ?? '')));
        $authorId = trim((string) ($message['author_id'] ?? $message['authorId'] ?? ''));

        return match ($direction) {
            'out', 'outgoing' => true,
            'in', 'incoming' => false,
            default => $authorId !== '' && $authorId === $ownerUserId,
        };
    }

    private function extractAvitoText(array $message): string
    {
        foreach ([
            $message['text'] ?? null,
            data_get($message, 'content.text'),
            data_get($message, 'message.text'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }

    private function resolveAvitoAuthorName(array $message): ?string
    {
        foreach ([
            data_get($message, 'author.name'),
            data_get($message, 'user.name'),
            data_get($message, 'buyer.name'),
            data_get($message, 'sender.name'),
        ] as $candidate) {
            $clean = $this->cleanLeadName(is_scalar($candidate) ? (string) $candidate : null);
            if ($clean) {
                return $clean;
            }
        }

        foreach ($this->collectHumanNames($message) as $candidate) {
            $clean = $this->cleanLeadName($candidate);
            if ($clean) {
                return $clean;
            }
        }

        return null;
    }

    private function resolveAvitoSentAt(array $message): Carbon
    {
        foreach (['created', 'created_at', 'timestamp', 'time', 'date'] as $key) {
            $value = $message[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            try {
                if (is_numeric($value)) {
                    $raw = (int) $value;
                    if ($raw > 9999999999) {
                        $raw = (int) floor($raw / 1000);
                    }
                    if ($raw > 0) {
                        return Carbon::createFromTimestamp($raw);
                    }
                }

                if (is_string($value)) {
                    return Carbon::parse($value);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return now();
    }

    private function extractAvitoMedia(array $message): array
    {
        $media = [];
        $attachments = data_get($message, 'attachments');
        if (!is_array($attachments)) {
            return $media;
        }

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $url = $attachment['url'] ?? data_get($attachment, 'image.url') ?? data_get($attachment, 'video.url');
            if (is_string($url) && $url !== '') {
                $media[] = [
                    'type' => $attachment['type'] ?? 'file',
                    'url' => $url,
                ];
            }
        }

        return $media;
    }

    private function extractVkMedia(array $message): array
    {
        $media = [];
        $attachments = is_array($message['attachments'] ?? null) ? $message['attachments'] : [];

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $type = (string) ($attachment['type'] ?? '');
            if ($type === 'photo' && is_array($attachment['photo'] ?? null)) {
                $sizes = is_array($attachment['photo']['sizes'] ?? null) ? $attachment['photo']['sizes'] : [];
                if (!empty($sizes)) {
                    usort($sizes, fn ($left, $right) => ((int) ($right['width'] ?? 0) * (int) ($right['height'] ?? 0)) <=> ((int) ($left['width'] ?? 0) * (int) ($left['height'] ?? 0)));
                    $url = $sizes[0]['url'] ?? null;
                    if (is_string($url) && $url !== '') {
                        $media[] = ['type' => 'photo', 'url' => $url];
                    }
                }
            }

            if ($type === 'doc' && is_array($attachment['doc'] ?? null)) {
                $url = $attachment['doc']['url'] ?? null;
                if (is_string($url) && $url !== '') {
                    $media[] = [
                        'type' => 'document',
                        'url' => $url,
                        'file_name' => $attachment['doc']['title'] ?? null,
                    ];
                }
            }

            if ($type === 'video' && is_array($attachment['video'] ?? null)) {
                $url = $attachment['video']['player'] ?? null;
                if (is_string($url) && $url !== '') {
                    $media[] = ['type' => 'video', 'url' => $url];
                }
            }
        }

        return $media;
    }

    private function collectHumanNames(array $payload): array
    {
        $names = [];

        $walk = function (mixed $node) use (&$walk, &$names) {
            if (!is_array($node)) {
                return;
            }

            $first = trim((string) ($node['first_name'] ?? ''));
            $last = trim((string) ($node['last_name'] ?? ''));
            $full = trim($first.' '.$last);
            if ($full !== '') {
                $names[] = $full;
            }

            $name = trim((string) ($node['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }

            foreach ($node as $value) {
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };

        $walk($payload);

        return array_values(array_unique(array_filter($names)));
    }

    private function cleanLeadName(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $lower = mb_strtolower($value);
        foreach (['vk:', 'tg:', 'avito:', 'telegram:', 'user ', 'id ', 'chat ', 'peer '] as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return null;
            }
        }

        if (preg_match('/^\d+$/', $value)) {
            return null;
        }

        return preg_match('/[\p{L}]{2,}/u', $value) === 1 ? $value : null;
    }
}

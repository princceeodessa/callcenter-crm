<?php

namespace App\Services\Chat;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\IntegrationConnection;
use App\Models\Message;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\Integrations\VkApiClient;
use Carbon\Carbon;

class ChatIngestService
{
    public function ingestFromTelegram(IntegrationConnection $connection, array $payload): ?Message
    {
        $msg = $payload['message'] ?? null;
        if (!is_array($msg)) {
            return null;
        }

        $chatId = $msg['chat']['id'] ?? null;
        if (!is_scalar($chatId)) {
            return null;
        }

        $messageId = $msg['message_id'] ?? ($payload['update_id'] ?? null);
        $from = is_array($msg['from'] ?? null) ? $msg['from'] : [];
        $author = trim((string)($from['first_name'] ?? '').' '.(string)($from['last_name'] ?? ''));
        $author = $author !== '' ? $author : (is_scalar($from['id'] ?? null) ? 'tg:'.$from['id'] : 'tg');
        $leadName = $this->cleanLeadName($author);

        $text = $msg['text'] ?? null;
        if (!is_string($text)) {
            $text = $msg['caption'] ?? null;
        }
        $text = is_string($text) ? $text : '';

        $media = [];
        if (isset($msg['photo']) && is_array($msg['photo']) && count($msg['photo']) > 0) {
            $ph = $msg['photo'];
            $last = end($ph);
            $fileId = is_array($last) ? ($last['file_id'] ?? null) : null;
            if (is_string($fileId) && $fileId !== '') {
                $media[] = ['type' => 'photo', 'file_id' => $fileId];
                if ($text === '') {
                    $text = '📷 Фото';
                }
            }
        }
        if (isset($msg['video']) && is_array($msg['video'])) {
            $fileId = $msg['video']['file_id'] ?? null;
            if (is_string($fileId) && $fileId !== '') {
                $media[] = ['type' => 'video', 'file_id' => $fileId, 'mime' => 'video/mp4'];
                if ($text === '') {
                    $text = '🎞 Видео';
                }
            }
        }
        if (isset($msg['document']) && is_array($msg['document'])) {
            $fileId = $msg['document']['file_id'] ?? null;
            if (is_string($fileId) && $fileId !== '') {
                $media[] = [
                    'type' => 'document',
                    'file_id' => $fileId,
                    'mime' => $msg['document']['mime_type'] ?? null,
                    'file_name' => $msg['document']['file_name'] ?? null,
                ];
                if ($text === '') {
                    $text = '📎 Файл';
                }
            }
        }

        if (!empty($media)) {
            $payload['media'] = $media;
        }
        $sentAt = isset($msg['date']) ? Carbon::createFromTimestamp((int)$msg['date']) : now();

        return $this->ingestGeneric(
            accountId: $connection->account_id,
            provider: 'telegram',
            externalConversationId: (string)$chatId,
            externalMessageId: is_scalar($messageId) ? (string)$messageId : null,
            author: $author,
            body: $text,
            payload: $payload,
            sentAt: $sentAt,
            leadName: $leadName,
        );
    }

    public function ingestFromVk(IntegrationConnection $connection, array $payload): ?Message
    {
        if (($payload['type'] ?? null) !== 'message_new') {
            return null;
        }
        $msg = $payload['object']['message'] ?? null;
        if (!is_array($msg)) {
            return null;
        }

        $peerId = $msg['peer_id'] ?? null;
        if (!is_scalar($peerId)) {
            return null;
        }

        $externalMessageId = $msg['conversation_message_id'] ?? ($msg['id'] ?? null);
        $fromId = $msg['from_id'] ?? null;
        $author = is_scalar($fromId) ? 'vk:'.$fromId : 'vk';
        $leadName = $this->extractVkLeadName($connection, $payload, $msg);
        if ($leadName) {
            $author = $leadName;
        }

        $text = $msg['text'] ?? null;
        $text = is_string($text) ? $text : '';

        $media = [];
        $attachments = is_array($msg['attachments'] ?? null) ? $msg['attachments'] : [];
        foreach ($attachments as $a) {
            if (!is_array($a)) {
                continue;
            }
            $type = (string) ($a['type'] ?? '');
            if ($type === 'photo' && is_array($a['photo'] ?? null)) {
                $sizes = is_array($a['photo']['sizes'] ?? null) ? $a['photo']['sizes'] : [];
                $url = null;
                if (!empty($sizes)) {
                    usort($sizes, fn($x, $y) => ((int)($y['width'] ?? 0) * (int)($y['height'] ?? 0)) <=> ((int)($x['width'] ?? 0) * (int)($x['height'] ?? 0)));
                    $url = $sizes[0]['url'] ?? null;
                }
                if (is_string($url) && $url !== '') {
                    $media[] = ['type' => 'photo', 'url' => $url];
                    if ($text === '') {
                        $text = '📷 Фото';
                    }
                }
            }
            if ($type === 'doc' && is_array($a['doc'] ?? null)) {
                $url = $a['doc']['url'] ?? null;
                if (is_string($url) && $url !== '') {
                    $media[] = ['type' => 'document', 'url' => $url, 'title' => $a['doc']['title'] ?? null];
                    if ($text === '') {
                        $text = '📎 Файл';
                    }
                }
            }
            if ($type === 'video' && is_array($a['video'] ?? null)) {
                $player = $a['video']['player'] ?? null;
                if (is_string($player) && $player !== '') {
                    $media[] = ['type' => 'video', 'url' => $player];
                    if ($text === '') {
                        $text = '🎞 Видео';
                    }
                }
            }
        }
        if (!empty($media)) {
            $payload['media'] = $media;
        }
        $sentAt = isset($msg['date']) ? Carbon::createFromTimestamp((int)$msg['date']) : now();

        return $this->ingestGeneric(
            accountId: $connection->account_id,
            provider: 'vk',
            externalConversationId: (string)$peerId,
            externalMessageId: is_scalar($externalMessageId) ? (string)$externalMessageId : null,
            author: $author,
            body: $text,
            payload: $payload,
            sentAt: $sentAt,
            leadName: $leadName,
        );
    }

    public function ingestFromAvito(IntegrationConnection $connection, array $payload): ?Message
    {
        $chatId = $payload['chat_id']
            ?? ($payload['chatId'] ?? data_get($payload, 'chat.id'))
            ?? data_get($payload, 'chat.chat_id');
        if (!is_scalar($chatId)) {
            return null;
        }

        $msg = $payload['message'] ?? ($payload['last_message'] ?? null);
        if (!is_array($msg)) {
            $msg = is_array(data_get($payload, 'last_message')) ? data_get($payload, 'last_message') : null;
        }

        $text = $payload['text']
            ?? data_get($msg, 'text')
            ?? data_get($payload, 'message.text')
            ?? data_get($payload, 'content.text');
        $text = is_string($text) ? $text : '';

        $externalMessageId = $payload['id']
            ?? ($payload['message_id'] ?? data_get($msg, 'id'))
            ?? data_get($payload, 'message.id');

        $authorId = $payload['author_id']
            ?? ($payload['user_id'] ?? data_get($msg, 'author_id'))
            ?? data_get($payload, 'author.id');
        $author = is_scalar($authorId) ? 'avito:'.$authorId : 'avito';
        $leadName = $this->extractAvitoLeadName($payload, $msg);
        if ($leadName) {
            $author = $leadName;
        }

        $media = [];
        $attachments = data_get($msg, 'attachments') ?? data_get($payload, 'attachments');
        if (is_array($attachments)) {
            foreach ($attachments as $a) {
                if (!is_array($a)) continue;
                $url = $a['url'] ?? data_get($a, 'image.url') ?? data_get($a, 'video.url');
                if (is_string($url) && $url !== '') {
                    $media[] = ['type' => $a['type'] ?? 'file', 'url' => $url];
                }
            }
        }
        if (!empty($media)) {
            $payload['media'] = $media;
            if ($text === '') {
                $text = '📎 Вложение';
            }
        }

        return $this->ingestGeneric(
            accountId: $connection->account_id,
            provider: 'avito',
            externalConversationId: (string)$chatId,
            externalMessageId: is_scalar($externalMessageId) ? (string)$externalMessageId : null,
            author: $author,
            body: $text,
            payload: $payload,
            sentAt: now(),
            leadName: $leadName,
        );
    }

    private function ingestGeneric(
        int $accountId,
        string $provider,
        string $externalConversationId,
        ?string $externalMessageId,
        string $author,
        string $body,
        array $payload,
        Carbon $sentAt,
        ?string $leadName = null,
    ): ?Message {
        $leadName = $this->cleanLeadName($leadName ?? $author);

        $conversation = Conversation::query()
            ->where('account_id', $accountId)
            ->where('channel', $provider)
            ->where('external_id', $externalConversationId)
            ->first();

        if (!$conversation) {
            [$pipeline, $stage] = $this->getDefaultPipelineAndStage($accountId);

            $title = $this->makeDealTitle($provider, $externalConversationId, $leadName, $author, $body);
            $responsibleId = $this->getDefaultResponsibleUserId($accountId);
            $deal = Deal::create([
                'account_id' => $accountId,
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stage->id,
                'title' => $title,
                'title_is_custom' => 0,
                'responsible_user_id' => $responsibleId,
                'is_unread' => true,
            ]);

            $conversation = Conversation::create([
                'account_id' => $accountId,
                'deal_id' => $deal->id,
                'channel' => $provider,
                'external_id' => $externalConversationId,
                'status' => 'open',
                'unread_count' => 0,
                'last_message_at' => null,
                'meta' => $this->conversationMeta($provider, $leadName),
            ]);
        } else {
            $meta = is_array($conversation->meta) ? $conversation->meta : [];
            $mergedMeta = array_merge($meta, $this->conversationMeta($provider, $leadName));
            if ($mergedMeta !== $meta) {
                $conversation->meta = $mergedMeta;
                $conversation->save();
            }
        }

        if ($externalMessageId) {
            $exists = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('external_id', $externalMessageId)
                ->where('direction', 'in')
                ->exists();
            if ($exists) {
                return null;
            }
        }

        $message = Message::create([
            'account_id' => $accountId,
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'author' => $author,
            'body' => $body,
            'external_id' => $externalMessageId,
            'payload' => $payload,
            'status' => 'ok',
        ]);

        $conversation->update([
            'unread_count' => (int)$conversation->unread_count + 1,
            'last_message_at' => $sentAt,
        ]);

        $deal = $conversation->deal;
        if ($deal) {
            $this->syncDealLeadPresentation($deal, $provider, $externalConversationId, $leadName);
            $deal->update(['is_unread' => true]);
        }

        DealActivity::create([
            'account_id' => $accountId,
            'deal_id' => $conversation->deal_id,
            'author_user_id' => null,
            'type' => 'message_in',
            'body' => $body,
            'payload' => [
                'provider' => $provider,
                'author' => $author,
                'lead_name' => $leadName,
                'external_conversation_id' => $externalConversationId,
                'external_message_id' => $externalMessageId,
            ],
        ]);

        return $message;
    }

    private function getDefaultPipelineAndStage(int $accountId): array
    {
        $pipeline = Pipeline::query()
            ->where('account_id', $accountId)
            ->where('is_default', 1)
            ->first();

        if (!$pipeline) {
            $pipeline = Pipeline::query()
                ->where('account_id', $accountId)
                ->orderBy('id')
                ->firstOrFail();
        }

        $stage = PipelineStage::query()
            ->where('account_id', $accountId)
            ->where('pipeline_id', $pipeline->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->firstOrFail();

        return [$pipeline, $stage];
    }

    private function makeDealTitle(string $provider, string $externalConversationId, ?string $leadName, string $author, string $body): string
    {
        $prov = match ($provider) {
            'vk' => 'VK',
            'telegram' => 'Telegram',
            'avito' => 'Avito',
            'megafon_vats' => 'Звонок',
            default => strtoupper($provider),
        };

        if ($leadName) {
            return $leadName.' — '.$prov;
        }

        $preview = trim(preg_replace('/\s+/', ' ', (string)$body));
        if (mb_strlen($preview) > 40) {
            $preview = mb_substr($preview, 0, 40).'…';
        }

        $base = 'Чат '.$prov;
        if ($preview !== '') {
            return $base.' — '.$preview;
        }

        return $base.' #'.$externalConversationId;
    }

    private function getDefaultResponsibleUserId(int $accountId): ?int
    {
        $admin = User::query()
            ->where('account_id', $accountId)
            ->where('is_active', 1)
            ->where('role', 'admin')
            ->orderBy('id')
            ->first();

        if ($admin) {
            return $admin->id;
        }

        $any = User::query()
            ->where('account_id', $accountId)
            ->where('is_active', 1)
            ->orderBy('id')
            ->first();

        return $any?->id;
    }

    private function conversationMeta(string $provider, ?string $leadName): array
    {
        $meta = [
            'created_by' => 'webhook',
            'provider' => $provider,
        ];

        if ($leadName) {
            $meta['lead_name'] = $leadName;
            $meta['display_name'] = $leadName;
        }

        return $meta;
    }

    private function syncDealLeadPresentation(Deal $deal, string $provider, string $externalConversationId, ?string $leadName): void
    {
        $updates = [];
        if (!$deal->title_is_custom) {
            $newTitle = $this->makeDealTitle($provider, $externalConversationId, $leadName, $leadName ?? '', '');
            if ($newTitle !== '' && $deal->title !== $newTitle) {
                $updates['title'] = $newTitle;
            }
        }

        if ($leadName) {
            if ($deal->contact_id) {
                $contact = $deal->contact;
                if ($contact && $this->shouldReplaceDealName($contact->name ?? null)) {
                    $contact->name = $leadName;
                    $contact->save();
                }
            } else {
                $contact = Contact::create([
                    'account_id' => $deal->account_id,
                    'name' => $leadName,
                    'phone' => null,
                    'email' => null,
                ]);
                $updates['contact_id'] = $contact->id;
            }
        }

        if (!empty($updates)) {
            $deal->update($updates);
        }
    }

    private function extractVkLeadName(IntegrationConnection $connection, array $payload, array $msg): ?string
    {
        $profiles = is_array($payload['object']['profiles'] ?? null) ? $payload['object']['profiles'] : [];
        $fromId = $msg['from_id'] ?? null;
        if (is_scalar($fromId)) {
            foreach ($profiles as $profile) {
                if (!is_array($profile)) {
                    continue;
                }
                if ((string) ($profile['id'] ?? '') !== (string) $fromId) {
                    continue;
                }
                $name = trim((string) ($profile['first_name'] ?? '').' '.(string) ($profile['last_name'] ?? ''));
                if ($this->cleanLeadName($name)) {
                    return $this->cleanLeadName($name);
                }
            }
        }

        if (!is_scalar($fromId) || (int) $fromId <= 0) {
            return null;
        }

        $token = trim((string) ($connection->settings['access_token'] ?? ''));
        if ($token === '') {
            return null;
        }

        try {
            $vk = new VkApiClient($token);
            $resp = $vk->usersGet((int) $fromId);
            $user = data_get($resp, 'response.0');
            if (!is_array($user)) {
                return null;
            }
            return $this->cleanLeadName(trim((string) ($user['first_name'] ?? '').' '.(string) ($user['last_name'] ?? '')));
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractAvitoLeadName(array $payload, ?array $msg): ?string
    {
        $candidates = [
            data_get($payload, 'author.name'),
            data_get($payload, 'user.name'),
            data_get($payload, 'buyer.name'),
            data_get($payload, 'sender.name'),
            data_get($payload, 'client.name'),
            data_get($payload, 'chat.user.name'),
            data_get($payload, 'chat.client.name'),
            data_get($payload, 'chat.buyer.name'),
            data_get($payload, 'chat.users.0.name'),
            data_get($payload, 'chat.users.1.name'),
            data_get($payload, 'users.0.name'),
            data_get($payload, 'users.1.name'),
            data_get($msg, 'author.name'),
            data_get($msg, 'sender.name'),
            data_get($msg, 'user.name'),
            data_get($msg, 'buyer.name'),
        ];

        foreach ($candidates as $candidate) {
            $name = $this->cleanLeadName(is_scalar($candidate) ? (string) $candidate : null);
            if ($name) {
                return $name;
            }
        }

        foreach ($this->collectLeadNameCandidates($payload) as $candidate) {
            $name = $this->cleanLeadName($candidate);
            if ($name) {
                return $name;
            }
        }

        if (is_array($msg)) {
            foreach ($this->collectLeadNameCandidates($msg) as $candidate) {
                $name = $this->cleanLeadName($candidate);
                if ($name) {
                    return $name;
                }
            }
        }

        return null;
    }

    private function collectLeadNameCandidates(array $payload): array
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

    private function shouldReplaceDealName(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return true;
        }

        $lower = mb_strtolower($value);
        foreach (['vk:', 'tg:', 'avito:', 'telegram:', 'user ', 'id ', 'chat '] as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return true;
            }
        }

        if (preg_match('/^\d+$/', $value)) {
            return true;
        }

        return preg_match('/[\p{L}]{2,}/u', $value) !== 1;
    }

    private function cleanLeadName(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $lower = mb_strtolower($value);
        foreach (['vk:', 'tg:', 'avito:', 'telegram:', 'user ', 'id ', 'chat '] as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return null;
            }
        }

        if (preg_match('/^\d+$/', $value)) {
            return null;
        }

        if (preg_match('/[\p{L}]{2,}/u', $value) !== 1) {
            return null;
        }

        return $value;
    }
}

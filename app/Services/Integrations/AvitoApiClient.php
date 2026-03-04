<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AvitoApiClient
{
    public function __construct(
        private readonly string $accessToken,
        private readonly string $baseUrl = 'https://api.avito.ru',
        private readonly float $timeoutSeconds = 25.0,
    ) {
    }

    private function http(): PendingRequest
    {
        return Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->withHeaders([
                'Authorization' => 'Bearer '.trim($this->accessToken),
            ]);
    }

    private function pickList(mixed $payload): array
    {
        if (is_array($payload) && array_is_list($payload)) {
            return array_values(array_filter($payload, fn($x) => is_array($x)));
        }
        if (is_array($payload)) {
            foreach (['chats', 'items', 'data', 'result', 'messages'] as $k) {
                if (isset($payload[$k]) && is_array($payload[$k])) {
                    return array_values(array_filter($payload[$k], fn($x) => is_array($x)));
                }
            }
        }
        return [];
    }

    public function listChats(int|string $userId, int $limit = 100, int $offset = 0): array
    {
        $uid = (string)$userId;

        // v2 without params
        foreach ([
            "/messenger/v2/accounts/{$uid}/chats",
            "/messenger/v2/accounts/{$uid}/chats/",
        ] as $path) {
            $r = $this->http()->get($this->baseUrl.$path);
            if ($r->successful()) {
                return $this->pickList($r->json());
            }
        }

        // v1 with pagination
        foreach ([
            "/messenger/v1/accounts/{$uid}/chats",
            "/messenger/v1/accounts/{$uid}/chats/",
        ] as $path) {
            $r = $this->http()->get($this->baseUrl.$path, ['limit' => $limit, 'offset' => $offset]);
            if ($r->successful()) {
                return $this->pickList($r->json());
            }
        }

        // v2 with params as fallback
        foreach ([
            "/messenger/v2/accounts/{$uid}/chats",
            "/messenger/v2/accounts/{$uid}/chats/",
        ] as $path) {
            $r = $this->http()->get($this->baseUrl.$path, ['limit' => $limit, 'offset' => $offset]);
            if ($r->successful()) {
                return $this->pickList($r->json());
            }
        }

        return [];
    }

    public function listMessages(int|string $userId, string $chatId, int $limit = 30, int $offset = 0): array
    {
        $uid = (string)$userId;
        $paths = [
            "/messenger/v2/accounts/{$uid}/chats/{$chatId}/messages/",
            "/messenger/v2/accounts/{$uid}/chats/{$chatId}/messages",
            "/messenger/v1/accounts/{$uid}/chats/{$chatId}/messages/",
            "/messenger/v1/accounts/{$uid}/chats/{$chatId}/messages",
        ];

        foreach ($paths as $path) {
            $r = $this->http()->get($this->baseUrl.$path, ['limit' => $limit, 'offset' => $offset]);
            if ($r->status() === 405) {
                return [];
            }
            if ($r->successful()) {
                return $this->pickList($r->json());
            }
        }

        return [];
    }

    public function markRead(int|string $userId, string $chatId): bool
    {
        $uid = (string)$userId;
        foreach ([
            "/messenger/v1/accounts/{$uid}/chats/{$chatId}/read",
            "/messenger/v1/accounts/{$uid}/chats/{$chatId}/read/",
        ] as $path) {
            $r = $this->http()->post($this->baseUrl.$path);
            if ($r->successful()) {
                return true;
            }
        }
        return false;
    }

    public function sendText(int|string $userId, string $chatId, string $text): array
    {
        $uid = (string)$userId;
        $path = $this->baseUrl."/messenger/v1/accounts/{$uid}/chats/{$chatId}/messages";

        $variants = [
            ['type' => 'text', 'message' => ['text' => $text]],
            ['type' => 'text', 'content' => ['text' => $text]],
            ['text' => $text],
        ];

        $last = null;
        foreach ($variants as $payload) {
            $r = $this->http()->post($path, $payload);
            if ($r->successful()) {
                return $r->json() ?? ['ok' => true];
            }
            $last = $r;
        }

        return [
            'ok' => false,
            'status' => $last?->status(),
            'body' => $last?->body(),
            'request_id' => $last?->header('x-request-id'),
        ];
    }
}

<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class VkApiClient
{
    public function __construct(
        private readonly string $accessToken,
        private readonly string $apiVersion = '5.131',
        private readonly float $timeoutSeconds = 20.0,
    ) {
    }

    private function http(): PendingRequest
    {
        return Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asForm();
    }

    public function call(string $method, array $params = []): array
    {
        $base = 'https://api.vk.com/method/'.ltrim($method, '/');
        $params = array_merge($params, [
            'access_token' => $this->accessToken,
            'v' => $this->apiVersion,
        ]);

        $r = $this->http()->post($base, $params);
        return $r->json() ?? [];
    }

    public function sendMessage(int|string $peerId, string $text, ?int $randomId = null): array
    {
        return $this->call('messages.send', [
            'peer_id' => $peerId,
            'random_id' => $randomId ?? random_int(1, PHP_INT_MAX),
            'message' => $text,
        ]);
    }

    public function getCallbackConfirmationCode(int|string $groupId): array
    {
        return $this->call('groups.getCallbackConfirmationCode', [
            'group_id' => $groupId,
        ]);
    }

    public function addCallbackServer(int|string $groupId, string $url, string $title, string $secretKey): array
    {
        return $this->call('groups.addCallbackServer', [
            'group_id' => $groupId,
            'url' => $url,
            'title' => $title,
            'secret_key' => $secretKey,
        ]);
    }

    public function editCallbackServer(int|string $groupId, int|string $serverId, string $url, string $title, string $secretKey): array
    {
        return $this->call('groups.editCallbackServer', [
            'group_id' => $groupId,
            'server_id' => $serverId,
            'url' => $url,
            'title' => $title,
            'secret_key' => $secretKey,
        ]);
    }

    public function setCallbackSettings(int|string $groupId, array $events, ?int $serverId = null): array
    {
        $params = array_merge(['group_id' => $groupId], $events);
        if ($serverId) {
            $params['server_id'] = $serverId;
        }
        return $this->call('groups.setCallbackSettings', $params);
    }

    public static function makeSecret(): string
    {
        // VK secret_key: 1..64; keep url-safe.
        return Str::random(32);
    }
}

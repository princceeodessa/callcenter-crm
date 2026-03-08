<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

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

    public function usersGet(int|string $userId, array $fields = ['first_name', 'last_name']): array
    {
        return $this->call('users.get', [
            'user_ids' => (string) $userId,
            'fields' => implode(',', $fields),
        ]);
    }

    public function sendMessageWithAttachment(int|string $peerId, string $text, string $attachment, ?int $randomId = null): array
    {
        return $this->call('messages.send', [
            'peer_id' => $peerId,
            'random_id' => $randomId ?? random_int(1, PHP_INT_MAX),
            'message' => $text,
            'attachment' => $attachment,
        ]);
    }

    public function getMessagesPhotoUploadServer(int|string $peerId): array
    {
        return $this->call('photos.getMessagesUploadServer', [
            'peer_id' => $peerId,
        ]);
    }

    public function saveMessagesPhoto(string $photo, int $server, string $hash): array
    {
        return $this->call('photos.saveMessagesPhoto', [
            'photo' => $photo,
            'server' => $server,
            'hash' => $hash,
        ]);
    }

    public function getMessagesDocUploadServer(int|string $peerId, string $type = 'doc'): array
    {
        return $this->call('docs.getMessagesUploadServer', [
            'peer_id' => $peerId,
            'type' => $type,
        ]);
    }

    public function saveDoc(string $file, string $title): array
    {
        return $this->call('docs.save', [
            'file' => $file,
            'title' => $title,
        ]);
    }

    /**
     * Upload a photo and return attachment string (photo{owner_id}_{id}_{access_key?}).
     */
    public function uploadMessagePhoto(int|string $peerId, UploadedFile $file): array
    {
        $serverResp = $this->getMessagesPhotoUploadServer($peerId);
        $uploadUrl = data_get($serverResp, 'response.upload_url');
        if (!is_string($uploadUrl) || $uploadUrl === '') {
            return ['ok' => false, 'error' => $serverResp['error'] ?? 'upload_url_missing', 'raw' => $serverResp];
        }

        $uploadResp = Http::timeout($this->timeoutSeconds)
            ->attach('photo', file_get_contents($file->getRealPath()), $file->getClientOriginalName() ?: 'photo')
            ->post($uploadUrl);
        $u = $uploadResp->json() ?? [];
        $photo = $u['photo'] ?? null;
        $server = $u['server'] ?? null;
        $hash = $u['hash'] ?? null;
        if (!is_string($photo) || !is_scalar($server) || !is_string($hash)) {
            return ['ok' => false, 'error' => 'photo_upload_failed', 'raw' => $u];
        }

        $saved = $this->saveMessagesPhoto($photo, (int)$server, $hash);
        $p0 = data_get($saved, 'response.0');
        if (!is_array($p0)) {
            return ['ok' => false, 'error' => $saved['error'] ?? 'photo_save_failed', 'raw' => $saved];
        }
        $ownerId = $p0['owner_id'] ?? null;
        $id = $p0['id'] ?? null;
        $accessKey = $p0['access_key'] ?? null;
        if (!is_scalar($ownerId) || !is_scalar($id)) {
            return ['ok' => false, 'error' => 'photo_ids_missing', 'raw' => $p0];
        }
        $att = 'photo'.$ownerId.'_'.$id.(is_string($accessKey) && $accessKey !== '' ? '_'.$accessKey : '');
        return ['ok' => true, 'attachment' => $att, 'raw' => $saved];
    }

    /**
     * Upload a document (any file) and return attachment string (doc{owner_id}_{id}_{access_key?}).
     */
    public function uploadMessageDoc(int|string $peerId, UploadedFile $file): array
    {
        $serverResp = $this->getMessagesDocUploadServer($peerId, 'doc');
        $uploadUrl = data_get($serverResp, 'response.upload_url');
        if (!is_string($uploadUrl) || $uploadUrl === '') {
            return ['ok' => false, 'error' => $serverResp['error'] ?? 'upload_url_missing', 'raw' => $serverResp];
        }

        $uploadResp = Http::timeout($this->timeoutSeconds)
            ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName() ?: 'file')
            ->post($uploadUrl);
        $u = $uploadResp->json() ?? [];
        $vkFile = $u['file'] ?? null;
        if (!is_string($vkFile) || $vkFile === '') {
            return ['ok' => false, 'error' => 'doc_upload_failed', 'raw' => $u];
        }

        $title = $file->getClientOriginalName() ?: 'file';
        $saved = $this->saveDoc($vkFile, $title);
        // docs.save returns {response:{doc:{...}}} OR {response:[{...}]}
        $doc = data_get($saved, 'response.doc');
        if (!is_array($doc)) {
            $doc = data_get($saved, 'response.0');
        }
        if (!is_array($doc)) {
            return ['ok' => false, 'error' => $saved['error'] ?? 'doc_save_failed', 'raw' => $saved];
        }
        $ownerId = $doc['owner_id'] ?? null;
        $id = $doc['id'] ?? null;
        $accessKey = $doc['access_key'] ?? null;
        if (!is_scalar($ownerId) || !is_scalar($id)) {
            return ['ok' => false, 'error' => 'doc_ids_missing', 'raw' => $doc];
        }
        $att = 'doc'.$ownerId.'_'.$id.(is_string($accessKey) && $accessKey !== '' ? '_'.$accessKey : '');
        return ['ok' => true, 'attachment' => $att, 'raw' => $saved];
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

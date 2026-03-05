<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class TelegramApiClient
{
    public function __construct(
        private readonly string $botToken,
        private readonly float $timeoutSeconds = 20.0,
    ) {
    }

    private function http(): PendingRequest
    {
        return Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson();
    }

    private function httpMultipart(): PendingRequest
    {
        return Http::timeout($this->timeoutSeconds)
            ->acceptJson();
    }

    private function url(string $method): string
    {
        $token = trim($this->botToken);
        return "https://api.telegram.org/bot{$token}/".ltrim($method, '/');
    }

    public function fileUrl(string $filePath): string
    {
        $token = trim($this->botToken);
        return "https://api.telegram.org/file/bot{$token}/".ltrim($filePath, '/');
    }

    public function getMe(): array
    {
        $r = $this->http()->get($this->url('getMe'));
        return $r->json() ?? [];
    }

    public function setWebhook(string $webhookUrl, ?string $secretToken = null): array
    {
        $payload = ['url' => $webhookUrl];
        if ($secretToken) {
            $payload['secret_token'] = $secretToken;
        }
        $r = $this->http()->post($this->url('setWebhook'), $payload);
        return $r->json() ?? [];
    }

    public function deleteWebhook(): array
    {
        $r = $this->http()->post($this->url('deleteWebhook'), []);
        return $r->json() ?? [];
    }

    public function sendMessage(int|string $chatId, string $text, ?string $parseMode = null): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];
        if ($parseMode) {
            $payload['parse_mode'] = $parseMode;
        }

        $r = $this->http()->post($this->url('sendMessage'), $payload);
        return $r->json() ?? [];
    }

    public function getFile(string $fileId): array
    {
        $r = $this->http()->get($this->url('getFile'), ['file_id' => $fileId]);
        return $r->json() ?? [];
    }

    public function sendPhoto(int|string $chatId, UploadedFile $file, ?string $caption = null): array
    {
        $payload = ['chat_id' => $chatId];
        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }
        $r = $this->httpMultipart()
            ->attach('photo', file_get_contents($file->getRealPath()), $file->getClientOriginalName() ?: 'photo')
            ->post($this->url('sendPhoto'), $payload);
        return $r->json() ?? [];
    }

    public function sendVideo(int|string $chatId, UploadedFile $file, ?string $caption = null): array
    {
        $payload = ['chat_id' => $chatId];
        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }
        $r = $this->httpMultipart()
            ->attach('video', file_get_contents($file->getRealPath()), $file->getClientOriginalName() ?: 'video')
            ->post($this->url('sendVideo'), $payload);
        return $r->json() ?? [];
    }

    public function sendDocument(int|string $chatId, UploadedFile $file, ?string $caption = null): array
    {
        $payload = ['chat_id' => $chatId];
        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }
        $r = $this->httpMultipart()
            ->attach('document', file_get_contents($file->getRealPath()), $file->getClientOriginalName() ?: 'file')
            ->post($this->url('sendDocument'), $payload);
        return $r->json() ?? [];
    }

    public static function makeSecretToken(): string
    {
        // Telegram requires 1..256 chars; keep it url-safe.
        return Str::random(48);
    }
}

<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BitrixApiClient
{
    public function __construct(
        private readonly string $webhookUrl,
        private readonly float $timeoutSeconds = 20.0,
    ) {
    }

    public function addActivity(array $fields): array
    {
        return $this->call('crm.activity.add', [
            'fields' => $fields,
        ]);
    }

    public function updateActivity(int|string $activityId, array $fields): array
    {
        return $this->call('crm.activity.update', [
            'id' => $activityId,
            'fields' => $fields,
        ]);
    }

    public function call(string $method, array $params = []): array
    {
        $response = $this->http()->post($this->endpoint($method), $params);

        if (! $response->successful()) {
            throw new RuntimeException('Bitrix HTTP '.$response->status().': '.trim($response->body()));
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Bitrix вернул неожиданный ответ.');
        }

        if (! empty($payload['error'])) {
            $message = trim((string) ($payload['error_description'] ?? $payload['error'] ?? 'Bitrix API error'));
            throw new RuntimeException($message);
        }

        return $payload;
    }

    private function http(): PendingRequest
    {
        return Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asForm();
    }

    private function endpoint(string $method): string
    {
        $base = trim($this->webhookUrl);
        if ($base === '') {
            throw new RuntimeException('Не задан webhook URL Bitrix.');
        }

        $base = rtrim($base, '/');
        if (str_ends_with(strtolower($base), '.json')) {
            $pos = strrpos($base, '/');
            $base = $pos === false ? $base : substr($base, 0, $pos);
        }

        return $base.'/'.ltrim($method, '/').'.json';
    }
}
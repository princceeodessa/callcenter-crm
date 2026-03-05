<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AvitoOAuthService
{
    public function __construct(
        private readonly string $baseUrl = 'https://api.avito.ru',
        private readonly float $timeoutSeconds = 25.0,
    ) {
    }

    private function http(): PendingRequest
    {
        return Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asForm();
    }

    /**
     * Exchange authorization code for access token.
     * Avito OAuth endpoints: https://avito.ru/oauth (authorize) and https://api.avito.ru/token (token).
     */
    public function exchangeCode(string $clientId, string $clientSecret, string $code, string $redirectUri): array
    {
        $payload = [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];

        $r = $this->http()->post($this->baseUrl.'/token', $payload);
        return $r->json() ?? ['ok' => false, 'status' => $r->status(), 'body' => $r->body()];
    }

    public function refreshToken(string $clientId, string $clientSecret, string $refreshToken): array
    {
        $payload = [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
        ];

        $r = $this->http()->post($this->baseUrl.'/token', $payload);
        return $r->json() ?? ['ok' => false, 'status' => $r->status(), 'body' => $r->body()];
    }
}

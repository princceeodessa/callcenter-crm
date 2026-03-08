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

        return $this->requestToken($payload, $clientId, $clientSecret);
    }

    public function refreshToken(string $clientId, string $clientSecret, string $refreshToken): array
    {
        $payload = [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
        ];

        return $this->requestToken($payload, $clientId, $clientSecret);
    }

    private function requestToken(array $payload, string $clientId, string $clientSecret): array
    {
        $paths = ['/token', '/oauth/token'];

        foreach ($paths as $path) {
            $r = $this->http()->post($this->baseUrl.$path, $payload);
            $json = $r->json();
            if (is_array($json) && !empty($json['access_token'])) {
                return $json;
            }

            $basicPayload = $payload;
            unset($basicPayload['client_id'], $basicPayload['client_secret']);
            $r2 = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post($this->baseUrl.$path, $basicPayload);
            $json2 = $r2->json();
            if (is_array($json2) && !empty($json2['access_token'])) {
                return $json2;
            }

            if ($r->status() < 400 && $r2->status() < 400) {
                return $json2 ?? $json ?? ['ok' => false];
            }
        }

        return ['ok' => false, 'error' => 'token_exchange_failed'];
    }
}

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
            ->acceptJson();
    }

    public function exchangeCode(string $clientId, string $clientSecret, string $code, string $redirectUri): array
    {
        return $this->requestTokenWithBasicAuth([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ], $clientId, $clientSecret);
    }

    public function refreshToken(string $clientId, string $clientSecret, string $refreshToken): array
    {
        return $this->requestTokenWithBasicAuth([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ], $clientId, $clientSecret);
    }

    public function clientCredentials(string $clientId, string $clientSecret): array
    {
        return $this->requestTokenAsForm([
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);
    }

    private function requestTokenWithBasicAuth(array $payload, string $clientId, string $clientSecret): array
    {
        $paths = ['/token/', '/token', '/oauth/token'];
        $last = null;

        foreach ($paths as $path) {
            $response = $this->http()
                ->withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post($this->baseUrl.$path, $payload);

            $json = $response->json();
            if (is_array($json) && !empty($json['access_token'])) {
                return $json;
            }

            $last = is_array($json) ? $json : [
                'ok' => false,
                'status' => $response->status(),
                'body' => $response->body(),
                'request_id' => $response->header('x-request-id'),
            ];
        }

        return is_array($last) ? $last : ['ok' => false, 'error' => 'token_exchange_failed'];
    }

    private function requestTokenAsForm(array $payload): array
    {
        $paths = ['/token/', '/token', '/oauth/token'];
        $last = null;

        foreach ($paths as $path) {
            $response = $this->http()
                ->asForm()
                ->post($this->baseUrl.$path, $payload);

            $json = $response->json();
            if (is_array($json) && !empty($json['access_token'])) {
                return $json;
            }

            $last = is_array($json) ? $json : [
                'ok' => false,
                'status' => $response->status(),
                'body' => $response->body(),
                'request_id' => $response->header('x-request-id'),
            ];
        }

        return is_array($last) ? $last : ['ok' => false, 'error' => 'token_exchange_failed'];
    }
}

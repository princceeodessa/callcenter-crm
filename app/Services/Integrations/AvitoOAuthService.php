<?php

namespace App\Services\Integrations;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
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
        return Http::timeout($this->timeoutSeconds);
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
        // Повторяем максимально близко логику рабочего avito_ii:
        // POST https://api.avito.ru/token/ + x-www-form-urlencoded body.
        $payload = [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];

        $variants = [
            fn () => $this->postExactForm($this->baseUrl.'/token/', $payload),
            fn () => $this->postExactForm($this->baseUrl.'/token', $payload),
            fn () => $this->http()->asForm()->post($this->baseUrl.'/token/', $payload),
        ];

        $last = null;
        foreach ($variants as $variant) {
            $response = $variant();
            $decoded = $this->decodeTokenResponse($response, $this->baseUrl.'/token/');
            if (!empty($decoded['access_token'])) {
                return $decoded;
            }
            $last = $decoded;
        }

        return is_array($last) ? $last : ['ok' => false, 'error' => 'token_exchange_failed'];
    }

    private function requestTokenWithBasicAuth(array $payload, string $clientId, string $clientSecret): array
    {
        $variants = [
            fn () => $this->http()->withBasicAuth($clientId, $clientSecret)->asForm()->post($this->baseUrl.'/token/', $payload),
            fn () => $this->http()->withBasicAuth($clientId, $clientSecret)->asForm()->post($this->baseUrl.'/token', $payload),
            fn () => $this->http()->withBasicAuth($clientId, $clientSecret)->asForm()->post($this->baseUrl.'/oauth/token', $payload),
        ];

        $last = null;
        foreach ($variants as $variant) {
            $response = $variant();
            $decoded = $this->decodeTokenResponse($response, $response->effectiveUri()?->value() ?? ($this->baseUrl.'/token/'));
            if (!empty($decoded['access_token'])) {
                return $decoded;
            }
            $last = $decoded;
        }

        return is_array($last) ? $last : ['ok' => false, 'error' => 'token_exchange_failed'];
    }

    private function postExactForm(string $url, array $payload): Response
    {
        return $this->http()
            ->withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])
            ->withBody(http_build_query($payload, '', '&', PHP_QUERY_RFC3986), 'application/x-www-form-urlencoded')
            ->post($url);
    }

    private function decodeTokenResponse(Response $response, string $url): array
    {
        $json = $response->json();
        if (is_array($json) && !empty($json['access_token'])) {
            return $json;
        }

        return [
            'ok' => false,
            'status' => $response->status(),
            'url' => $url,
            'body' => $response->body(),
            'json' => is_array($json) ? $json : null,
            'request_id' => $response->header('x-request-id'),
        ];
    }
}

<?php

namespace App\Services\Integrations;

use App\Models\IntegrationConnection;
use Carbon\Carbon;

class AvitoTokenManager
{
    public function __construct(
        private readonly AvitoOAuthService $oauth,
    ) {
    }

    public function getValidToken(IntegrationConnection $connection, bool $forceRefresh = false): string
    {
        $settings = is_array($connection->settings) ? $connection->settings : [];
        $token = trim((string) ($settings['access_token'] ?? ''));
        $clientId = trim((string) ($settings['client_id'] ?? ''));
        $clientSecret = trim((string) ($settings['client_secret'] ?? ''));
        $refreshToken = trim((string) ($settings['refresh_token'] ?? ''));
        $hasClientCredentials = $clientId !== '' && $clientSecret !== '';

        if (!$forceRefresh && !$this->shouldRefresh($settings, $hasClientCredentials)) {
            if ($token !== '' && trim((string) ($settings['user_id'] ?? '')) === '' && $hasClientCredentials) {
                $this->hydrateUserId($connection, $settings, $token);
            }

            return $token;
        }

        if (!$hasClientCredentials) {
            return $token;
        }

        try {
            if ($refreshToken !== '') {
                $resp = $this->oauth->refreshToken($clientId, $clientSecret, $refreshToken);
                $freshToken = trim((string) ($resp['access_token'] ?? ''));

                if ($freshToken !== '') {
                    return $this->persistFreshToken($connection, $settings, $resp, 'refresh_token');
                }

                $settings['last_setup_error'] = 'Avito refresh error: '.json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $resp = $this->oauth->clientCredentials($clientId, $clientSecret);
            $freshToken = trim((string) ($resp['access_token'] ?? ''));
            if ($freshToken !== '') {
                return $this->persistFreshToken($connection, $settings, $resp, 'client_credentials');
            }

            $settings['last_setup_error'] = 'Avito token error: '.json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $connection->update([
                'settings' => $settings,
                'status' => $this->resolveStatus($settings),
                'last_error' => $settings['last_setup_error'],
            ]);
        } catch (\Throwable $e) {
            $settings['last_setup_error'] = 'Avito token exception: '.$e->getMessage();
            $connection->update([
                'settings' => $settings,
                'status' => $this->resolveStatus($settings),
                'last_error' => $settings['last_setup_error'],
            ]);
        }

        return trim((string) ($settings['access_token'] ?? ''));
    }

    private function shouldRefresh(array $settings, bool $hasClientCredentials): bool
    {
        $token = trim((string) ($settings['access_token'] ?? ''));
        if ($token === '') {
            return $hasClientCredentials;
        }

        $expiresAt = trim((string) ($settings['token_expires_at'] ?? ''));
        if ($expiresAt !== '') {
            try {
                return Carbon::parse($expiresAt)->lessThanOrEqualTo(now()->addMinutes(5));
            } catch (\Throwable) {
                return true;
            }
        }

        if ($hasClientCredentials) {
            return true;
        }

        return false;
    }

    private function persistFreshToken(IntegrationConnection $connection, array $settings, array $resp, string $flow): string
    {
        $token = trim((string) ($resp['access_token'] ?? ''));
        if ($token === '') {
            return '';
        }

        $settings['access_token'] = $token;
        if (!empty($resp['refresh_token'])) {
            $settings['refresh_token'] = (string) $resp['refresh_token'];
        }
        if (!empty($resp['expires_in'])) {
            $settings['token_expires_at'] = now()->addSeconds((int) $resp['expires_in'])->toDateTimeString();
        }
        $settings['last_token_refreshed_at'] = now()->toDateTimeString();
        $settings['last_token_flow'] = $flow;
        unset($settings['last_setup_error']);

        $settings = $this->hydrateUserId($connection, $settings, $token);

        $connection->update([
            'settings' => $settings,
            'status' => $this->resolveStatus($settings),
            'last_error' => null,
        ]);

        return $token;
    }

    private function hydrateUserId(IntegrationConnection $connection, array $settings, string $token): array
    {
        if (trim((string) ($settings['user_id'] ?? '')) !== '') {
            return $settings;
        }

        try {
            $self = (new AvitoApiClient($token))->getSelfAccount();
            $uid = $self['id']
                ?? data_get($self, 'account.id')
                ?? data_get($self, 'result.id')
                ?? data_get($self, 'data.id');

            if (is_scalar($uid) && trim((string) $uid) !== '') {
                $settings['user_id'] = (string) $uid;
            }
        } catch (\Throwable $e) {
            $settings['last_setup_error'] = 'Avito self account error: '.$e->getMessage();
            $connection->update([
                'settings' => $settings,
                'last_error' => $settings['last_setup_error'],
            ]);
        }

        return $settings;
    }

    private function resolveStatus(array $settings): string
    {
        $hasToken = trim((string) ($settings['access_token'] ?? '')) !== '';
        $hasClient = trim((string) ($settings['client_id'] ?? '')) !== ''
            && trim((string) ($settings['client_secret'] ?? '')) !== '';
        $hasUserId = trim((string) ($settings['user_id'] ?? '')) !== '';
        $hasSetupError = trim((string) ($settings['last_setup_error'] ?? '')) !== '';

        if (!$hasToken && !$hasClient) {
            return 'disabled';
        }

        if (!$hasUserId && $hasSetupError) {
            return 'error';
        }

        if (!$hasToken && $hasSetupError) {
            return 'error';
        }

        return 'active';
    }
}

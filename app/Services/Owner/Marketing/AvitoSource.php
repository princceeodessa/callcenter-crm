<?php

namespace App\Services\Owner\Marketing;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Авито (api.avito.ru, client_credentials) — дневные просмотры, контакты и избранное по активным объявлениям
 * кабинета потолков. Перенесено из дашборда БлагоДар (avito.py). Расходов API статистики не даёт — их берём из таблицы
 * заявок («Авито затраты»).
 */
class AvitoSource
{
    private const BASE = 'https://api.avito.ru';
    private const TOKEN_KEY = 'owner_marketing.avito_token';

    private array $meta = [];

    public function __construct(private readonly array $cfg)
    {
    }

    public function enabled(): bool
    {
        return ! empty($this->cfg['client_id']) && ! empty($this->cfg['client_secret']);
    }

    public function meta(): array
    {
        return $this->meta;
    }

    /** @return array<string, array<string, int>> */
    public function daily(Carbon $from, Carbon $to): array
    {
        $account = $this->call('GET', '/core/v1/accounts/self');
        $userId = $account['id'] ?? null;
        if (! $userId) {
            throw new RuntimeException('Авито: не удалось узнать кабинет');
        }
        $items = [];
        for ($page = 1; ; $page++) {
            $resp = $this->call('GET', '/core/v1/items?'.http_build_query(['per_page' => 100, 'page' => $page, 'status' => 'active']));
            $chunk = $resp['resources'] ?? [];
            $items = array_merge($items, $chunk);
            if (count($chunk) < 100) {
                break;
            }
        }
        $this->meta = ['account' => $account['name'] ?? null, 'items' => count($items)];

        $out = [];
        $ids = array_values(array_map(fn ($it) => (int) $it['id'], $items));
        foreach (array_chunk($ids, 200) as $chunk) {
            $resp = $this->call('POST', '/stats/v1/accounts/'.$userId.'/items', [
                'dateFrom' => $from->toDateString(),
                'dateTo' => $to->toDateString(),
                'fields' => ['uniqViews', 'uniqContacts', 'uniqFavorites'],
                'itemIds' => $chunk,
                'periodGrouping' => 'day',
            ]);
            foreach ($resp['result']['items'] ?? [] as $it) {
                foreach ($it['stats'] ?? [] as $d) {
                    $day = (string) ($d['date'] ?? '');
                    if ($day === '') {
                        continue;
                    }
                    $out[$day]['views'] = ($out[$day]['views'] ?? 0) + (int) ($d['uniqViews'] ?? 0);
                    $out[$day]['contacts'] = ($out[$day]['contacts'] ?? 0) + (int) ($d['uniqContacts'] ?? 0);
                    $out[$day]['favorites'] = ($out[$day]['favorites'] ?? 0) + (int) ($d['uniqFavorites'] ?? 0);
                }
            }
        }
        ksort($out);

        return $out;
    }

    private function call(string $method, string $path, ?array $json = null): array
    {
        $headers = ['Authorization' => 'Bearer '.$this->token()];
        if ($json !== null) {
            $headers['Content-Type'] = 'application/json';
        }
        [$status, $text] = Http::withRetry($method, self::BASE.$path, $headers, $json !== null ? json_encode($json) : null);
        if ($status === 401 || $status === 403) {
            Cache::forget(self::TOKEN_KEY);
        }
        if ($status !== 200) {
            throw Http::fail('Авито '.strtok($path, '?'), $status, $text);
        }

        return Http::json($text);
    }

    private function token(): string
    {
        $saved = Cache::get(self::TOKEN_KEY);
        if (is_array($saved) && ($saved['client_id'] ?? null) === $this->cfg['client_id'] && ($saved['expires_at'] ?? 0) > time() + 300) {
            return $saved['access_token'];
        }
        [$status, $text] = Http::withRetry('POST', self::BASE.'/token', ['Content-Type' => 'application/x-www-form-urlencoded'], http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->cfg['client_id'],
            'client_secret' => $this->cfg['client_secret'],
        ]));
        $data = Http::json($text);
        if ($status !== 200 || empty($data['access_token'])) {
            throw new RuntimeException('Авито: токен не выдан (HTTP '.$status.')');
        }
        Cache::put(self::TOKEN_KEY, [
            'client_id' => $this->cfg['client_id'],
            'access_token' => $data['access_token'],
            'expires_at' => time() + (int) ($data['expires_in'] ?? 86400),
        ], (int) ($data['expires_in'] ?? 86400));

        return $data['access_token'];
    }
}

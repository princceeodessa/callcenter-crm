<?php

namespace App\Services\Owner\Marketing;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Авито (api.avito.ru, client_credentials). В одном кабинете объявления трёх направлений — потолки, кондиционеры,
 * ремонт с шумоизоляцией, — поэтому всё считается и целиком, и по направлениям (по названию объявления):
 * - просмотры, контакты, избранное — по каждому объявлению за день (stats/v1, перенесено из дашборда, avito.py);
 * - расход — POST /stats/v2/accounts/{id}/spendings по дням: весь кабинет и по объявлениям каждого направления
 *   (filter.itemIDs). Тариф и прочие списания к объявлениям не привязаны — это «общие» (shared_spend).
 * У stats/v2 лимит — запрос в минуту, поэтому запросы расхода идут с паузой (сбор Авито занимает 3–4 минуты и
 * запускается своим таймером, а не общим планировщиком).
 *
 * Цифры дня: views, contacts, favorites, spend (все списания), spend_presence (размещение и целевые действия),
 * spend_promotion, spend_other (тариф, комиссия и прочее) и по направлениям <dir>_spend, <dir>_views,
 * <dir>_contacts, <dir>_favorites, shared_spend.
 */
class AvitoSource
{
    private const BASE = 'https://api.avito.ru';
    private const TOKEN_KEY = 'owner_marketing.avito_token';

    /** Направления кабинета в порядке проверки названия: первое совпадение решает. */
    public const DIRS = [
        'cond' => ['label' => 'Кондиционеры', 're' => '/кондиц|сплит/iu'],
        'ceilings' => ['label' => 'Потолки', 're' => '/потол/iu'],
        'repair' => ['label' => 'Ремонт и шумоизоляция', 're' => '/ремонт|отделк|шумоизол|звукоизол|тихие\s+стены|штукатур|плитк|электрик|сантех/iu'],
    ];

    /** Пауза между запросами stats/v2 (лимит Авито — запрос в минуту); в тестах 0. */
    public static int $paceSeconds = 61;

    private array $meta = [];
    private ?float $lastV2 = null;

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

    /**
     * Направление объявления по названию: ceilings | cond | repair | other. Только «Предложение услуг» (категория 114):
     * вакансии «Монтажник натяжных потолков» и мебель — не реклама направлений.
     */
    public static function direction(string $title, int $categoryId = 114): string
    {
        if ($categoryId !== 114) {
            return 'other';
        }
        foreach (self::DIRS as $key => $d) {
            if (preg_match($d['re'], $title)) {
                return $key;
            }
        }

        return 'other';
    }

    /** @return array<string, array<string, int|float>> */
    public function daily(Carbon $from, Carbon $to): array
    {
        $account = $this->call('GET', '/core/v1/accounts/self');
        $userId = $account['id'] ?? null;
        if (! $userId) {
            throw new RuntimeException('Авито: не удалось узнать кабинет');
        }

        // все объявления кабинета: снятые и архивные тоже — у них бывают расходы и контакты в окне
        $dirOf = [];
        $counts = [];
        foreach (['active', 'old', 'removed', 'blocked', 'rejected'] as $status) {
            for ($page = 1; ; $page++) {
                $resp = $this->call('GET', '/core/v1/items?'.http_build_query(['per_page' => 100, 'page' => $page, 'status' => $status]));
                $chunk = $resp['resources'] ?? [];
                foreach ($chunk as $it) {
                    $dir = self::direction((string) ($it['title'] ?? ''), (int) ($it['category']['id'] ?? 114));
                    $dirOf[(int) $it['id']] = $dir;
                    if ($status === 'active') {
                        $counts[$dir] = ($counts[$dir] ?? 0) + 1;
                    }
                }
                if (count($chunk) < 100) {
                    break;
                }
            }
        }
        $this->meta = ['account' => $account['name'] ?? null, 'items' => count($dirOf), 'active_by_dir' => $counts];

        $out = [];
        $add = function (string $day, string $key, float|int $v) use (&$out) {
            $out[$day][$key] = ($out[$day][$key] ?? 0) + $v;
        };

        foreach (array_chunk(array_keys($dirOf), 200) as $chunk) {
            $resp = $this->call('POST', '/stats/v1/accounts/'.$userId.'/items', [
                'dateFrom' => $from->toDateString(),
                'dateTo' => $to->toDateString(),
                'fields' => ['uniqViews', 'uniqContacts', 'uniqFavorites'],
                'itemIds' => $chunk,
                'periodGrouping' => 'day',
            ]);
            foreach ($resp['result']['items'] ?? [] as $it) {
                $dir = $dirOf[(int) ($it['itemId'] ?? 0)] ?? 'other';
                foreach ($it['stats'] ?? [] as $d) {
                    $day = (string) ($d['date'] ?? '');
                    if ($day === '') {
                        continue;
                    }
                    foreach (['views' => 'uniqViews', 'contacts' => 'uniqContacts', 'favorites' => 'uniqFavorites'] as $k => $f) {
                        $v = (int) ($d[$f] ?? 0);
                        $add($day, $k, $v);
                        $add($day, $dir.'_'.$k, $v);
                    }
                }
            }
        }

        // расход: весь кабинет по видам, затем по объявлениям каждого направления; остаток — общий (тариф и прочее)
        foreach ($this->spendings($userId, $from, $to, null) as $day => $s) {
            foreach ($s as $k => $v) {
                $add($day, $k, $v);
            }
        }
        foreach (array_keys(self::DIRS) as $dir) {
            $ids = array_keys(array_filter($dirOf, fn ($d) => $d === $dir));
            if (! $ids) {
                continue;
            }
            foreach ($this->spendings($userId, $from, $to, $ids) as $day => $s) {
                $add($day, $dir.'_spend', $s['spend']);
            }
        }
        foreach ($out as &$m) {
            if (isset($m['spend'])) {
                $dirs = 0.0;
                foreach (array_keys(self::DIRS) as $dir) {
                    $dirs += $m[$dir.'_spend'] ?? 0;
                }
                $m['shared_spend'] = max(0, $m['spend'] - $dirs);
            }
            foreach ($m as $k => $v) {
                if (is_float($v)) {
                    $m[$k] = round($v, 2);
                }
            }
        }
        unset($m);
        ksort($out);

        return $out;
    }

    /**
     * Расход по дням: весь кабинет ($itemIds = null) или только эти объявления.
     *
     * @return array<string, array<string, float>> день => spend, spend_presence, spend_promotion, spend_other
     */
    private function spendings(int|string $userId, Carbon $from, Carbon $to, ?array $itemIds): array
    {
        $body = [
            'dateFrom' => $from->toDateString(),
            'dateTo' => $to->toDateString(),
            'grouping' => 'day',
            'spendingTypes' => ['all'],
        ];
        if ($itemIds !== null) {
            $body['filter'] = ['itemIDs' => array_values(array_map('intval', $itemIds))];
        }
        if ($this->lastV2 !== null && self::$paceSeconds > 0) {
            $wait = self::$paceSeconds - (microtime(true) - $this->lastV2);
            if ($wait > 0) {
                usleep((int) ($wait * 1_000_000));
            }
        }
        try {
            $resp = $this->call('POST', '/stats/v2/accounts/'.$userId.'/spendings', $body);
        } finally {
            $this->lastV2 = microtime(true);
        }
        $out = [];
        foreach ($resp['result']['groupings'] ?? [] as $g) {
            $day = substr((string) ($g['date'] ?? ''), 0, 10);
            if ($day === '') {
                continue;
            }
            $rec = ['spend' => 0.0, 'spend_presence' => 0.0, 'spend_promotion' => 0.0, 'spend_other' => 0.0];
            foreach ($g['spendings'] ?? [] as $s) {
                $v = (float) ($s['value'] ?? 0);
                $slug = (string) ($s['slug'] ?? '');
                $key = in_array($slug, ['presence', 'promotion'], true) ? 'spend_'.$slug : 'spend_other';
                $rec[$key] += $v;
                $rec['spend'] += $v;
            }
            $out[$day] = $rec;
        }

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

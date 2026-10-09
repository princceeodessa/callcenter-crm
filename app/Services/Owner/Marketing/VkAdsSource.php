<?php

namespace App\Services\Owner\Marketing;

use Carbon\Carbon;
use RuntimeException;

/**
 * VK Реклама (ads.vk.com, API v2) — дневные итоги по всем кампаниям кабинета: расход, показы, клики,
 * просмотры от 3 секунд, досмотры, вступления в сообщество (подписки), заявки лид-форм. Перенесено из дашборда
 * БлагоДар (vkads.py, clips_manager.py). Ещё — те же цифры по каждой группе объявлений (в кампании «Клипы» группа =
 * клип): в цифрах дня ключ `groups` {id группы: цифры}, названия групп, кампаний и ссылки на клипы — в meta().
 * Заявки считаются только у кампаний на лид-формы: у охватных VK кладёт в base.vk.result показы.
 * У кабинета лимит 5 активных токенов: токен хранится в файле storage/app/owner-marketing/vk_ads_token.json и
 * обновляется refresh-токеном, новый без нужды не берётся. Не в кэше Laravel: `optimize:clear` при каждой выкладке
 * стирал его, и 09.10.2026 за день выкладок пять токенов кончились («токен не выдан, HTTP 403»).
 */
class VkAdsSource
{
    private const BASE = 'https://ads.vk.com';

    /** Файл токена; в тестах — свой временный. */
    public static ?string $tokenFile = null;

    private array $meta = [];

    public function __construct(private readonly array $cfg)
    {
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function enabled(): bool
    {
        return ! empty($this->cfg['client_id']) && ! empty($this->cfg['client_secret']);
    }

    /** @return array<string, array<string, mixed>> день => цифры (+ groups) */
    public function daily(Carbon $from, Carbon $to): array
    {
        $plans = $this->getAll('/api/v2/ad_plans.json', ['fields' => 'id,name,status,objective']);
        $plans = array_values(array_filter($plans, fn ($p) => ($p['status'] ?? '') !== 'deleted'));
        $ids = array_values(array_map(fn ($p) => (int) $p['id'], $plans));
        $leadPlans = array_values(array_map(fn ($p) => (int) $p['id'], array_filter($plans, fn ($p) => ($p['objective'] ?? '') === 'leadads')));
        $out = [];
        foreach (array_chunk($ids, 100) as $chunk) {
            $resp = $this->call('/api/v2/statistics/ad_plans/day.json', [
                'id' => implode(',', $chunk),
                'metrics' => 'all',
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ]);
            foreach ($resp['items'] ?? [] as $item) {
                $leadads = in_array((int) ($item['id'] ?? 0), $leadPlans, true);
                foreach ($item['rows'] ?? [] as $row) {
                    $day = (string) ($row['date'] ?? '');
                    if ($day === '') {
                        continue;
                    }
                    $p = self::parse($row);
                    if (! $leadads) {
                        $p['goals'] = 0;
                    }
                    foreach ($p as $k => $v) {
                        $out[$day][$k] = ($out[$day][$k] ?? 0) + $v;
                    }
                }
            }
        }
        $this->groups($from, $to, $plans, $out);
        foreach ($out as &$m) {
            $m['spent'] = round($m['spent'] ?? 0, 2);
        }
        unset($m);
        ksort($out);

        return $out;
    }

    /** Цифры групп объявлений по дням — в $out[день]['groups'][id]; только группы с показами или расходом. */
    private function groups(Carbon $from, Carbon $to, array $plans, array &$out): void
    {
        $planById = [];
        foreach ($plans as $p) {
            $planById[(int) $p['id']] = $p;
        }
        $groups = array_values(array_filter(
            $this->getAll('/api/v2/ad_groups.json', ['fields' => 'id,name,status,ad_plan_id']),
            fn ($g) => ($g['status'] ?? '') !== 'deleted' && isset($planById[(int) ($g['ad_plan_id'] ?? 0)])
        ));
        $clips = [];
        foreach ($this->getAll('/api/v2/banners.json', ['fields' => 'id,ad_group_id,urls']) as $b) {
            $url = (string) ($b['urls']['vk_clip']['url'] ?? '');
            if ($url !== '' && str_starts_with($url, 'https://vk.com/')) {
                $clips[(int) ($b['ad_group_id'] ?? 0)] ??= $url;
            }
        }
        $meta = [];
        foreach ($groups as $g) {
            $plan = $planById[(int) $g['ad_plan_id']];
            $meta[(int) $g['id']] = [
                'name' => (string) ($g['name'] ?? ''),
                'status' => (string) ($g['status'] ?? ''),
                'plan' => (string) ($plan['name'] ?? ''),
                'plan_status' => (string) ($plan['status'] ?? ''),
                'leadads' => ($plan['objective'] ?? '') === 'leadads',
                'clip' => $clips[(int) $g['id']] ?? null,
            ];
        }
        foreach (array_chunk(array_keys($meta), 100) as $chunk) {
            $resp = $this->call('/api/v2/statistics/ad_groups/day.json', [
                'id' => implode(',', $chunk),
                'metrics' => 'all',
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ]);
            foreach ($resp['items'] ?? [] as $item) {
                $gid = (int) ($item['id'] ?? 0);
                foreach ($item['rows'] ?? [] as $row) {
                    $day = (string) ($row['date'] ?? '');
                    $p = self::parse($row);
                    if ($day === '' || ($p['shows'] === 0 && $p['spent'] == 0)) {
                        continue;
                    }
                    if (! ($meta[$gid]['leadads'] ?? false)) {
                        $p['goals'] = 0;
                    }
                    $p['spent'] = round($p['spent'], 2);
                    $out[$day]['groups'][$gid] = $p;
                }
            }
        }
        $this->meta = ['groups' => $meta];
    }

    /** Строка статистики VK → цифры (как parse_total в clips_manager.py). */
    public static function parse(array $row): array
    {
        $base = $row['base'] ?? [];
        $video = $row['video'] ?? [];
        $social = $row['social_network'] ?? [];

        return [
            'spent' => (float) ($base['spent'] ?? 0),
            'shows' => (int) ($base['shows'] ?? 0),
            'clicks' => (int) ($base['clicks'] ?? 0),
            'views3' => (int) ($video['viewed_3_seconds'] ?? 0),
            'views100' => (int) ($video['viewed_100_percent'] ?? 0),
            'joins' => (int) ($social['result_join'] ?? 0),
            // заявки лид-форм приходят в base.vk.result (priced goal), а не в base.goals
            'goals' => (int) (($base['vk']['result'] ?? null) ?: ($base['goals'] ?? 0)),
        ];
    }

    private function getAll(string $path, array $query): array
    {
        $items = [];
        for ($offset = 0; ; $offset += 250) {
            $page = $this->call($path, $query + ['limit' => 250, 'offset' => $offset]);
            $items = array_merge($items, $page['items'] ?? []);
            if ($offset + 250 >= (int) ($page['count'] ?? 0)) {
                return $items;
            }
        }
    }

    private function call(string $path, array $query): array
    {
        [$status, $text] = Http::withRetry('GET', self::BASE.$path.'?'.http_build_query($query), ['Authorization' => 'Bearer '.$this->token()]);
        if ($status === 401) {
            $this->forgetToken();
        }
        if ($status !== 200) {
            throw Http::fail('VK Реклама '.$path, $status, $text);
        }

        return Http::json($text);
    }

    private function token(): string
    {
        $saved = $this->savedToken();
        $agency = (string) ($this->cfg['agency_client_name'] ?? '');
        if (is_array($saved) && ($saved['client_id'] ?? null) === $this->cfg['client_id'] && ($saved['agency'] ?? '') === $agency) {
            if (($saved['expires_at'] ?? 0) > time() + 300) {
                return $saved['access_token'];
            }
            $resp = ! empty($saved['refresh_token']) ? $this->tokenRequest([
                'grant_type' => 'refresh_token',
                'refresh_token' => $saved['refresh_token'],
                'client_id' => $this->cfg['client_id'],
                'client_secret' => $this->cfg['client_secret'],
            ], false) : null;
        } else {
            $saved = null;
            $resp = null;
        }

        if (! $resp) {
            $form = ['client_id' => $this->cfg['client_id'], 'client_secret' => $this->cfg['client_secret']];
            if ($agency !== '') {
                $form += ['grant_type' => 'agency_client_credentials', 'agency_client_name' => $agency];
            } else {
                $form['grant_type'] = 'client_credentials';
            }
            $resp = $this->tokenRequest($form, true);
        }

        $this->saveToken([
            'client_id' => $this->cfg['client_id'],
            'agency' => $agency,
            'access_token' => $resp['access_token'],
            'refresh_token' => $resp['refresh_token'] ?? ($saved['refresh_token'] ?? null),
            'expires_at' => time() + (int) ($resp['expires_in'] ?? 3600),
        ]);

        return $resp['access_token'];
    }

    private static function tokenPath(): string
    {
        return self::$tokenFile ?? storage_path('app/owner-marketing/vk_ads_token.json');
    }

    private function savedToken(): ?array
    {
        $path = self::tokenPath();
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : null;
    }

    private function saveToken(array $data): void
    {
        $path = self::tokenPath();
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        $tmp = $path.'.'.getmypid().'.tmp';
        file_put_contents($tmp, json_encode($data));
        chmod($tmp, 0600);
        rename($tmp, $path);
    }

    private function forgetToken(): void
    {
        $saved = $this->savedToken();
        if ($saved !== null) {
            // access-токен отозван или протух — refresh-токен ещё годится, новый токен из лимита не тратится
            $this->saveToken(['expires_at' => 0] + $saved);
        }
    }

    private function tokenRequest(array $form, bool $mustSucceed): ?array
    {
        [$status, $text] = Http::withRetry('POST', self::BASE.'/api/v2/oauth2/token.json',
            ['Content-Type' => 'application/x-www-form-urlencoded'], http_build_query($form));
        $data = Http::json($text);
        if ($status === 200 && ! empty($data['access_token'])) {
            return $data;
        }
        if ($mustSucceed) {
            throw new RuntimeException('VK Реклама: токен не выдан (HTTP '.$status.')'
                .(str_contains($text, 'limit') ? ' — у кабинета исчерпан лимит 5 токенов: удалите лишние токены в кабинете VK Рекламы' : ''));
        }

        return null;
    }
}

<?php

namespace App\Services\Owner\Marketing;

use App\Models\IntegrationConnection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Instagram (API с входом через Instagram, graph.instagram.com — с сервера доступен; graph.facebook.com — нет) для
 * «SMM-контента»: подписчики, публикации и их просмотры, охват, лайки, комментарии, сохранения, репосты.
 * Токены бизнес-аккаунтов (БлагоДар — ceilings, «Шаговый» — sneakers) админ вставляет в «Интеграции → Instagram»
 * (integration_connections пространства потолков, provider instagram, settings.accounts.{business}); токен живёт
 * 60 дней — продлевается здесь, когда до конца меньше 20 дней.
 * В цифрах дня: followers — снимок числа подписчиков (берётся раз в день и не стирается при перезаборе окна),
 * new_followers — новых за день (insights follower_count, только последние 30 дней). Публикации с цифрами — в meta().
 */
class InstagramSource
{
    public const PROVIDER = 'instagram';

    public const SOURCES = ['ig' => 'ceilings', 'ig_shag' => 'sneakers'];

    /** Сколько последних публикаций разбирать (каждая — свой запрос статистики). */
    private const MAX_POSTS = 60;

    private array $meta = [];

    public function __construct(private readonly string $business, private readonly string $sourceKey)
    {
    }

    public static function base(): string
    {
        return 'https://graph.instagram.com/'.trim((string) config('owner.marketing.instagram.version', 'v23.0'), '/');
    }

    public static function connection(): ?IntegrationConnection
    {
        return IntegrationConnection::withoutGlobalScopes()->where('account_id', (int) config('owner.ceilings_account_id'))
            ->where('provider', self::PROVIDER)->first();
    }

    /** Настройки аккаунта бизнеса без токена — для страниц. */
    public static function account(string $business): ?array
    {
        $conn = self::connection();
        $a = (array) (($conn?->settings ?? [])['accounts'][$business] ?? []);

        // «Отключить» в интеграциях — сбор останавливается, маркеры остаются
        return $conn && $conn->status === 'active' && ! empty($a['token']) ? array_diff_key($a, ['token' => 1]) : null;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function enabled(): bool
    {
        return self::account($this->business) !== null;
    }

    /** Проверка токена при подключении: профиль или исключение с текстом ошибки Instagram. */
    public static function profile(string $token): array
    {
        return self::get('/me', ['fields' => 'user_id,username,name,account_type,followers_count,follows_count,media_count,profile_picture_url'], $token);
    }

    /** @return array<string, array<string, mixed>> день => цифры */
    public function daily(Carbon $from, Carbon $to): array
    {
        $token = $this->token();
        $profile = self::profile($token);
        $errors = [];

        // окно перезаписывается целиком — снимки подписчиков прошлых дней берём из уже собранного
        $out = [];
        foreach (DB::table('owner_marketing_daily')->where('source', $this->sourceKey)
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])->get(['day', 'metrics']) as $r) {
            $out[substr((string) $r->day, 0, 10)] = json_decode((string) $r->metrics, true) ?: [];
        }
        $today = Carbon::today()->toDateString();
        if ($today >= $from->toDateString() && $today <= $to->toDateString()) {
            $out[$today]['followers'] = (int) ($profile['followers_count'] ?? 0);
        }
        try {
            $since = max($from->copy()->startOfDay()->getTimestamp(), Carbon::today()->subDays(29)->getTimestamp());
            $ins = self::get('/me/insights', ['metric' => 'follower_count', 'period' => 'day', 'since' => $since, 'until' => $to->copy()->addDay()->getTimestamp()], $token);
            foreach ((array) ($ins['data'][0]['values'] ?? []) as $v) {
                $day = Carbon::parse((string) ($v['end_time'] ?? ''))->subDay()->toDateString();
                if ($day >= $from->toDateString() && $day <= $to->toDateString()) {
                    $out[$day]['new_followers'] = (int) ($v['value'] ?? 0);
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'новые подписчики по дням: '.$e->getMessage();   // у аккаунтов меньше 100 подписчиков Instagram их не отдаёт
        }

        $posts = [];
        $page = self::get('/me/media', ['fields' => 'id,caption,media_type,media_product_type,permalink,thumbnail_url,media_url,timestamp,like_count,comments_count', 'limit' => 50], $token);
        while (true) {
            foreach ((array) ($page['data'] ?? []) as $m) {
                $posts[] = $m;
            }
            $next = (string) ($page['paging']['next'] ?? '');
            if ($next === '' || count($posts) >= self::MAX_POSTS) {
                break;
            }
            $page = self::getUrl($next);
        }
        $posts = array_slice($posts, 0, self::MAX_POSTS);
        foreach ($posts as &$m) {
            $m = self::post($m, $token, $errors);
        }
        unset($m);

        $this->meta = [
            'business' => $this->business,
            'profile' => array_intersect_key($profile, array_flip(['user_id', 'username', 'name', 'account_type', 'followers_count', 'follows_count', 'media_count', 'profile_picture_url'])),
            'posts' => $posts,
            'warnings' => array_values(array_unique(array_slice($errors, 0, 5))),
        ];
        ksort($out);

        return $out;
    }

    /** Цифры одной публикации: поля медиа + insights (views, reach, saved, shares); Instagram не у всех типов отдаёт все. */
    private static function post(array $m, string $token, array &$errors): array
    {
        $row = [
            'id' => (string) ($m['id'] ?? ''),
            'caption' => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($m['caption'] ?? ''))), 0, 160),
            'type' => (string) (($m['media_product_type'] ?? '') === 'REELS' ? 'REELS' : ($m['media_type'] ?? '')),
            'permalink' => (string) ($m['permalink'] ?? ''),
            'thumb' => (string) ($m['thumbnail_url'] ?? (($m['media_type'] ?? '') === 'IMAGE' || ($m['media_type'] ?? '') === 'CAROUSEL_ALBUM' ? ($m['media_url'] ?? '') : '')),
            'at' => isset($m['timestamp']) ? Carbon::parse((string) $m['timestamp'])->setTimezone(config('app.timezone'))->format('Y-m-d H:i') : null,
            'likes' => isset($m['like_count']) ? (int) $m['like_count'] : null,
            'comments' => isset($m['comments_count']) ? (int) $m['comments_count'] : null,
            'views' => null, 'reach' => null, 'saved' => null, 'shares' => null,
        ];
        foreach (['views,reach,saved,shares', 'reach,saved,shares', 'views'] as $metrics) {
            try {
                $ins = self::get('/'.$row['id'].'/insights', ['metric' => $metrics], $token);
                foreach ((array) ($ins['data'] ?? []) as $d) {
                    $name = (string) ($d['name'] ?? '');
                    if (array_key_exists($name, $row) && $row[$name] === null) {
                        $row[$name] = (int) ($d['values'][0]['value'] ?? $d['total_value']['value'] ?? 0);
                    }
                }
                if ($row['views'] !== null && $row['reach'] !== null) {
                    break;
                }
            } catch (\Throwable $e) {
                $errors[] = 'статистика публикации: '.$e->getMessage();
            }
        }

        return $row;
    }

    /** Токен аккаунта; за 20 дней до конца — продлить (Instagram продлевает токены старше суток ещё на 60 дней). */
    private function token(): string
    {
        $conn = self::connection();
        $settings = (array) ($conn?->settings ?? []);
        $acc = (array) ($settings['accounts'][$this->business] ?? []);
        $token = (string) ($acc['token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Instagram не подключён');
        }
        $expires = isset($acc['expires_at']) ? Carbon::parse((string) $acc['expires_at']) : null;
        $saved = isset($acc['saved_at']) ? Carbon::parse((string) $acc['saved_at']) : null;
        if ($expires !== null && $expires->lt(now()->addDays(20)) && ($saved === null || $saved->lt(now()->subDay()))) {
            try {
                $r = self::getUrl('https://graph.instagram.com/refresh_access_token?'.http_build_query(['grant_type' => 'ig_refresh_token', 'access_token' => $token]));
                if (! empty($r['access_token'])) {
                    $acc = array_merge($acc, ['token' => (string) $r['access_token'], 'saved_at' => now()->toDateTimeString(),
                        'expires_at' => now()->addSeconds((int) ($r['expires_in'] ?? 5184000))->toDateTimeString()]);
                    $settings['accounts'][$this->business] = $acc;
                    $conn->forceFill(['settings' => $settings])->save();
                    $token = $acc['token'];
                }
            } catch (\Throwable $e) {
                // продлить не вышло — старый токен ещё работает, попробуем в следующий раз
            }
        }

        return $token;
    }

    private static function get(string $path, array $query, string $token): array
    {
        return self::getUrl(self::base().$path.'?'.http_build_query($query + ['access_token' => $token]));
    }

    /** GET и разбор ответа; в тексте ошибки — сообщение Instagram, без адреса (в нём токен). */
    private static function getUrl(string $url): array
    {
        [$status, $text] = Http::withRetry('GET', $url, [], null, 30);
        $data = Http::json($text);
        if ($status !== 200 || isset($data['error'])) {
            $msg = (string) ($data['error']['message'] ?? '');
            throw new RuntimeException('Instagram: HTTP '.$status.($msg !== '' ? ' — '.mb_substr($msg, 0, 200) : ''));
        }

        return $data;
    }
}

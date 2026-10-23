<?php

namespace App\Services\Owner;

use App\Services\Owner\Marketing\InstagramSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * «SMM-контент» → блок Instagram за период: подписчики (сейчас и прирост — по ежедневным снимкам сборщика или по
 * «новым подписчикам» Instagram), публикации периода с просмотрами, охватом, лайками, комментариями, сохранениями
 * и репостами (цифры публикации — за всё её время, как их отдаёт Instagram). Источник — InstagramSource (ig, ig_shag).
 */
class InstagramStats
{
    public static function forBusiness(string $business, Carbon $from, Carbon $toExclusive): array
    {
        $key = (string) array_search($business, InstagramSource::SOURCES, true);
        $acc = InstagramSource::account($business);
        if ($key === '' || $acc === null) {
            return ['connected' => false];
        }
        $status = DB::table('owner_marketing_sources')->where('source', $key)->first();
        $meta = json_decode((string) ($status->meta ?? ''), true) ?: [];
        $rows = DB::table('owner_marketing_daily')->where('source', $key)
            ->where('day', '>=', $from->toDateString())->where('day', '<', $toExclusive->toDateString())
            ->orderBy('day')->get(['day', 'metrics'])
            ->map(fn ($r) => ['day' => substr((string) $r->day, 0, 10)] + (json_decode((string) $r->metrics, true) ?: []));

        $snaps = $rows->filter(fn ($r) => isset($r['followers']))->values();
        $newRows = $rows->filter(fn ($r) => isset($r['new_followers']));
        $posts = collect((array) ($meta['posts'] ?? []))
            ->filter(fn ($p) => ! empty($p['at']) && $p['at'] >= $from->toDateString() && $p['at'] < $toExclusive->toDateString())
            ->sortByDesc(fn ($p) => [(int) ($p['views'] ?? -1), (string) $p['at']])->values();
        $sum = fn (string $k) => $posts->contains(fn ($p) => $p[$k] !== null) ? (int) $posts->sum(fn ($p) => (int) ($p[$k] ?? 0)) : null;
        $views = $sum('views');
        $engagement = $posts->sum(fn ($p) => (int) ($p['likes'] ?? 0) + (int) ($p['comments'] ?? 0) + (int) ($p['saved'] ?? 0) + (int) ($p['shares'] ?? 0));

        return [
            'connected' => true,
            'username' => (string) ($meta['profile']['username'] ?? $acc['username'] ?? ''),
            'collected' => $status !== null && $status->last_ok_at !== null,
            'updated' => $status && $status->last_ok_at ? Carbon::parse($status->last_ok_at)->format('d.m.Y H:i') : null,
            'error' => $status?->last_error,
            'followers' => (int) ($meta['profile']['followers_count'] ?? $acc['followers'] ?? 0),
            // прирост: разница первого и последнего снимка периода; снимков меньше двух — «новые подписчики» Instagram
            'growth' => $snaps->count() >= 2 ? (int) $snaps->last()['followers'] - (int) $snaps->first()['followers'] : null,
            'growth_since' => $snaps->count() >= 2 ? Carbon::parse($snaps->first()['day'])->format('d.m') : null,
            'new_followers' => $newRows->isNotEmpty() ? (int) $newRows->sum('new_followers') : null,
            'posts' => $posts->all(),
            'latest' => $posts->isEmpty() ? collect((array) ($meta['posts'] ?? []))->take(5)->values()->all() : [],
            'count' => $posts->count(),
            'views' => $views,
            'avg_views' => $views !== null && $posts->count() > 0 ? (int) round($views / $posts->count()) : null,
            'reach' => $sum('reach'),
            'engagement' => $posts->isNotEmpty() ? (int) $engagement : null,
            'warnings' => (array) ($meta['warnings'] ?? []),
        ];
    }
}

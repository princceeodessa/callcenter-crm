<?php

namespace App\Services\Owner\Marketing;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Сбор рекламы потолков в owner_marketing_daily. Источники независимы: ошибка одного не мешает остальным и
 * записывается в owner_marketing_sources (видно на сводке). Пустой или сломанный ответ старые цифры не стирает.
 */
class MarketingCollector
{
    public const LABELS = [
        'direct' => 'Яндекс Директ',
        'vk' => 'VK Реклама',
        'avito' => 'Авито',
        'sheet' => 'Таблица заявок',
    ];

    /** @return array<string, VkAdsSource|DirectSource|AvitoSource|LeadsSheetSource> */
    public static function sources(): array
    {
        $cfg = (array) config('owner.marketing');

        return [
            'direct' => new DirectSource((array) ($cfg['direct'] ?? [])),
            'vk' => new VkAdsSource((array) ($cfg['vk_ads'] ?? [])),
            'avito' => new AvitoSource((array) ($cfg['avito'] ?? [])),
            'sheet' => new LeadsSheetSource((array) ($cfg['leads_sheet'] ?? [])),
        ];
    }

    /** @return array<string, string> источник => итог одной строкой */
    public function run(int $days, array $only = []): array
    {
        $to = Carbon::today();
        $from = $to->copy()->subDays(max(1, $days) - 1);
        $result = [];

        foreach (self::sources() as $key => $source) {
            if ($only && ! in_array($key, $only, true)) {
                continue;
            }
            if (! $source->enabled()) {
                $result[$key] = 'не настроен (нет ключей в .env)';
                continue;
            }
            $this->status($key, ['last_attempt_at' => now()]);
            try {
                $daily = $source->daily($from, $to);
                DB::transaction(function () use ($key, $daily, $from, $to) {
                    // API отдают окно целиком — дни без активности просто не приходят, поэтому окно заменяется.
                    // Таблицу заявок заменяем только по прочитанным дням: недоступный лист не должен стирать месяц.
                    if ($key !== 'sheet') {
                        DB::table('owner_marketing_daily')->where('source', $key)
                            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])->delete();
                    }
                    $rows = [];
                    foreach ($daily as $day => $metrics) {
                        $rows[] = ['source' => $key, 'day' => $day, 'metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()];
                    }
                    foreach (array_chunk($rows, 200) as $chunk) {
                        DB::table('owner_marketing_daily')->upsert($chunk, ['source', 'day'], ['metrics', 'updated_at']);
                    }
                });
                $meta = method_exists($source, 'meta') ? $source->meta() : [];
                $this->status($key, [
                    'last_ok_at' => now(),
                    'last_error' => null,
                    'meta' => json_encode($meta + ['days' => count($daily), 'from' => $from->toDateString(), 'to' => $to->toDateString()], JSON_UNESCAPED_UNICODE),
                ]);
                $result[$key] = 'ok, дней с данными: '.count($daily);
            } catch (\Throwable $e) {
                $msg = mb_substr($e->getMessage(), 0, 500);
                $this->status($key, ['last_error' => $msg]);
                Log::warning('owner marketing '.$key.': '.$msg);
                $result[$key] = 'ошибка: '.$msg;
            }
        }

        return $result;
    }

    private function status(string $key, array $values): void
    {
        $exists = DB::table('owner_marketing_sources')->where('source', $key)->exists();
        if ($exists) {
            DB::table('owner_marketing_sources')->where('source', $key)->update($values + ['updated_at' => now()]);
        } else {
            DB::table('owner_marketing_sources')->insert($values + ['source' => $key, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}

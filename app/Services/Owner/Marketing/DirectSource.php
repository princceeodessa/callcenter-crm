<?php

namespace App\Services\Owner\Marketing;

use Carbon\Carbon;
use RuntimeException;

/**
 * Яндекс Директ (API v5, сервис Reports) — дневные показы, клики и расход по всему кабинету, с НДС, в рублях.
 * Перенесено из дашборда БлагоДар (direct.py). Отчёт строится асинхронно: на 201/202 ждём и повторяем тот же запрос.
 */
class DirectSource
{
    private const PROD = 'https://api.direct.yandex.com/json/v5/';
    private const SANDBOX = 'https://api-sandbox.direct.yandex.com/json/v5/';

    public function __construct(private readonly array $cfg)
    {
    }

    public function enabled(): bool
    {
        return ! empty($this->cfg['token']);
    }

    /** @return array<string, array<string, float|int>> */
    public function daily(Carbon $from, Carbon $to): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$this->cfg['token'],
            'Accept-Language' => 'ru',
            'Content-Type' => 'application/json; charset=utf-8',
            'processingMode' => 'auto',
            'returnMoneyInMicros' => 'false',
            'skipReportHeader' => 'true',
            'skipReportSummary' => 'true',
        ];
        if (! empty($this->cfg['client_login'])) {
            $headers['Client-Login'] = $this->cfg['client_login'];
        }
        $body = json_encode(['params' => [
            'SelectionCriteria' => ['DateFrom' => $from->toDateString(), 'DateTo' => $to->toDateString()],
            'FieldNames' => ['Date', 'Impressions', 'Clicks', 'Cost'],
            'ReportName' => 'crm-owner-'.$from->format('Ymd').'-'.$to->format('Ymd').'-'.substr(md5((string) microtime(true)), 0, 8),
            'ReportType' => 'ACCOUNT_PERFORMANCE_REPORT',
            'DateRangeType' => 'CUSTOM_DATE',
            'Format' => 'TSV',
            'IncludeVAT' => 'YES',
        ]], JSON_UNESCAPED_UNICODE);
        $url = (! empty($this->cfg['sandbox']) ? self::SANDBOX : self::PROD).'reports';

        for ($i = 0; $i < 40; $i++) {
            [$status, $text] = Http::withRetry('POST', $url, $headers, $body, 120);
            if ($status === 200) {
                return self::parseTsv($text);
            }
            if ($status === 201 || $status === 202) {
                if (Http::$fake === null) {
                    sleep(3);
                }
                continue;
            }
            throw Http::fail('Яндекс Директ, отчёт', $status, $text);
        }
        throw new RuntimeException('Яндекс Директ: отчёт не готов после двух минут ожидания');
    }

    /** TSV отчёта (первая строка — заголовки) → день => показы/клики/расход. */
    public static function parseTsv(string $text): array
    {
        $lines = array_values(array_filter(explode("\n", str_replace("\r", '', $text)), fn ($l) => $l !== ''));
        if (count($lines) < 2) {
            return [];
        }
        $head = explode("\t", $lines[0]);
        $out = [];
        foreach (array_slice($lines, 1) as $line) {
            $row = array_combine($head, array_pad(explode("\t", $line), count($head), ''));
            $day = (string) ($row['Date'] ?? '');
            if ($day === '') {
                continue;
            }
            $num = fn ($v) => (float) str_replace([' ', ','], ['', '.'], (string) $v);
            $out[$day]['impr'] = ($out[$day]['impr'] ?? 0) + (int) $num($row['Impressions'] ?? 0);
            $out[$day]['clicks'] = ($out[$day]['clicks'] ?? 0) + (int) $num($row['Clicks'] ?? 0);
            $out[$day]['cost'] = round(($out[$day]['cost'] ?? 0) + $num($row['Cost'] ?? 0), 2);
        }
        ksort($out);

        return $out;
    }
}

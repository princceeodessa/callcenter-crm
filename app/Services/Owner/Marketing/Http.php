<?php

namespace App\Services\Owner\Marketing;

use RuntimeException;

/**
 * Простой HTTP на curl для клиентов рекламных кабинетов (без зависимости от Guzzle).
 * В тестах запросы подменяются через Http::$fake.
 */
class Http
{
    /** @var null|\Closure(string $method, string $url, array $headers, ?string $body): array{0:int,1:string,2?:string} */
    public static ?\Closure $fake = null;

    /** @return array{0:int, 1:string, 2:string} статус, тело, content-type */
    public static function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 60): array
    {
        if (self::$fake !== null) {
            $r = (self::$fake)($method, $url, $headers, $body);

            return [$r[0], $r[1], $r[2] ?? 'application/json'];
        }

        $ch = curl_init($url);
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = $k.': '.$v;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $lines,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $text = curl_exec($ch);
        if ($text === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Сеть: '.$err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        return [$status, (string) $text, $type];
    }

    /** Запрос с повтором при 429/5xx: до 5 попыток с паузой 2·N секунд. @return array{0:int, 1:string, 2:string} */
    public static function withRetry(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 60): array
    {
        for ($attempt = 1; ; $attempt++) {
            $r = self::request($method, $url, $headers, $body, $timeout);
            if (in_array($r[0], [429, 500, 502, 503], true) && $attempt < 5) {
                if (self::$fake === null) {
                    sleep(2 * $attempt);
                }
                continue;
            }

            return $r;
        }
    }

    public static function json(string $text): array
    {
        $data = json_decode($text, true);

        return is_array($data) ? $data : [];
    }

    /** Короткий текст ошибки ответа (без заголовков и тела целиком). */
    public static function fail(string $what, int $status, string $text): RuntimeException
    {
        $snippet = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($text))), 0, 300);

        return new RuntimeException($what.': HTTP '.$status.($snippet !== '' ? ' — '.$snippet : ''));
    }
}

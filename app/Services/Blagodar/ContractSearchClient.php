<?php

namespace App\Services\Blagodar;

use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Поиск договоров 1С через CRM «БлагоДар» (26.11.2026, правило 370 их CRM; инструкция —
 * docs/agent/kc-site-contract-search.md в репозитории CRM «БлагоДар»). Поиск — два запроса: «начать» и «что нашлось»
 * (CRM ставит поиск в очередь, офисный агент обмена спрашивает все базы 1С и отвечает по каждой).
 *
 * Сессия CRM: ОДНА пара токенов на весь сайт — в файле (php-fpm — много процессов, общей памяти нет; кэш Laravel
 * стирается при выкладке). Обновление — одно на всех под блокировкой файла: старый refreshToken после обновления
 * гаснет, а повторное его использование CRM считает кражей и гасит все сессии учётки. 401 — один раз refresh
 * (или вход), потом повтор запроса. В журнал — только служебное: код ответа и путь (id поиска), строку запроса
 * оператора (фамилия, телефон) и ответы с данными заказчиков — никогда. HTTP — curl (Guzzle в проекте нет).
 */
class ContractSearchClient
{
    /** Подмена запросов в тестах: fn (string $method, string $url, ?array $json, array $headers): array{0: int, 1: array} */
    public static ?Closure $fake = null;

    public static function enabled(): bool
    {
        return config('blagodar_crm.api') !== '' && filled(config('blagodar_crm.login')) && filled(config('blagodar_crm.password'));
    }

    /** @return array{id: string, query?: string} */
    public function start(string $query): array
    {
        return $this->call('POST', '/onec-contracts/searches', ['query' => $query]);
    }

    /** @return array<string, mixed> state, bases, waiting, results */
    public function status(string $id): array
    {
        return $this->call('GET', '/onec-contracts/searches/'.rawurlencode($id));
    }

    /** @return array<string, mixed> */
    private function call(string $method, string $path, ?array $body = null): array
    {
        $tokens = $this->tokens() ?? $this->locked(fn () => $this->tokens() ?? $this->login());
        for ($attempt = 0; $attempt < 2; $attempt++) {
            [$status, $data] = $this->request($method, $path, $body, $tokens['accessToken']);
            if ($status === 401 && $attempt === 0) {
                $tokens = $this->refreshAfter($tokens['accessToken']);
                continue;
            }
            if ($status < 200 || $status >= 300) {
                Log::warning('CRM «БлагоДар», поиск договоров: ответ '.$status, ['path' => $path]);
                $msg = $data['message'] ?? '';   // у CRM бывает и списком (ошибки проверки полей)
                throw new CrmApiException($status, is_array($msg) ? implode('; ', array_map('strval', $msg)) : (string) $msg);
            }

            return $data;
        }

        throw new CrmApiException(401, '');
    }

    /** 401 на запрос: обновить сессию — если её уже не обновил другой запрос, пока мы ждали блокировку. */
    private function refreshAfter(string $staleAccess): array
    {
        return $this->locked(function () use ($staleAccess) {
            $cur = $this->tokens();
            if ($cur !== null && $cur['accessToken'] !== $staleAccess) {
                return $cur;
            }
            if ($cur !== null) {
                [$status, $data] = $this->request('POST', '/auth/refresh', ['refreshToken' => $cur['refreshToken']]);
                if ($status >= 200 && $status < 300 && ! empty($data['accessToken'])) {
                    return $this->save((string) $data['accessToken'], (string) (($data['refreshToken'] ?? '') ?: $cur['refreshToken']));
                }
            }

            return $this->login();
        });
    }

    /** Вход учёткой сайта (вызывается под блокировкой). */
    private function login(): array
    {
        [$status, $data] = $this->request('POST', '/auth/login', [
            'phone' => (string) config('blagodar_crm.login'),
            'password' => (string) config('blagodar_crm.password'),
            'device' => 'kc-site',
        ]);
        if ($status < 200 || $status >= 300 || empty($data['accessToken']) || empty($data['refreshToken'])) {
            Log::warning('CRM «БлагоДар»: вход учётки сайта не удался, ответ '.$status);
            throw new CrmApiException($status === 429 ? 429 : ($status === 0 ? 0 : 401), '');
        }

        return $this->save((string) $data['accessToken'], (string) $data['refreshToken']);
    }

    /** @return array{0: int, 1: array<string, mixed>} код ответа (0 — сеть) и JSON-ответ */
    private function request(string $method, string $path, ?array $json = null, ?string $token = null): array
    {
        $url = config('blagodar_crm.api').$path;
        $headers = ['Accept' => 'application/json'] + ($json !== null ? ['Content-Type' => 'application/json'] : [])
            + ($token !== null ? ['Authorization' => 'Bearer '.$token] : []);
        if (self::$fake !== null) {
            $r = (self::$fake)($method, $url, $json, $headers);

            return [(int) $r[0], (array) ($r[1] ?? [])];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => array_map(fn ($k, $v) => $k.': '.$v, array_keys($headers), $headers),
        ]);
        if ($json !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
        }
        $text = curl_exec($ch);
        $status = $text === false ? 0 : (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($text === false) {
            Log::warning('CRM «БлагоДар» не отвечает', ['path' => $path]);

            return [0, []];
        }
        $data = json_decode((string) $text, true);

        return [$status, is_array($data) ? $data : []];
    }

    /** @return array{accessToken: string, refreshToken: string}|null */
    private function tokens(): ?array
    {
        $file = (string) config('blagodar_crm.token_file');
        $data = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;

        return is_array($data) && ! empty($data['accessToken']) && ! empty($data['refreshToken'])
            ? ['accessToken' => (string) $data['accessToken'], 'refreshToken' => (string) $data['refreshToken']] : null;
    }

    private function save(string $access, string $refresh): array
    {
        $file = (string) config('blagodar_crm.token_file');
        $tmp = $file.'.'.getmypid().'.tmp';
        file_put_contents($tmp, json_encode(['accessToken' => $access, 'refreshToken' => $refresh, 'saved_at' => now()->toIso8601String()]));
        @chmod($tmp, 0600);
        rename($tmp, $file);

        return ['accessToken' => $access, 'refreshToken' => $refresh];
    }

    /** Вход и обновление — по одному на весь сайт: блокировка файла рядом с токенами. */
    private function locked(Closure $fn): array
    {
        $file = (string) config('blagodar_crm.token_file');
        if (! is_dir(dirname($file))) {
            @mkdir(dirname($file), 0700, true);
        }
        $fh = fopen($file.'.lock', 'c');
        try {
            flock($fh, LOCK_EX);

            return $fn();
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}

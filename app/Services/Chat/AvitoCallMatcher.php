<?php

namespace App\Services\Chat;

use App\Models\Deal;
use App\Models\DealActivity;
use App\Services\Integrations\AvitoConnections;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «Звонок через Авито» (05.11.2026): клиент жмёт «Позвонить» в Авито — звонок приходит к нам через МегаФон на номер
 * кабинета, а Авито в момент окончания звонка пишет в чат сообщение appCall (без номера телефона). Без привязки
 * один клиент становился двумя сделками: «Звонки: 7912…» и «Имя — Avito». Новый чат с таким сообщением кладём
 * в сделку звонка, который закончился в ту же минуту на номер этого кабинета.
 */
class AvitoCallMatcher
{
    /** Конец звонка у МегаФона и сообщение Авито расходятся на секунды; берём с запасом. */
    private const END_TOLERANCE = 60;

    /** Звонок, конец которого ещё не дошёл от МегаФона (опрос Авито успел раньше), — начат не раньше. */
    private const OPEN_CALL_WINDOW = 40 * 60;

    public static function callDealFor(int $accountId, array $payload): ?Deal
    {
        $msg = is_array($payload['message'] ?? null) ? $payload['message'] : (array) data_get($payload, 'payload.value', []);
        if (! in_array($msg['type'] ?? null, ['appCall', 'call'], true)) {
            return null;
        }

        $endedAt = is_numeric($msg['created'] ?? null) ? Carbon::createFromTimestamp((int) $msg['created']) : now();
        $cabinet = trim((string) ($payload['account_id'] ?? data_get($payload, 'payload.value.user_id') ?? ''));
        $numbers = self::cabinetNumbers($cabinet !== '' && in_array($cabinet, AvitoConnections::privateUserIds($accountId), true));
        if ($numbers === []) {
            return null;
        }

        // входящие звонки на номера кабинета вокруг этого момента: когда закончились и какие ещё идут
        $ends = [];      // сделка => насколько конец звонка далёк от сообщения Авито, с
        $started = [];   // сделка|callid => начало звонка, конец которого ещё не пришёл
        $activities = DealActivity::query()
            ->where('account_id', $accountId)
            ->where('type', 'call')
            ->whereBetween('created_at', [$endedAt->copy()->subSeconds(self::OPEN_CALL_WINDOW), $endedAt->copy()->addMinutes(3)])
            ->orderBy('id')
            ->get(['deal_id', 'payload', 'created_at']);
        foreach ($activities as $a) {
            $p = is_array($a->payload) ? $a->payload : [];
            $type = strtoupper((string) ($p['type'] ?? ''));
            if ($type === 'OUT' || strtolower((string) ($p['direction'] ?? '')) === 'out' || ! in_array(self::tail($p['diversion'] ?? null), $numbers, true)) {
                continue;
            }
            $key = $a->deal_id.'|'.($p['callid'] ?? '');
            $end = match (true) {
                ($p['cmd'] ?? '') === 'history' && isset($p['start']) => Carbon::parse((string) $p['start'])->addSeconds((int) ($p['duration'] ?? 0)),
                ($p['cmd'] ?? '') === 'event' && in_array($type, ['COMPLETED', 'CANCELLED', 'MISSED'], true) => $a->created_at,
                default => null,
            };
            if ($end) {
                $diff = abs($end->getTimestamp() - $endedAt->getTimestamp());
                $ends[$a->deal_id] = min($ends[$a->deal_id] ?? PHP_INT_MAX, $diff);
                unset($started[$key]);
            } elseif ($a->created_at->lte($endedAt->copy()->addSeconds(10))) {
                $started[$key] ??= (int) $a->deal_id;
            }
        }

        // 1) звонок закончился в ту же минуту, что и сообщение Авито; два почти одновременных — не угадываем
        $near = collect($ends)->filter(fn (int $diff) => $diff <= self::END_TOLERANCE)->sort();
        $dealId = null;
        if ($near->isNotEmpty()) {
            $diffs = $near->values();
            $dealId = $near->count() > 1 && $diffs[1] - $diffs[0] < 5 ? null : $near->keys()->first();
        } elseif (count(array_unique($started)) === 1) {
            // 2) конец звонка от МегаФона ещё не пришёл (опрос Авито успел раньше) — единственный идущий звонок
            $dealId = reset($started);
        }

        return $dealId ? Deal::query()->where('account_id', $accountId)->whereNull('closed_at')->find($dealId) : null;
    }

    /** Номера звонков кабинета из «Настройки → Источники звонков»: «авито частник» — кабинет частника, прочие «авито» — основной. @return list<string> */
    private static function cabinetNumbers(bool $private): array
    {
        if (! Schema::hasTable('phone_sources')) {
            return [];
        }

        return DB::table('phone_sources')->get(['number', 'label'])
            ->filter(function ($row) use ($private) {
                $label = mb_strtolower((string) $row->label);

                return str_contains($label, 'авито') && str_contains($label, 'частник') === $private;
            })
            ->map(fn ($row) => self::tail($row->number))->filter()->values()->all();
    }

    private static function tail(mixed $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', is_scalar($phone) ? (string) $phone : '');

        return strlen($digits) >= 10 ? substr($digits, -10) : null;
    }
}

<?php

namespace App\Support\Leads;

use App\Models\Contact;
use App\Models\Deal;
use Illuminate\Support\Facades\Cache;

/**
 * Единый дедуп входящих лидов по номеру телефона для всех источников
 * (звонки МегаФон, формы Tilda/VK и т.п.):
 *  - findOpenDealByPhone: найти уже открытую сделку по номеру (любой источник);
 *  - withPhoneLock: сериализовать создание сделки по номеру, чтобы одновременные
 *    события (гонка) не плодили дубликаты.
 */
class LeadDeduplicator
{
    /** Нормализация телефона к 11-значному РФ-формату (7XXXXXXXXXX). */
    public static function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if (! $digits) {
            return null;
        }
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7'.substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            $digits = '7'.$digits;
        }

        return $digits;
    }

    /** @return array<int,string> */
    public static function phoneSearchVariants(string $phone): array
    {
        $tail = strlen($phone) > 1 ? substr($phone, 1) : $phone;

        return array_values(array_unique(array_filter([
            $phone,
            $tail,
            '+'.$phone,
            '8'.$tail,
        ])));
    }

    /**
     * Найти открытую (не закрытую) сделку по номеру телефона: сперва по контакту,
     * затем по активностям (тело/payload содержат номер). Возвращает наиболее
     * подходящую (звонковую — в приоритете, затем свежайшую).
     */
    public static function findOpenDealByPhone(int $accountId, string $clientPhone, ?int $contactId = null): ?Deal
    {
        $variants = self::phoneSearchVariants($clientPhone);

        $contactIds = Contact::query()
            ->where('account_id', $accountId)
            ->where(function ($query) use ($variants) {
                foreach ($variants as $index => $variant) {
                    $index === 0
                        ? $query->where('phone', 'like', '%'.$variant.'%')
                        : $query->orWhere('phone', 'like', '%'.$variant.'%');
                }
            })
            ->pluck('id')->filter()->values()->all();

        if ($contactId && ! in_array($contactId, $contactIds, true)) {
            $contactIds[] = $contactId;
        }

        if (! empty($contactIds)) {
            $byContact = Deal::query()
                ->where('account_id', $accountId)
                ->whereNull('closed_at')
                ->whereIn('contact_id', $contactIds)
                ->with('contact:id,phone')
                ->orderByRaw("CASE WHEN title LIKE 'Звонки:%' THEN 1 ELSE 0 END")
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get()
                ->first(fn (Deal $deal) => self::normalizePhone((string) ($deal->contact?->phone ?? '')) === $clientPhone);

            if ($byContact) {
                return $byContact;
            }
        }

        return Deal::query()
            ->where('account_id', $accountId)
            ->whereNull('closed_at')
            ->whereHas('activities', function ($query) use ($variants) {
                $query->where(function ($activityQuery) use ($variants) {
                    foreach ($variants as $index => $variant) {
                        if ($index === 0) {
                            $activityQuery->where('body', 'like', '%'.$variant.'%')
                                ->orWhereRaw('CAST(payload AS CHAR) LIKE ?', ['%'.$variant.'%']);
                        } else {
                            $activityQuery->orWhere('body', 'like', '%'.$variant.'%')
                                ->orWhereRaw('CAST(payload AS CHAR) LIKE ?', ['%'.$variant.'%']);
                        }
                    }
                });
            })
            ->orderByRaw("CASE WHEN title LIKE 'Звонки:%' THEN 1 ELSE 0 END")
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Выполнить $callback под блокировкой по номеру телефона (сериализация создания
     * сделки для одного номера). Если номера нет, блокировку не удалось взять за
     * отведённое время, либо сам кэш-стор недоступен (диск/права и т.п.) — выполняем
     * без блокировки (лучше без дедупа, чем потерять лид).
     *
     * Важно: колбэк вызывается ровно один раз. Ошибка при acquire() (до колбэка)
     * ловится и уходит в fallback; ошибка при release() (после колбэка, в finally
     * у Lock::block()) не должна приводить к повторному вызову — поэтому получение
     * лока и вызов колбэка разнесены по разным try-блокам.
     *
     * @template T
     * @param  callable():T  $callback
     * @return T
     */
    public static function withPhoneLock(int $accountId, ?string $phone, callable $callback)
    {
        if (! $phone) {
            return $callback();
        }

        $lock = Cache::lock('lead-phone:'.$accountId.':'.$phone, 15);

        try {
            $acquired = $lock->block(8);
        } catch (\Throwable $e) {
            report($e);
            $acquired = false;
        }

        if (! $acquired) {
            return $callback();
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}

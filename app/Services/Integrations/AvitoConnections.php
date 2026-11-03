<?php

namespace App\Services\Integrations;

use App\Models\Conversation;
use App\Models\IntegrationConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Кабинеты Авито аккаунта (с 03.11.2026 их может быть несколько: основной и связанный «Дмитрий Мастер»).
 * Чат принадлежит одному кабинету: его историю читают и ответы шлют его ключами, иначе Авито отвечает отказом.
 */
class AvitoConnections
{
    /** Подключения аккаунта, первым — основной (меньший id). @return Collection<int, IntegrationConnection> */
    public static function all(int $accountId, array $statuses = ['active']): Collection
    {
        return IntegrationConnection::query()
            ->where('account_id', $accountId)
            ->where('provider', 'avito')
            ->whereIn('status', $statuses)
            ->orderBy('id')
            ->get();
    }

    /**
     * Кабинет чата: conversations.meta.avito_user_id → кабинет, из которого опрос принёс входящие (payload.account_id —
     * id кабинета Авито) → основной. Найденный по сообщениям запоминается в meta.
     */
    public static function forConversation(Conversation $conversation, array $statuses = ['active']): ?IntegrationConnection
    {
        $all = self::all((int) $conversation->account_id, $statuses);
        if ($all->count() <= 1) {
            return $all->first();
        }

        $meta = is_array($conversation->meta) ? $conversation->meta : [];
        $userId = trim((string) ($meta['avito_user_id'] ?? ''));
        if ($userId === '') {
            $userId = trim((string) DB::table('messages')->where('conversation_id', $conversation->id)->where('direction', 'in')
                ->whereRaw("COALESCE(JSON_EXTRACT(payload, '$.account_id'), JSON_EXTRACT(payload, '$.payload.value.user_id')) IS NOT NULL")
                ->orderByDesc('id')
                ->value(DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.account_id')), JSON_UNQUOTE(JSON_EXTRACT(payload, '$.payload.value.user_id')))")));
            if ($userId !== '' && $conversation->exists) {
                $meta['avito_user_id'] = $userId;
                $conversation->forceFill(['meta' => $meta])->saveQuietly();
            }
        }

        return $all->first(fn (IntegrationConnection $c) => $userId !== '' && trim((string) ($c->settings['user_id'] ?? '')) === $userId)
            ?? $all->first();
    }
}

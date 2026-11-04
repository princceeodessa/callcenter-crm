<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use App\Services\Chat\MessageContent;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'conversation_id',
        'direction',
        'author',
        'body',
        'external_id',
        'payload',
        'status',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    protected static function booted(): void
    {
        // фото, голосовое, ссылка, стикер… без текста — подпись и вложения из сообщения площадки (иначе пустой пузырь)
        static::saving(fn (Message $message) => MessageContent::fill($message));

        // входящее Авито несёт id кабинета (опрос — payload.account_id, вебхук — payload.payload.value.user_id): чат
        // запоминает свой кабинет — по нему отвечаем, читаем историю и выделяем «частника» (связанный кабинет, 03.11.2026)
        static::created(function (Message $message) {
            if ($message->direction !== 'in' || ! is_array($message->payload)) {
                return;
            }
            $userId = trim((string) ($message->payload['account_id'] ?? data_get($message->payload, 'payload.value.user_id') ?? ''));
            if ($userId === '') {
                return;
            }
            $conversation = Conversation::query()->find($message->conversation_id);
            if (! $conversation || $conversation->channel !== 'avito') {
                return;
            }
            $meta = is_array($conversation->meta) ? $conversation->meta : [];
            if (($meta['avito_user_id'] ?? '') !== $userId) {
                $meta['avito_user_id'] = $userId;
                $conversation->forceFill(['meta' => $meta])->saveQuietly();
            }
        });
    }
}

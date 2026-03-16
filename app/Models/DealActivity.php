<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class DealActivity extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'deal_id',
        'author_user_id',
        'type',
        'body',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    protected $appends = [
        'type_label',
    ];

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function getTypeLabelAttribute(): string
    {
        $type = (string) ($this->type ?? '');
        $payload = is_array($this->payload ?? null) ? $this->payload : [];
        $provider = isset($payload['provider']) ? (string) $payload['provider'] : null;

        $provLabel = $provider ? match ($provider) {
            'bitrix' => 'Bitrix',
            'vk' => 'VK',
            'telegram' => 'Telegram',
            'avito' => 'Avito',
            'tilda' => 'Tilda',
            default => strtoupper($provider),
        } : null;

        if ($type === 'comment') {
            return "\u{041A}\u{043E}\u{043C}\u{043C}\u{0435}\u{043D}\u{0442}\u{0430}\u{0440}\u{0438}\u{0439}";
        }

        return match ($type) {
            'message_in' => 'Входящее сообщение'.($provLabel ? " ({$provLabel})" : ''),
            'message_out' => 'Исходящее сообщение'.($provLabel ? " ({$provLabel})" : ''),
            'lead_form' => 'Заявка с формы'.($provLabel ? " ({$provLabel})" : ''),
            'import' => 'Импорт'.($provLabel ? " ({$provLabel})" : ''),
            'bitrix_comment' => 'Комментарий Bitrix',
            'bitrix_task_import' => 'Дело Bitrix',
            'bitrix_sync' => 'Синхронизация Bitrix',
            'call' => 'Звонок',
            'task_created' => 'Создано дело',
            'task_updated' => 'Дело обновлено',
            'task_done' => 'Дело выполнено',
            'stage_changed' => 'Смена стадии',
            'deal_closed' => 'Закрытие сделки',
            'deal_updated' => 'Изменение сделки',
            'system' => 'Система',
            default => $type !== '' ? $type : 'Событие',
        };
    }
}

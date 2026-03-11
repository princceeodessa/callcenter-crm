<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class DealActivity extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id','deal_id','author_user_id',
        'type','body','payload'
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
            'vk' => 'VK',
            'telegram' => 'Telegram',
            'avito' => 'Avito',
            'tilda' => 'Tilda',
            default => strtoupper($provider),
        } : null;

        return match ($type) {
            'message_in' => 'Входящее сообщение'.($provLabel ? " ({$provLabel})" : ''),
            'message_out' => 'Исходящее сообщение'.($provLabel ? " ({$provLabel})" : ''),
            'lead_form' => 'Заявка с формы'.($provLabel ? " ({$provLabel})" : ''),
            'call' => 'Звонок',
            'task_created' => 'Создано дело',
            'task_done' => 'Дело выполнено',
            'stage_changed' => 'Смена стадии',
            'deal_closed' => 'Закрытие сделки',
            'deal_updated' => 'Изменение сделки',
            'system' => 'Система',
            default => $type !== '' ? $type : 'Событие',
        };
    }
}
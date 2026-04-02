<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use BelongsToAccount;

    private const UNASSIGNED_LABEL = 'Всем';

    protected $fillable = [
        'account_id',
        'deal_id',
        'assigned_user_id',
        'title',
        'description',
        'status',
        'due_at',
        'completed_at',
        'external_provider',
        'external_id',
        'external_sync_status',
        'external_sync_error',
        'external_payload',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'notified_at' => 'datetime',
        'completed_at' => 'datetime',
        'external_payload' => 'array',
    ];

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function getAssigneeLabelAttribute(): string
    {
        $name = trim((string) ($this->assignedTo?->name ?? ''));

        if ($name === '' && $this->assigned_user_id) {
            $name = trim((string) ($this->assignedTo()->value('name') ?? ''));
        }

        return $name !== '' ? $name : self::UNASSIGNED_LABEL;
    }

    public function getExternalSyncLabelAttribute(): ?string
    {
        if (($this->external_provider ?? null) !== 'bitrix') {
            return null;
        }

        return match ((string) ($this->external_sync_status ?? '')) {
            'synced' => 'Bitrix: синхронизировано',
            'imported' => 'Bitrix: импортировано',
            'pending' => 'Bitrix: ждёт синхронизации',
            'error' => 'Bitrix: ошибка синхронизации',
            default => 'Bitrix',
        };
    }

    public function getContextLabelAttribute(): ?string
    {
        $payload = is_array($this->external_payload ?? null) ? $this->external_payload : [];

        $contextLabel = trim((string) ($payload['context_label'] ?? ''));
        if ($contextLabel !== '') {
            return $contextLabel;
        }

        $sheetName = trim((string) ($payload['sheet_name'] ?? ''));
        $workbookTitle = trim((string) ($payload['workbook_title'] ?? ''));
        $rowIndex = (int) ($payload['row_index'] ?? 0);

        if ($sheetName === '' && $workbookTitle === '') {
            return null;
        }

        $parts = array_filter([$workbookTitle, $sheetName, $rowIndex > 0 ? 'строка '.$rowIndex : null]);

        return !empty($parts) ? implode(' -> ', $parts) : null;
    }

    public function getContextUrlAttribute(): ?string
    {
        $payload = is_array($this->external_payload ?? null) ? $this->external_payload : [];
        $url = trim((string) ($payload['context_url'] ?? ''));

        return $url !== '' ? $url : null;
    }
}

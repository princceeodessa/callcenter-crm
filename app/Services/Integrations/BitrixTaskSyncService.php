<?php

namespace App\Services\Integrations;

use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Models\Task;
use App\Models\User;

class BitrixTaskSyncService
{
    private const BITRIX_TODO_TYPE_ID = 6;

    public function syncCreatedTask(Task $task, Deal $deal, User $author): void
    {
        $binding = $deal->resolveBitrixBinding();
        if (! $binding) {
            return;
        }

        $connection = $this->resolveConnection($task->account_id);
        if (! $connection) {
            $this->markPending($task, 'РЎРґРµР»РєР° РїСЂРёС€Р»Р° РёР· Bitrix, РЅРѕ РёРЅС‚РµРіСЂР°С†РёСЏ Bitrix РЅРµ РЅР°СЃС‚СЂРѕРµРЅР°.');
            return;
        }

        $settings = is_array($connection->settings) ? $connection->settings : [];

        try {
            $client = new BitrixApiClient((string) ($settings['webhook_url'] ?? ''));
            $requestFields = $this->makeActivityFields($task, $deal, $binding, $settings);
            $response = $client->addActivity($requestFields);
            $activityId = $this->extractActivityId($response);

            $task->forceFill([
                'external_provider' => 'bitrix',
                'external_id' => $activityId,
                'external_sync_status' => 'synced',
                'external_sync_error' => null,
                'external_payload' => $this->mergeExternalPayload($task, [
                    'entity_id' => $binding['entity_id'],
                    'entity_type' => $binding['entity_type'],
                    'owner_type_id' => $binding['owner_type_id'],
                    'bitrix_activity_id' => $activityId,
                    'bitrix_last_action' => 'created',
                ]),
            ])->save();

            $connection->forceFill([
                'status' => 'active',
                'last_error' => null,
                'last_synced_at' => now(),
            ])->save();

            $this->logEvent($task->account_id, 'crm.activity.add', $activityId, [
                'request' => $requestFields,
                'response' => $response,
                'task_id' => $task->id,
                'deal_id' => $deal->id,
            ]);
        } catch (\Throwable $e) {
            $task->forceFill([
                'external_provider' => 'bitrix',
                'external_sync_status' => 'error',
                'external_sync_error' => $e->getMessage(),
                'external_payload' => $this->mergeExternalPayload($task, [
                    'entity_id' => $binding['entity_id'],
                    'entity_type' => $binding['entity_type'],
                    'owner_type_id' => $binding['owner_type_id'],
                    'bitrix_last_action' => 'create_failed',
                ]),
            ])->save();

            $connection->forceFill([
                'status' => 'error',
                'last_error' => $e->getMessage(),
            ])->save();

            $this->logEvent($task->account_id, 'crm.activity.add_error', null, [
                'task_id' => $task->id,
                'deal_id' => $deal->id,
                'error' => $e->getMessage(),
            ]);

            $this->addSyncNote($deal, $author, 'РќРµ СѓРґР°Р»РѕСЃСЊ РѕС‚РїСЂР°РІРёС‚СЊ РґРµР»Рѕ РІ Bitrix: '.$e->getMessage(), [
                'provider' => 'bitrix',
                'task_id' => $task->id,
                'sync_status' => 'error',
            ]);
        }
    }

    public function syncUpdatedTask(Task $task, User $author): void
    {
        $deal = $task->deal ?? $task->deal()->first();
        if (! $deal) {
            return;
        }

        if (($task->external_sync_status ?? '') === 'imported' && empty($task->external_id)) {
            return;
        }

        if (($task->external_provider ?? null) === 'bitrix' && empty($task->external_id)) {
            $this->syncCreatedTask($task, $deal, $author);
            $task->refresh();
        }

        if (($task->external_provider ?? null) !== 'bitrix' || empty($task->external_id)) {
            return;
        }

        $connection = $this->resolveConnection($task->account_id);
        if (! $connection) {
            $this->markPending($task, 'Bitrix интеграция отключена: локальное дело изменено, но данные в Bitrix не обновлены.');
            return;
        }

        $settings = is_array($connection->settings) ? $connection->settings : [];

        try {
            $client = new BitrixApiClient((string) ($settings['webhook_url'] ?? ''));
            $requestFields = $this->makeActivityUpdateFields($task, $deal, $settings);
            $response = $client->updateActivity((string) $task->external_id, $requestFields);

            $task->forceFill([
                'external_sync_status' => 'synced',
                'external_sync_error' => null,
                'external_payload' => $this->mergeExternalPayload($task, [
                    'bitrix_activity_id' => $task->external_id,
                    'bitrix_last_action' => 'updated',
                    'bitrix_updated_at' => now()->toIso8601String(),
                ]),
            ])->save();

            $connection->forceFill([
                'status' => 'active',
                'last_error' => null,
                'last_synced_at' => now(),
            ])->save();

            $this->logEvent($task->account_id, 'crm.activity.update', (string) $task->external_id, [
                'request' => $requestFields,
                'response' => $response,
                'task_id' => $task->id,
                'deal_id' => $deal->id,
            ]);
        } catch (\Throwable $e) {
            $task->forceFill([
                'external_sync_status' => 'error',
                'external_sync_error' => $e->getMessage(),
                'external_payload' => $this->mergeExternalPayload($task, [
                    'bitrix_activity_id' => $task->external_id,
                    'bitrix_last_action' => 'update_failed',
                ]),
            ])->save();

            $connection->forceFill([
                'status' => 'error',
                'last_error' => $e->getMessage(),
            ])->save();

            $this->logEvent($task->account_id, 'crm.activity.update_error', (string) $task->external_id, [
                'task_id' => $task->id,
                'deal_id' => $deal->id,
                'error' => $e->getMessage(),
            ]);

            $this->addSyncNote($deal, $author, 'Не удалось обновить дело в Bitrix: '.$e->getMessage(), [
                'provider' => 'bitrix',
                'task_id' => $task->id,
                'sync_status' => 'error',
                'external_id' => $task->external_id,
            ]);
        }
    }

    public function syncCompletedTask(Task $task, User $author): void
    {
        $deal = $task->deal ?? $task->deal()->first();
        if (! $deal) {
            return;
        }

        if (($task->external_sync_status ?? '') === 'imported' && empty($task->external_id)) {
            return;
        }

        if (($task->external_provider ?? null) === 'bitrix' && empty($task->external_id)) {
            $this->syncCreatedTask($task, $deal, $author);
            $task->refresh();
        }

        if (($task->external_provider ?? null) !== 'bitrix' || empty($task->external_id)) {
            return;
        }

        $connection = $this->resolveConnection($task->account_id);
        if (! $connection) {
            $this->markPending($task, 'Bitrix РёРЅС‚РµРіСЂР°С†РёСЏ РѕС‚РєР»СЋС‡РµРЅР°: Р»РѕРєР°Р»СЊРЅРѕРµ РґРµР»Рѕ Р·Р°РІРµСЂС€РµРЅРѕ, РЅРѕ СЃС‚Р°С‚СѓСЃ РІ Bitrix РЅРµ РѕР±РЅРѕРІР»С‘РЅ.');
            return;
        }

        $settings = is_array($connection->settings) ? $connection->settings : [];

        try {
            $client = new BitrixApiClient((string) ($settings['webhook_url'] ?? ''));
            $requestFields = [
                'COMPLETED' => 'Y',
            ];
            $response = $client->updateActivity((string) $task->external_id, $requestFields);

            $task->forceFill([
                'external_sync_status' => 'synced',
                'external_sync_error' => null,
                'external_payload' => $this->mergeExternalPayload($task, [
                    'bitrix_activity_id' => $task->external_id,
                    'bitrix_last_action' => 'completed',
                    'bitrix_completed_at' => optional($task->completed_at)->toIso8601String(),
                ]),
            ])->save();

            $connection->forceFill([
                'status' => 'active',
                'last_error' => null,
                'last_synced_at' => now(),
            ])->save();

            $this->logEvent($task->account_id, 'crm.activity.update', (string) $task->external_id, [
                'request' => $requestFields,
                'response' => $response,
                'task_id' => $task->id,
                'deal_id' => $deal->id,
            ]);
        } catch (\Throwable $e) {
            $task->forceFill([
                'external_sync_status' => 'error',
                'external_sync_error' => $e->getMessage(),
                'external_payload' => $this->mergeExternalPayload($task, [
                    'bitrix_activity_id' => $task->external_id,
                    'bitrix_last_action' => 'complete_failed',
                ]),
            ])->save();

            $connection->forceFill([
                'status' => 'error',
                'last_error' => $e->getMessage(),
            ])->save();

            $this->logEvent($task->account_id, 'crm.activity.update_error', (string) $task->external_id, [
                'task_id' => $task->id,
                'deal_id' => $deal->id,
                'error' => $e->getMessage(),
            ]);

            $this->addSyncNote($deal, $author, 'РќРµ СѓРґР°Р»РѕСЃСЊ РѕС‚РјРµС‚РёС‚СЊ РґРµР»Рѕ РІС‹РїРѕР»РЅРµРЅРЅС‹Рј РІ Bitrix: '.$e->getMessage(), [
                'provider' => 'bitrix',
                'task_id' => $task->id,
                'sync_status' => 'error',
                'external_id' => $task->external_id,
            ]);
        }
    }

    private function resolveConnection(int $accountId): ?IntegrationConnection
    {
        $connection = IntegrationConnection::query()
            ->where('account_id', $accountId)
            ->where('provider', 'bitrix')
            ->first();

        if (! $connection) {
            return null;
        }

        $settings = is_array($connection->settings) ? $connection->settings : [];
        $webhookUrl = trim((string) ($settings['webhook_url'] ?? ''));

        return $webhookUrl !== '' ? $connection : null;
    }

    private function makeActivityFields(Task $task, Deal $deal, array $binding, array $settings): array
    {
        $fields = [
            'OWNER_TYPE_ID' => (int) $binding['owner_type_id'],
            'OWNER_ID' => (int) $binding['entity_id'],
            // Universal CRM activity. This is the most stable way to mirror a local "РґРµР»Рѕ" to Bitrix.
            'TYPE_ID' => self::BITRIX_TODO_TYPE_ID,
            'SUBJECT' => $task->title,
            'DESCRIPTION' => $this->makeDescription($task, $deal),
            'DESCRIPTION_TYPE' => 1,
            'COMPLETED' => $task->status === 'done' ? 'Y' : 'N',
        ];

        if ($task->due_at) {
            $fields['DEADLINE'] = $task->due_at->format('Y-m-d\TH:i:sP');
        }

        $defaultResponsibleId = (int) ($settings['default_responsible_id'] ?? 0);
        if ($defaultResponsibleId > 0) {
            $fields['RESPONSIBLE_ID'] = $defaultResponsibleId;
        }

        return $fields;
    }

    private function makeActivityUpdateFields(Task $task, Deal $deal, array $settings): array
    {
        $fields = [
            'SUBJECT' => $task->title,
            'DESCRIPTION' => $this->makeDescription($task, $deal),
            'DESCRIPTION_TYPE' => 1,
            'COMPLETED' => $task->status === 'done' ? 'Y' : 'N',
            'DEADLINE' => $task->due_at ? $task->due_at->format('Y-m-d\TH:i:sP') : '',
        ];

        $defaultResponsibleId = (int) ($settings['default_responsible_id'] ?? 0);
        if ($defaultResponsibleId > 0) {
            $fields['RESPONSIBLE_ID'] = $defaultResponsibleId;
        }

        return $fields;
    }

    private function makeDescription(Task $task, Deal $deal): string
    {
        $lines = [
            'Р›РѕРєР°Р»СЊРЅРѕРµ РґРµР»Рѕ CRM #'.$task->id,
            'РЎРґРµР»РєР° CRM #'.$deal->id.': '.$deal->title,
        ];

        if ($task->description) {
            $lines[] = 'РљРѕРјРјРµРЅС‚Р°СЂРёР№: '.$task->description;
        }

        if ($task->due_at) {
            $lines[] = 'РЎСЂРѕРє: '.$task->due_at->format('d.m.Y H:i');
        }

        $assignee = $task->assignee_label;
        if ($assignee !== '') {
            $lines[] = 'РќР°Р·РЅР°С‡РµРЅРѕ: '.$assignee;
        }

        return implode("\n", $lines);
    }

    private function extractActivityId(array $response): string
    {
        $result = $response['result'] ?? null;
        if (is_scalar($result) && trim((string) $result) !== '') {
            return trim((string) $result);
        }

        throw new \RuntimeException('Bitrix РЅРµ РІРµСЂРЅСѓР» ID СЃРѕР·РґР°РЅРЅРѕРіРѕ РґРµР»Р°.');
    }

    private function markPending(Task $task, string $message): void
    {
        $task->forceFill([
            'external_provider' => 'bitrix',
            'external_sync_status' => 'pending',
            'external_sync_error' => $message,
            'external_payload' => $this->mergeExternalPayload($task, [
                'bitrix_last_action' => 'pending',
            ]),
        ])->save();
    }

    private function mergeExternalPayload(Task $task, array $extra): array
    {
        $current = is_array($task->external_payload ?? null) ? $task->external_payload : [];

        return array_merge($current, $extra);
    }

    private function addSyncNote(Deal $deal, User $author, string $body, array $payload): void
    {
        DealActivity::create([
            'account_id' => $deal->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => $author->id,
            'type' => 'bitrix_sync',
            'body' => $body,
            'payload' => $payload,
        ]);
    }

    private function logEvent(int $accountId, string $eventType, ?string $externalId, array $payload): void
    {
        IntegrationEvent::create([
            'account_id' => $accountId,
            'provider' => 'bitrix',
            'direction' => 'out',
            'event_type' => $eventType,
            'external_id' => $externalId,
            'payload' => $payload,
            'received_at' => now(),
        ]);
    }
}

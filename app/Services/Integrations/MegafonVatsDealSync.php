<?php

namespace App\Services\Integrations;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Models\User;

class MegafonVatsDealSync
{
    /**
     * Convert a VATS call event to a Deal + DealActivity.
     *
     * - De-duplicates deals by client phone (one active deal per phone).
     * - De-duplicates activities by (callid + cmd/type + start).
     * - Attaches recording link when present in payload.
     */
    public static function handle(IntegrationConnection $connection, IntegrationEvent $event): void
    {
        $accountId = $connection->account_id;
        $p = $event->payload ?? [];

        // Pick client phone (best-effort for MegaFon VATS payloads)
        $clientPhoneRaw = self::firstString($p, [
            'telnum',          // external number
            'diversion',       // sometimes external number is duplicated here
            'phone_client',
            'client_phone',
            'from',
            'caller',
        ]);

        $clientPhone = self::normalizePhone($clientPhoneRaw);
        if (!$clientPhone) {
            // No client phone -> don't create a deal
            return;
        }

        $contact = Contact::query()->firstOrCreate(
            ['account_id' => $accountId, 'phone' => $clientPhone],
            ['name' => 'Клиент '.$clientPhone]
        );

        // Find existing open deal for this contact (prevents duplicates)
        $deal = Deal::query()
            ->where('account_id', $accountId)
            ->where('contact_id', $contact->id)
            ->whereNull('closed_at')
            ->orderByDesc('id')
            ->first();

        if (!$deal) {
            $stage = PipelineStage::query()
                ->where('account_id', $accountId)
                ->orderBy('sort')
                ->first();

            if (!$stage) {
                // Pipelines not seeded? Try default pipeline.
                $pipeline = Pipeline::query()->where('account_id', $accountId)->where('is_default', 1)->first();
                $stage = $pipeline
                    ? PipelineStage::query()->where('pipeline_id', $pipeline->id)->orderBy('sort')->first()
                    : null;
            }

            if (!$stage) {
                return;
            }

            $responsible = User::query()->where('account_id', $accountId)->orderBy('id')->first();

            $deal = Deal::create([
                'account_id' => $accountId,
                'pipeline_id' => $stage->pipeline_id,
                'stage_id' => $stage->id,
                'title' => 'Звонки: '.$clientPhone,
                'contact_id' => $contact->id,
                'responsible_user_id' => $responsible?->id,
                'is_unread' => true,
            ]);

            DealActivity::create([
                'account_id' => $accountId,
                'deal_id' => $deal->id,
                'author_user_id' => null,
                'type' => 'system',
                'body' => 'Сделка создана автоматически из звонка (МегаФон ВАТС).',
            ]);
        }

        // De-duplicate call activities
        $callId = self::firstString($p, ['callid', 'call_id', 'callId', 'uuid', 'id']) ?? $event->external_id;
        $cmd = self::firstString($p, ['cmd']);
        $type = self::firstString($p, ['type']);
        $start = self::firstString($p, ['start']);

        $exists = DealActivity::query()
            ->where('deal_id', $deal->id)
            ->where('type', 'call')
            ->when($callId, fn($q) => $q->where('payload->callid', (string)$callId))
            ->when($cmd, fn($q) => $q->where('payload->cmd', (string)$cmd))
            ->when($type, fn($q) => $q->where('payload->type', (string)$type))
            ->when($start, fn($q) => $q->where('payload->start', (string)$start))
            ->exists();

        if ($exists) {
            return;
        }

        $status = self::firstString($p, ['status']);
        $duration = self::firstString($p, ['duration']);
        $employee = self::firstString($p, ['user']);
        $employeePhone = self::normalizePhone(self::firstString($p, ['phone']));
        $telnum = self::normalizePhone(self::firstString($p, ['telnum']));
        $diversion = self::normalizePhone(self::firstString($p, ['diversion']));

        $recordingUrl = self::findRecordingUrl($p);

        $parts = [];
        $parts[] = 'Звонок (МегаФон ВАТС)';
        if ($type) $parts[] = 'тип: '.$type;
        if ($status) $parts[] = 'статус: '.$status;
        if ($duration !== null && $duration !== '') $parts[] = 'длительность: '.$duration.' c';
        if ($employee) $parts[] = 'сотрудник: '.$employee;
        if ($employeePhone) $parts[] = 'внутр.: '.$employeePhone;
        if ($telnum) $parts[] = 'клиент: '.$telnum;
        if ($diversion && $diversion !== $telnum) $parts[] = 'переадр.: '.$diversion;
        if ($callId) $parts[] = 'callid: '.$callId;
        if ($recordingUrl) $parts[] = 'запись: '.$recordingUrl;

        $payload = $p;
        if ($callId) $payload['callid'] = (string)$callId;
        if ($recordingUrl) $payload['recording_url'] = $recordingUrl;
        $payload['integration_event_id'] = $event->id;

        DealActivity::create([
            'account_id' => $accountId,
            'deal_id' => $deal->id,
            'author_user_id' => null,
            'type' => 'call',
            'body' => implode(' | ', $parts),
            'payload' => $payload,
        ]);

        // Optional: create a callback task for missed calls
        if ($status === 'missed') {
            $taskExists = Task::query()
                ->where('deal_id', $deal->id)
                ->where('status', 'open')
                ->where('title', 'like', '%Перезвонить%')
                ->whereDate('created_at', now()->toDateString())
                ->exists();

            if (!$taskExists) {
                Task::create([
                    'account_id' => $accountId,
                    'deal_id' => $deal->id,
                    'assigned_user_id' => $deal->responsible_user_id,
                    'title' => 'Перезвонить: '.$clientPhone,
                    'description' => $recordingUrl ? ('Запись: '.$recordingUrl) : null,
                    'status' => 'open',
                    'due_at' => now()->addMinutes(10),
                ]);
            }

            $deal->is_unread = true;
            $deal->save();
        }
    }

    private static function firstString(array $payload, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $payload) && is_scalar($payload[$k]) && (string)$payload[$k] !== '') {
                return (string)$payload[$k];
            }
        }
        return null;
    }

    private static function normalizePhone(?string $phone): ?string
    {
        if (!$phone) return null;
        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits) return null;

        // RU normalization: 8XXXXXXXXXX -> 7XXXXXXXXXX
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7'.substr($digits, 1);
        }
        // 10 digits -> assume RU
        if (strlen($digits) === 10) {
            $digits = '7'.$digits;
        }

        return $digits;
    }

    private static function findRecordingUrl(array $payload): ?string
    {
        $keys = [
            'recording_url',
            'record_url',
            'recordUrl',
            'record',
            'recording',
            'recordLink',
            'record_link',
            'file',
            'file_url',
            'url',
        ];

        foreach ($keys as $k) {
            if (!array_key_exists($k, $payload)) continue;
            $v = $payload[$k];
            if (is_string($v) && preg_match('#^https?://#i', $v)) {
                return $v;
            }
        }

        // Some APIs nest recording under a subobject
        foreach (['recording', 'record', 'call', 'data'] as $sub) {
            if (isset($payload[$sub]) && is_array($payload[$sub])) {
                $url = self::findRecordingUrl($payload[$sub]);
                if ($url) return $url;
            }
        }

        return null;
    }
}

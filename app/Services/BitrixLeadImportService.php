<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\DealStageHistory;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class BitrixLeadImportService
{
    private const SUPPORTED_EXTENSIONS = ['csv', 'xlsx'];

    private const MULTI_VALUE_FIELDS = ['phone', 'email', 'comments'];

    private const HEADER_ALIASES = [
        'lead_id' => ['id', 'ид', 'id лида', 'ид лида', 'lead id', 'leadid', 'crm id'],
        'title' => ['название', 'название лида', 'заголовок', 'тема', 'title', 'lead title'],
        'contact_name' => ['контакт', 'фио', 'имя контакта', 'название контакта', 'contact', 'contact name'],
        'first_name' => ['имя', 'контактимя', 'firstname', 'first name', 'name'],
        'last_name' => ['фамилия', 'контактфамилия', 'lastname', 'last name', 'surname'],
        'middle_name' => ['отчество', 'контактотчество', 'middlename', 'middle name', 'second name'],
        'phone' => [
            'телефон',
            'мобильный телефон',
            'рабочий телефон',
            'контактрабочийтелефон',
            'контактмобильныйтелефон',
            'контакттелефондлярассылок',
            'контактдругойтелефон',
            'phone',
            'mobile phone',
            'work phone',
        ],
        'email' => [
            'email',
            'e-mail',
            'e mail',
            'почта',
            'электронная почта',
            'контактрабочийemail',
            'контактчастныйemail',
            'контактemailдлярассылок',
            'контактдругойemail',
        ],
        'status' => ['статус', 'статус лида', 'стадия', 'status', 'status name', 'statusname'],
        'responsible' => ['ответственный', 'менеджер', 'responsible', 'responsible name', 'assigned by', 'assigned by name', 'assignedbyname'],
        'amount' => ['сумма', 'бюджет', 'стоимость', 'amount', 'opportunity', 'price'],
        'currency' => ['валюта', 'currency'],
        'source' => ['источник', 'источник лида', 'канал', 'source', 'source name', 'sourcename'],
        'comments' => ['комментарий', 'комментарии', 'описание', 'примечание', 'details', 'comments', 'comment', 'description', 'additional info', 'дополнительно о контакте'],
        'created_at' => ['дата создания', 'создано', 'created at', 'created time', 'createdtime', 'date create', 'creation date'],
    ];

    public function importFromUploadedFile(
        UploadedFile $file,
        int $accountId,
        int $userId,
        int $defaultStageId,
        int $defaultResponsibleUserId
    ): array {
        $originalName = $file->getClientOriginalName() ?: 'bitrix-import';

        return $this->importFromPath(
            $file->getRealPath(),
            $originalName,
            $accountId,
            $userId,
            $defaultStageId,
            $defaultResponsibleUserId
        );
    }

    public function importFromPath(
        string $path,
        string $originalName,
        int $accountId,
        int $userId,
        int $defaultStageId,
        int $defaultResponsibleUserId
    ): array {
        $parsed = $this->parseFile($path, $originalName);
        $rows = $parsed['rows'];

        if (count($rows) === 0) {
            throw new RuntimeException('Р В Р’В Р РЋРЎС™Р В Р’В Р вЂ™Р’Вµ Р В Р Р‹Р РЋРІР‚СљР В Р’В Р СћРІР‚ВР В Р’В Р вЂ™Р’В°Р В Р’В Р вЂ™Р’В»Р В Р’В Р РЋРІР‚СћР В Р Р‹Р В РЎвЂњР В Р Р‹Р В Р вЂ° Р В Р’В Р В РІР‚В¦Р В Р’В Р вЂ™Р’В°Р В Р’В Р Р†РІР‚С›РІР‚вЂњР В Р Р‹Р Р†Р вЂљРЎв„ўР В Р’В Р РЋРІР‚В Р В Р Р‹Р В РЎвЂњР В Р Р‹Р Р†Р вЂљРЎв„ўР В Р Р‹Р В РІР‚С™Р В Р’В Р РЋРІР‚СћР В Р’В Р РЋРІР‚СњР В Р’В Р РЋРІР‚В Р В Р Р‹Р В РЎвЂњ Р В Р’В Р вЂ™Р’В»Р В Р’В Р РЋРІР‚ВР В Р’В Р СћРІР‚ВР В Р’В Р вЂ™Р’В°Р В Р’В Р РЋР’ВР В Р’В Р РЋРІР‚В Р В Р’В Р СћРІР‚ВР В Р’В Р вЂ™Р’В»Р В Р Р‹Р В Р РЏ Р В Р’В Р РЋРІР‚ВР В Р’В Р РЋР’ВР В Р’В Р РЋРІР‚вЂќР В Р’В Р РЋРІР‚СћР В Р Р‹Р В РІР‚С™Р В Р Р‹Р Р†Р вЂљРЎв„ўР В Р’В Р вЂ™Р’В°.');
        }

        $defaultStage = PipelineStage::query()
            ->where('account_id', $accountId)
            ->findOrFail($defaultStageId);

        $defaultResponsible = User::query()
            ->where('account_id', $accountId)
            ->where('is_active', true)
            ->findOrFail($defaultResponsibleUserId);

        $stages = PipelineStage::query()
            ->where('account_id', $accountId)
            ->orderBy('sort')
            ->get(['id', 'pipeline_id', 'name']);

        $users = User::query()
            ->where('account_id', $accountId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $imported = 0;
        $duplicates = 0;
        $failed = 0;
        $matchedStages = 0;
        $matchedResponsibles = 0;
        $failedMessages = [];

        foreach ($rows as $row) {
            $leadId = $this->nullIfBlank($row['lead_id'] ?? null);
            $contactName = $this->composeContactName($row);
            $phone = $this->extractPhone($row['phone'] ?? null);
            $email = $this->extractEmail($row['email'] ?? null);
            $title = $this->makeDealTitle($row, $contactName, $phone, $email, $leadId);
            $importHash = $this->buildImportHash($accountId, $leadId, $title, $phone, $email, $row['created_at'] ?? null);

            if ($this->hasImportedRow($accountId, $leadId, $importHash)) {
                $duplicates++;
                continue;
            }

            try {
                $resolvedStage = $this->resolveStage($row['status'] ?? null, $defaultStage, $stages);
                $resolvedResponsible = $this->resolveResponsible($row['responsible'] ?? null, $defaultResponsible, $users);
                $createdAt = $this->parseDate($row['created_at'] ?? null);
                $amount = $this->parseAmount($row['amount'] ?? null);
                $currency = $this->normalizeCurrency($row['currency'] ?? null);
                $activityBody = $this->buildActivityBody($row, $leadId);

                DB::transaction(function () use (
                    $accountId,
                    $userId,
                    $row,
                    $contactName,
                    $phone,
                    $email,
                    $title,
                    $leadId,
                    $importHash,
                    $createdAt,
                    $amount,
                    $currency,
                    $resolvedStage,
                    $resolvedResponsible,
                    $users,
                    $activityBody,
                    &$imported,
                    &$matchedStages,
                    &$matchedResponsibles
                ) {
                    $contact = $this->resolveContact($accountId, $contactName, $phone, $email);

                    $deal = Deal::create([
                        'account_id' => $accountId,
                        'pipeline_id' => $resolvedStage['stage']->pipeline_id,
                        'stage_id' => $resolvedStage['stage']->id,
                        'title' => $title,
                        'title_is_custom' => true,
                        'contact_id' => $contact?->id,
                        'responsible_user_id' => $resolvedResponsible['user']->id,
                        'amount' => $amount,
                        'currency' => $currency,
                    ]);

                    if ($createdAt) {
                        $this->applyTimestamps($deal, $createdAt);
                    }

                    $activity = DealActivity::create([
                        'account_id' => $accountId,
                        'deal_id' => $deal->id,
                        'author_user_id' => $userId,
                        'type' => 'import',
                        'body' => $activityBody,
                        'payload' => [
                            'provider' => 'bitrix',
                            'bitrix_lead_id' => $leadId,
                            'bitrix_entity_id' => $leadId,
                            'bitrix_entity_type' => 'lead',
                            'import_hash' => $importHash,
                            'source_sheet' => $row['_sheet'] ?? null,
                            'source_row' => $row['_row'] ?? null,
                            'bitrix_status' => $this->nullIfBlank($row['status'] ?? null),
                            'bitrix_source' => $this->nullIfBlank($row['source'] ?? null),
                            'bitrix_responsible' => $this->nullIfBlank($row['responsible'] ?? null),
                            'bitrix_created_at' => $createdAt?->toIso8601String(),
                        ],
                    ]);

                    if ($createdAt) {
                        $this->applyTimestamps($activity, $createdAt);
                    }

                    $this->createBitrixCommentActivity($accountId, $deal->id, $userId, $row, $createdAt);
                    $this->createImportedTask($accountId, $deal->id, $userId, $row, $resolvedResponsible['user'], $users, $createdAt);

                    DealStageHistory::create([
                        'account_id' => $accountId,
                        'deal_id' => $deal->id,
                        'from_stage_id' => null,
                        'to_stage_id' => $resolvedStage['stage']->id,
                        'changed_by_user_id' => $userId,
                        'changed_at' => $createdAt ?? now(),
                    ]);

                    $imported++;

                    if ($resolvedStage['matched']) {
                        $matchedStages++;
                    }

                    if ($resolvedResponsible['matched']) {
                        $matchedResponsibles++;
                    }
                });
            } catch (\Throwable $e) {
                $failed++;

                if (count($failedMessages) < 5) {
                    $failedMessages[] = sprintf(
                        'Строка %s: %s',
                        $this->rowLabel($row),
                        $e->getMessage()
                    );
                }
            }
        }

        if ($imported === 0 && $duplicates === 0 && $failed > 0) {
            throw new RuntimeException(implode(' ', $failedMessages));
        }

        return [
            'imported' => $imported,
            'duplicates' => $duplicates,
            'failed' => $failed,
            'blank_rows' => $parsed['blank_rows'],
            'total' => count($rows) + $parsed['blank_rows'],
            'matched_stages' => $matchedStages,
            'matched_responsibles' => $matchedResponsibles,
            'failed_messages' => $failedMessages,
        ];
    }

    private function parseFile(string $path, string $originalName): array
    {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            throw new RuntimeException('Поддерживаются только файлы CSV и XLSX.');
        }

        return match ($extension) {
            'csv' => $this->parseCsvFile($path),
            'xlsx' => $this->parseXlsxFile($path),
            default => throw new RuntimeException('Формат файла не поддерживается.'),
        };
    }

    private function parseCsvFile(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Не удалось открыть CSV-файл.');
        }

        $sample = fread($handle, 4096) ?: '';
        rewind($handle);

        $delimiter = $this->detectCsvDelimiter($sample);
        $rows = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($row === [null]) {
                continue;
            }

            $rows[] = array_map(fn ($value) => $this->cleanCell($value), $row);
        }

        fclose($handle);

        return $this->extractRecords(['CSV' => $rows]);
    }

    private function parseXlsxFile(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('В PHP не включено расширение zip. Включите extension=zip.');
        }

        if (!class_exists(\SimpleXMLElement::class)) {
            throw new RuntimeException('В PHP не включено XML-расширение. Включите extension=simplexml / extension=xml.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Не удалось открыть XLSX-файл.');
        }

        try {
            $sharedStrings = $this->loadSharedStrings($zip);
            $sheetFiles = $this->sheetFiles($zip);
            $sheets = [];

            foreach ($sheetFiles as $sheetName => $sheetPath) {
                $xmlString = $zip->getFromName($sheetPath);
                if ($xmlString === false) {
                    continue;
                }

                $sheets[$sheetName] = $this->parseSheetRows($xmlString, $sharedStrings);
            }
        } finally {
            $zip->close();
        }

        return $this->extractRecords($sheets);
    }

    private function extractRecords(array $sheets): array
    {
        $records = [];
        $blankRows = 0;

        foreach ($sheets as $sheetName => $rows) {
            $headerMap = null;

            foreach ($rows as $index => $cells) {
                if ($headerMap === null) {
                    $headerMap = $this->detectHeaderMap($cells);
                    continue;
                }

                $record = $this->mapRow($cells, $headerMap, (string) $sheetName, $index + 1);
                if ($record === null) {
                    $blankRows++;
                    continue;
                }

                $records[] = $record;
            }
        }

        if (count($records) === 0) {
            throw new RuntimeException('Не удалось распознать таблицу Bitrix. Нужны колонки вроде ID, Название, Телефон, E-mail, Статус или Ответственный.');
        }

        return [
            'rows' => $records,
            'blank_rows' => $blankRows,
        ];
    }

    private function detectHeaderMap(array $cells): ?array
    {
        $map = [];
        $aliasesByField = $this->normalizedHeaderAliases();

        foreach ($cells as $index => $cell) {
            $normalizedCell = $this->normalizeHeader($cell);
            if ($normalizedCell === '') {
                continue;
            }

            $bestField = null;
            $bestScore = -1;

            foreach ($aliasesByField as $field => $aliases) {
                foreach ($aliases as $alias) {
                    if (!$this->headerMatches($normalizedCell, $alias)) {
                        continue;
                    }

                    $score = strlen($alias);
                    if ($score > $bestScore) {
                        $bestField = $field;
                        $bestScore = $score;
                    }
                }
            }

            if ($bestField === null) {
                continue;
            }

            $map[$bestField] ??= [];
            $map[$bestField][] = $index;
        }

        $recognized = count($map);
        $hasCoreField = !empty($map['title'])
            || !empty($map['contact_name'])
            || !empty($map['first_name'])
            || !empty($map['last_name'])
            || !empty($map['phone'])
            || !empty($map['email']);

        if ($recognized < 3 || !$hasCoreField) {
            return null;
        }

        return $map;
    }
    private function mapRow(array $cells, array $headerMap, string $sheetName, int $rowNumber): ?array
    {
        $record = [
            '_sheet' => $sheetName,
            '_row' => $rowNumber,
        ];

        foreach (array_keys(self::HEADER_ALIASES) as $field) {
            $indexes = $headerMap[$field] ?? [];

            $record[$field] = in_array($field, self::MULTI_VALUE_FIELDS, true)
                ? $this->joinValues($cells, $indexes)
                : $this->firstValue($cells, $indexes);
        }

        if (!$this->isMeaningfulRow($record)) {
            return null;
        }

        return $record;
    }

    private function resolveStage(?string $status, PipelineStage $defaultStage, $stages): array
    {
        $normalizedStatus = $this->normalizeLookup($status);
        if ($normalizedStatus === '') {
            return ['stage' => $defaultStage, 'matched' => false];
        }

        foreach ($stages as $stage) {
            if ($normalizedStatus === $this->normalizeLookup($stage->name)) {
                return ['stage' => $stage, 'matched' => true];
            }
        }

        foreach ($stages as $stage) {
            $normalizedStage = $this->normalizeLookup($stage->name);
            if ($normalizedStage !== ''
                && (str_contains($normalizedStatus, $normalizedStage) || str_contains($normalizedStage, $normalizedStatus))) {
                return ['stage' => $stage, 'matched' => true];
            }
        }

        return ['stage' => $defaultStage, 'matched' => false];
    }

    private function resolveResponsible(?string $responsibleName, User $defaultResponsible, $users): array
    {
        $normalizedResponsible = $this->normalizeLookup($responsibleName);
        if ($normalizedResponsible === '') {
            return ['user' => $defaultResponsible, 'matched' => false];
        }

        foreach ($users as $user) {
            if ($normalizedResponsible === $this->normalizeLookup($user->name)) {
                return ['user' => $user, 'matched' => true];
            }
        }

        foreach ($users as $user) {
            $normalizedUser = $this->normalizeLookup($user->name);
            if ($normalizedUser !== ''
                && (str_contains($normalizedResponsible, $normalizedUser) || str_contains($normalizedUser, $normalizedResponsible))) {
                return ['user' => $user, 'matched' => true];
            }
        }

        return ['user' => $defaultResponsible, 'matched' => false];
    }

    private function resolveContact(int $accountId, ?string $contactName, ?string $phone, ?string $email): ?Contact
    {
        if (!$contactName && !$phone && !$email) {
            return null;
        }

        $contact = null;

        if ($phone) {
            $contact = Contact::query()
                ->where('account_id', $accountId)
                ->where('phone', $phone)
                ->first();
        }

        if (!$contact && $email) {
            $contact = Contact::query()
                ->where('account_id', $accountId)
                ->where('email', $email)
                ->first();
        }

        if (!$contact) {
            return Contact::create([
                'account_id' => $accountId,
                'name' => $contactName,
                'phone' => $phone,
                'email' => $email,
            ]);
        }

        $updates = [];

        if (!$contact->name && $contactName) {
            $updates['name'] = $contactName;
        }

        if (!$contact->phone && $phone) {
            $updates['phone'] = $phone;
        }

        if (!$contact->email && $email) {
            $updates['email'] = $email;
        }

        if (!empty($updates)) {
            $contact->fill($updates)->save();
        }

        return $contact;
    }

    private function hasImportedRow(int $accountId, ?string $leadId, string $importHash): bool
    {
        return DealActivity::query()
            ->where('account_id', $accountId)
            ->where('type', 'import')
            ->where('payload->provider', 'bitrix')
            ->where(function ($query) use ($leadId, $importHash) {
                if ($leadId) {
                    $query->where('payload->bitrix_lead_id', $leadId)
                        ->orWhere('payload->import_hash', $importHash);

                    return;
                }

                $query->where('payload->import_hash', $importHash);
            })
            ->exists();
    }

    private function buildImportHash(
        int $accountId,
        ?string $leadId,
        string $title,
        ?string $phone,
        ?string $email,
        ?string $createdAt
    ): string {
        return sha1(implode('|', [
            'bitrix',
            $accountId,
            $leadId ?: '',
            $this->normalizePhone($phone),
            mb_strtolower(trim((string) ($email ?? '')), 'UTF-8'),
            mb_strtolower(trim($title), 'UTF-8'),
            trim((string) ($createdAt ?? '')),
        ]));
    }

    private function makeDealTitle(array $row, ?string $contactName, ?string $phone, ?string $email, ?string $leadId): string
    {
        $title = $this->nullIfBlank($row['title'] ?? null);
        if ($title) {
            return $title;
        }

        if ($contactName) {
            return $contactName.' - Bitrix';
        }

        if ($phone) {
            return 'Клиент '.$phone.' - Bitrix';
        }

        if ($email) {
            return $email.' - Bitrix';
        }

        if ($leadId) {
            return 'Лид #'.$leadId.' - Bitrix';
        }

        return 'Лид из Bitrix';
    }

    private function buildActivityBody(array $row, ?string $leadId): string
    {
        $lines = ['Импортировано из Bitrix'];

        if ($leadId) {
            $lines[] = 'ID лида: '.$leadId;
        }

        foreach ([
            'status' => 'Статус Bitrix',
            'source' => 'Источник',
            'responsible' => 'Ответственный в Bitrix',
            'comments' => 'Комментарий',
        ] as $field => $label) {
            $value = $this->nullIfBlank($row[$field] ?? null);
            if ($value) {
                $lines[] = $label.': '.$value;
            }
        }

        if ($createdAt = $this->parseDate($row['created_at'] ?? null)) {
            $lines[] = 'Дата создания: '.$createdAt->format('d.m.Y H:i');
        }

        return implode("\n", $lines);
    }

    private function composeContactName(array $row): ?string
    {
        $direct = $this->nullIfBlank($row['contact_name'] ?? null);
        if ($direct) {
            return $direct;
        }

        $parts = array_filter([
            $this->nullIfBlank($row['last_name'] ?? null),
            $this->nullIfBlank($row['first_name'] ?? null),
            $this->nullIfBlank($row['middle_name'] ?? null),
        ]);

        if (empty($parts)) {
            return null;
        }

        return implode(' ', $parts);
    }

    private function createBitrixCommentActivity(int $accountId, int $dealId, int $userId, array $row, ?Carbon $createdAt): void
    {
        $comment = $this->nullIfBlank($row['comments'] ?? null);
        if ($comment === null) {
            return;
        }

        $activity = DealActivity::create([
            'account_id' => $accountId,
            'deal_id' => $dealId,
            'author_user_id' => $userId,
            'type' => 'bitrix_comment',
            'body' => $comment,
            'payload' => [
                'provider' => 'bitrix',
                'source_sheet' => $row['_sheet'] ?? null,
                'source_row' => $row['_row'] ?? null,
            ],
        ]);

        if ($createdAt) {
            $this->applyTimestamps($activity, $createdAt);
        }
    }

    private function createImportedTask(
        int $accountId,
        int $dealId,
        int $userId,
        array $row,
        User $defaultResponsible,
        $users,
        ?Carbon $createdAt
    ): void {
        if (! $this->hasImportedTaskData($row)) {
            return;
        }

        $title = $this->buildImportedTaskTitle($row);
        if ($title === null) {
            return;
        }

        $resolvedResponsible = $this->resolveResponsible($row['task_responsible'] ?? null, $defaultResponsible, $users);
        $description = $this->nullIfBlank($row['task_description'] ?? null);
        $dueAt = $this->parseDate($row['task_due_at'] ?? null);
        $completed = $this->isCompletedTaskStatus($row['task_completed'] ?? null);

        $task = Task::create([
            'account_id' => $accountId,
            'deal_id' => $dealId,
            'assigned_user_id' => $resolvedResponsible['user']->id,
            'title' => $title,
            'description' => $description,
            'status' => $completed ? 'done' : 'open',
            'due_at' => $dueAt,
            'completed_at' => $completed ? ($dueAt ?? $createdAt ?? now()) : null,
            'external_provider' => 'bitrix',
            'external_sync_status' => 'imported',
            'external_payload' => [
                'source' => 'bitrix_file_import',
                'source_sheet' => $row['_sheet'] ?? null,
                'source_row' => $row['_row'] ?? null,
            ],
        ]);

        if ($createdAt) {
            $this->applyTimestamps($task, $createdAt);
        }

        $activityBody = 'РРјРїРѕСЂС‚РёСЂРѕРІР°РЅРѕ РґРµР»Рѕ РёР· Bitrix: '.$task->title;
        if ($description) {
            $activityBody .= "\n".$description;
        }

        $activity = DealActivity::create([
            'account_id' => $accountId,
            'deal_id' => $dealId,
            'author_user_id' => $userId,
            'type' => 'bitrix_task_import',
            'body' => $activityBody,
            'payload' => [
                'provider' => 'bitrix',
                'task_id' => $task->id,
                'source_sheet' => $row['_sheet'] ?? null,
                'source_row' => $row['_row'] ?? null,
            ],
        ]);

        if ($createdAt) {
            $this->applyTimestamps($activity, $createdAt);
        }
    }

    private function hasImportedTaskData(array $row): bool
    {
        foreach (['task_title', 'task_description', 'task_due_at', 'task_responsible', 'task_completed'] as $field) {
            if ($this->nullIfBlank($row[$field] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    private function buildImportedTaskTitle(array $row): ?string
    {
        $title = $this->nullIfBlank($row['task_title'] ?? null);
        if ($title !== null) {
            return $title;
        }

        $description = $this->nullIfBlank($row['task_description'] ?? null);
        if ($description === null) {
            return null;
        }

        $firstLine = trim((string) preg_split('/\R/u', $description, 2)[0]);
        if ($firstLine === '') {
            return null;
        }

        return mb_strlen($firstLine, 'UTF-8') > 120
            ? mb_substr($firstLine, 0, 117, 'UTF-8').'...'
            : $firstLine;
    }

    private function isCompletedTaskStatus(?string $value): bool
    {
        $normalized = $this->normalizeLookup($value);

        if ($normalized === '') {
            return false;
        }

        foreach (['done', 'complete', 'completed', 'success', 'finished', 'РІС‹РїРѕР»РЅРµРЅРѕ', 'Р·Р°РІРµСЂС€РµРЅРѕ', 'Р·Р°РєСЂС‹С‚Рѕ'] as $candidate) {
            if ($normalized === $this->normalizeLookup($candidate)) {
                return true;
            }
        }

        return false;
    }

    private function parseAmount(?string $value): ?float
    {
        $value = $this->nullIfBlank($value);
        if ($value === null) {
            return null;
        }

        $normalized = str_replace(["\xc2\xa0", ' '], '', $value);
        $normalized = preg_replace('/[^0-9,.\-]+/u', '', $normalized) ?? '';

        if ($normalized === '' || $normalized === '-' || $normalized === ',' || $normalized === '.') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace(',', '', $normalized);
        } else {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    private function normalizeCurrency(?string $value): string
    {
        $value = mb_strtoupper(trim((string) $value), 'UTF-8');

        return match ($value) {
            '', 'RUR', 'РУБ', 'РУБ.', 'RUB.' => 'RUB',
            'USD', '$' => 'USD',
            'EUR', '€' => 'EUR',
            default => preg_match('/^[A-Z]{3}$/', $value) === 1 ? $value : 'RUB',
        };
    }

    private function parseDate(?string $value): ?Carbon
    {
        $value = $this->nullIfBlank($value);
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;
            if ($numeric > 20000 && $numeric < 60000) {
                return Carbon::create(1899, 12, 30, 0, 0, 0)->addDays((int) floor($numeric));
            }
        }

        foreach (['d.m.Y H:i:s', 'd.m.Y H:i', 'd.m.Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', DATE_ATOM] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);
                if ($parsed !== false) {
                    return $parsed;
                }
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractPhone(?string $value): ?string
    {
        $value = $this->nullIfBlank($value);
        if ($value === null) {
            return null;
        }

        if (preg_match('/\+?\d[\d\s\-\(\)]{5,}\d/u', $value, $matches) === 1) {
            return trim($matches[0]);
        }

        return $value;
    }

    private function extractEmail(?string $value): ?string
    {
        $value = $this->nullIfBlank($value);
        if ($value === null) {
            return null;
        }

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $value, $matches) === 1) {
            return mb_strtolower(trim($matches[0]), 'UTF-8');
        }

        return mb_strtolower($value, 'UTF-8');
    }

    private function applyTimestamps(Model $model, Carbon $moment): void
    {
        $model->timestamps = false;
        $model->forceFill([
            'created_at' => $moment,
            'updated_at' => $moment,
        ])->saveQuietly();
        $model->timestamps = true;
    }

    private function isMeaningfulRow(array $row): bool
    {
        foreach (['lead_id', 'title', 'contact_name', 'first_name', 'last_name', 'phone', 'email', 'status', 'responsible', 'amount', 'comments', 'task_title', 'task_description', 'task_due_at'] as $field) {
            if ($this->nullIfBlank($row[$field] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    private function firstValue(array $cells, array $indexes): ?string
    {
        foreach ($indexes as $index) {
            $value = $this->nullIfBlank($cells[$index] ?? null);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function joinValues(array $cells, array $indexes): ?string
    {
        $values = [];

        foreach ($indexes as $index) {
            $value = $this->nullIfBlank($cells[$index] ?? null);
            if ($value === null) {
                continue;
            }

            $values[] = $value;
        }

        $values = array_values(array_unique($values));

        return empty($values) ? null : implode('; ', $values);
    }

    private function normalizeHeader(?string $value): string
    {
        $value = $this->cleanCell($value);
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';

        return $value;
    }

    private function normalizeLookup(?string $value): string
    {
        $value = $this->cleanCell($value);
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';

        return $value;
    }

    private function normalizePhone(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($value ?? '')) ?? '';

        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7'.substr($digits, 1);
        }

        return $digits;
    }

    private function cleanCell(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $value = (string) $value;
        $value = str_replace("\xEF\xBB\xBF", '', $value);

        $encoding = mb_detect_encoding($value, ['UTF-8', 'Windows-1251', 'CP1251', 'ISO-8859-1'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $converted = @mb_convert_encoding($value, 'UTF-8', $encoding);
            if (is_string($converted)) {
                $value = $converted;
            }
        }

        return trim($value);
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function detectCsvDelimiter(string $sample): string
    {
        $delimiters = [';', ',', "\t"];
        $bestDelimiter = ';';
        $bestScore = -1;

        foreach ($delimiters as $delimiter) {
            $score = substr_count($sample, $delimiter);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }

    private function headerMatches(string $normalizedCell, string $alias): bool
    {
        if ($normalizedCell === $alias) {
            return true;
        }

        if (in_array($alias, ["\u{043a}\u{043e}\u{043d}\u{0442}\u{0430}\u{043a}\u{0442}", 'contact'], true)) {
            return false;
        }

        return strlen($alias) >= 4 && str_starts_with($normalizedCell, $alias);
    }
    private function normalizedHeaderAliases(): array
    {
        static $normalized = null;

        if ($normalized !== null) {
            return $normalized;
        }

        $normalized = [];

        foreach (self::HEADER_ALIASES as $field => $aliases) {
            $normalized[$field] = array_map(fn ($alias) => $this->normalizeHeader($alias), $aliases);
        }

        return $normalized;
    }

    private function rowLabel(array $row): string
    {
        $sheet = $row['_sheet'] ?? '?';
        $line = $row['_row'] ?? '?';

        return $sheet.' / '.$line;
    }

    private function loadSharedStrings(ZipArchive $zip): array
    {
        $xmlString = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlString === false) {
            return [];
        }

        $xml = simplexml_load_string($xmlString);
        if (!$xml) {
            return [];
        }

        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $strings = [];

        foreach ($xml->children($ns)->si as $si) {
            $parts = [];

            if (isset($si->t)) {
                $parts[] = (string) $si->t;
            }

            foreach ($si->r as $run) {
                if (isset($run->t)) {
                    $parts[] = (string) $run->t;
                }
            }

            $strings[] = $this->cleanCell(implode('', $parts));
        }

        return $strings;
    }

    private function sheetFiles(ZipArchive $zip): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relsXml === false) {
            return [];
        }

        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);

        if (!$workbook || !$rels) {
            return [];
        }

        $rels->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $relMap = [];

        foreach ($rels->xpath('//r:Relationship') ?: [] as $rel) {
            $attrs = $rel->attributes();
            $relMap[(string) $attrs['Id']] = 'xl/'.ltrim((string) $attrs['Target'], '/');
        }

        $workbook->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $result = [];

        foreach ($workbook->xpath('//a:sheets/a:sheet') ?: [] as $sheet) {
            $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $rid = (string) $attrs['id'];
            $name = (string) $sheet['name'];

            if (isset($relMap[$rid])) {
                $result[$name] = $relMap[$rid];
            }
        }

        return $result;
    }

    private function parseSheetRows(string $xmlString, array $sharedStrings): array
    {
        $xml = simplexml_load_string($xmlString);
        if (!$xml) {
            return [];
        }

        $xml->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];

        foreach ($xml->xpath('//a:sheetData/a:row') ?: [] as $row) {
            $current = [];

            foreach ($row->c as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                $index = $this->columnIndexFromReference($ref);
                $type = (string) ($cell['t'] ?? '');
                $value = $this->cellValue($cell, $type, $sharedStrings);

                $current[$index] = $this->cleanCell($value);
            }

            if (!empty($current)) {
                $maxIndex = max(array_keys($current));
                $dense = array_fill(0, $maxIndex + 1, '');

                foreach ($current as $index => $value) {
                    $dense[$index] = $value;
                }

                $rows[] = $dense;
            }
        }

        return $rows;
    }

    private function cellValue(\SimpleXMLElement $cell, string $type, array $sharedStrings): string
    {
        if ($type === 'inlineStr') {
            return (string) ($cell->is->t ?? '');
        }

        $value = (string) ($cell->v ?? '');

        if ($type === 's') {
            $index = (int) $value;

            return $sharedStrings[$index] ?? '';
        }

        return $value;
    }

    private function columnIndexFromReference(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference)) ?? '';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }
}
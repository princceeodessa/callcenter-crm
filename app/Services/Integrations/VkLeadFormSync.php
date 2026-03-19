<?php

namespace App\Services\Integrations;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VkLeadFormSync
{
    public function handle(IntegrationConnection $connection, IntegrationEvent $event): ?Deal
    {
        $rawPayload = is_array($event->payload) ? $event->payload : [];
        $payload = $this->normalizePayload($rawPayload);
        $object = is_array($payload['object'] ?? null) ? $payload['object'] : $payload;

        return DB::transaction(function () use ($connection, $event, $rawPayload, $payload, $object) {
            $accountId = (int) $connection->account_id;
            $externalId = $this->extractExternalId($object, $event);
            $existingDeal = $this->findExistingDeal($accountId, $externalId);

            if ($existingDeal) {
                return $existingDeal;
            }

            [$pipeline, $stage] = $this->getDefaultPipelineAndStage($accountId);

            $answers = $this->extractAnswers($object);
            $leadName = $this->extractLeadName($object, $answers);
            $phone = $this->normalizePhone($this->extractPhone($object, $answers));
            $email = $this->extractEmail($object, $answers);
            $contact = $this->resolveContact($accountId, $leadName, $phone, $email);
            $responsibleId = $this->getDefaultResponsibleUserId($accountId);
            $formatted = $this->formatSubmission($payload, $object, $answers);

            $deal = Deal::create([
                'account_id' => $accountId,
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stage->id,
                'title' => $this->makeDealTitle($leadName, $phone, $formatted['form_name']),
                'title_is_custom' => 0,
                'contact_id' => $contact?->id,
                'responsible_user_id' => $responsibleId,
                'is_unread' => true,
            ]);

            DealActivity::create([
                'account_id' => $accountId,
                'deal_id' => $deal->id,
                'author_user_id' => null,
                'type' => 'lead_form',
                'body' => $formatted['body'],
                'payload' => [
                    'provider' => 'vk',
                    'external_id' => $externalId,
                    'form_name' => $formatted['form_name'],
                    'fields' => $formatted['fields'],
                    'meta' => $formatted['meta'],
                    'raw' => $rawPayload,
                    'integration_event_id' => $event->id,
                ],
            ]);

            return $deal;
        });
    }

    private function extractExternalId(array $object, IntegrationEvent $event): ?string
    {
        foreach (['lead_id', 'id', 'request_id'] as $key) {
            $value = $object[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return is_string($event->external_id) && trim($event->external_id) !== ''
            ? trim($event->external_id)
            : null;
    }

    private function findExistingDeal(int $accountId, ?string $externalId): ?Deal
    {
        if (!$externalId) {
            return null;
        }

        $activity = DealActivity::query()
            ->where('account_id', $accountId)
            ->where('type', 'lead_form')
            ->where('payload->provider', 'vk')
            ->where('payload->external_id', $externalId)
            ->latest('id')
            ->first();

        return $activity?->deal;
    }

    private function getDefaultPipelineAndStage(int $accountId): array
    {
        $pipeline = Pipeline::query()
            ->where('account_id', $accountId)
            ->where('is_default', 1)
            ->first();

        if (!$pipeline) {
            $pipeline = Pipeline::query()
                ->where('account_id', $accountId)
                ->orderBy('id')
                ->firstOrFail();
        }

        $stage = PipelineStage::query()
            ->where('account_id', $accountId)
            ->where('pipeline_id', $pipeline->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->firstOrFail();

        return [$pipeline, $stage];
    }

    private function getDefaultResponsibleUserId(int $accountId): ?int
    {
        $admin = User::query()
            ->where('account_id', $accountId)
            ->where('is_active', 1)
            ->where('role', 'admin')
            ->orderBy('id')
            ->first();

        if ($admin) {
            return $admin->id;
        }

        $any = User::query()
            ->where('account_id', $accountId)
            ->where('is_active', 1)
            ->orderBy('id')
            ->first();

        return $any?->id;
    }

    private function resolveContact(int $accountId, ?string $leadName, ?string $phone, ?string $email): ?Contact
    {
        if (!$leadName && !$phone && !$email) {
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
                'name' => $leadName ?: ($phone ? 'Клиент '.$phone : ($email ?: 'Лид из VK')),
                'phone' => $phone,
                'email' => $email,
            ]);
        }

        $updates = [];
        if ($leadName && $this->shouldUpdateContactName($contact->name ?? null)) {
            $updates['name'] = $leadName;
        }
        if ($phone && trim((string) ($contact->phone ?? '')) === '') {
            $updates['phone'] = $phone;
        }
        if ($email && trim((string) ($contact->email ?? '')) === '') {
            $updates['email'] = $email;
        }

        if ($updates !== []) {
            $contact->update($updates);
        }

        return $contact;
    }

    private function extractAnswers(array $object): array
    {
        $rawAnswers = $object['answers'] ?? $object['fields'] ?? $object['form_data'] ?? [];
        if (!is_array($rawAnswers)) {
            return [];
        }

        $fields = [];
        $position = 0;
        foreach ($rawAnswers as $index => $answer) {
            $position++;
            if (is_array($answer)) {
                $label = $this->firstNonEmptyString($answer, ['label', 'question', 'question_text', 'title', 'text', 'name'])
                    ?? ('Поле '.$position);

                $value = $this->flattenValue(
                    $answer['answer']
                    ?? $answer['value']
                    ?? $answer['text']
                    ?? $answer['answer_text']
                    ?? $answer['answers']
                    ?? null
                );

                if ($value === '') {
                    continue;
                }

                $fields[] = [
                    'key' => $this->firstNonEmptyString($answer, ['key', 'field_key', 'name']) ?? (string) $index,
                    'label' => $label,
                    'value' => $value,
                ];

                continue;
            }

            if (is_scalar($answer) && trim((string) $answer) !== '') {
                $fields[] = [
                    'key' => (string) $index,
                    'label' => 'Поле '.$position,
                    'value' => $this->normalizeText(trim((string) $answer)),
                ];
            }
        }

        return $fields;
    }

    private function extractLeadName(array $object, array $answers): ?string
    {
        foreach ([
            $this->firstNonEmptyString($object, ['name', 'full_name', 'user_name']),
            $this->combineNames(
                $this->firstNonEmptyString($object, ['first_name']),
                $this->firstNonEmptyString($object, ['last_name'])
            ),
        ] as $candidate) {
            if ($candidate && preg_match('/[\p{L}]{2,}/u', $candidate) === 1) {
                return trim($candidate);
            }
        }

        foreach ($answers as $field) {
            $label = mb_strtolower(trim((string) ($field['label'] ?? '')));
            $key = mb_strtolower(trim((string) ($field['key'] ?? '')));
            $value = trim((string) ($field['value'] ?? ''));

            if ($value === '' || preg_match('/[\p{L}]{2,}/u', $value) !== 1) {
                continue;
            }

            foreach (['имя', 'фио', 'name', 'fullname', 'full_name'] as $needle) {
                if (str_contains($label, $needle) || str_contains($key, $needle)) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function extractPhone(array $object, array $answers): ?string
    {
        $candidate = $this->firstNonEmptyString($object, ['phone', 'phone_number']);
        if ($candidate) {
            return $candidate;
        }

        foreach ($answers as $field) {
            $label = mb_strtolower(trim((string) ($field['label'] ?? '')));
            $key = mb_strtolower(trim((string) ($field['key'] ?? '')));
            $value = trim((string) ($field['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            foreach (['телефон', 'phone', 'mobile', 'whatsapp'] as $needle) {
                if (str_contains($label, $needle) || str_contains($key, $needle)) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function extractEmail(array $object, array $answers): ?string
    {
        $candidate = $this->firstNonEmptyString($object, ['email']);
        if ($candidate && filter_var(trim(mb_strtolower($candidate)), FILTER_VALIDATE_EMAIL)) {
            return trim(mb_strtolower($candidate));
        }

        foreach ($answers as $field) {
            $label = mb_strtolower(trim((string) ($field['label'] ?? '')));
            $key = mb_strtolower(trim((string) ($field['key'] ?? '')));
            $value = trim((string) ($field['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            foreach (['email', 'e-mail', 'почта'] as $needle) {
                if ((str_contains($label, $needle) || str_contains($key, $needle))
                    && filter_var(trim(mb_strtolower($value)), FILTER_VALIDATE_EMAIL)) {
                    return trim(mb_strtolower($value));
                }
            }
        }

        return null;
    }

    private function formatSubmission(array $payload, array $object, array $answers): array
    {
        $formName = $this->firstNonEmptyString($object, ['form_name', 'title', 'name']) ?: 'Форма VK';
        $groupId = $this->firstNonEmptyString($payload, ['group_id']) ?: $this->firstNonEmptyString($object, ['group_id']);
        $userId = $this->firstNonEmptyString($object, ['user_id']);
        $leadId = $this->firstNonEmptyString($object, ['lead_id', 'id']);
        $formId = $this->firstNonEmptyString($object, ['form_id']);
        $createdAt = $this->formatDateTime(
            $this->firstNonEmptyString($object, ['created_at', 'date', 'submitted_at', 'time'])
        );

        $meta = [];
        if ($groupId) {
            $meta[] = ['label' => 'Сообщество', 'value' => 'https://vk.com/club'.$groupId];
        }
        if ($userId) {
            $meta[] = ['label' => 'Профиль', 'value' => 'https://vk.com/id'.$userId];
        }
        if ($createdAt) {
            $meta[] = ['label' => 'Дата получения', 'value' => $createdAt];
        }
        if ($leadId) {
            $meta[] = ['label' => 'ID заявки', 'value' => $leadId];
        }
        if ($formId) {
            $meta[] = ['label' => 'ID формы', 'value' => $formId];
        }

        $lines = ['Заявка с формы VK: '.$formName];

        if ($meta !== []) {
            $lines[] = '';
            foreach ($meta as $item) {
                $lines[] = $item['label'].': '.$item['value'];
            }
        }

        if ($answers !== []) {
            foreach ($answers as $field) {
                $lines[] = '';
                $lines[] = 'Вопрос: '.$field['label'];
                $lines[] = 'Ответ: '.$field['value'];
            }
        } else {
            $lines[] = '';
            $lines[] = 'В событии не удалось найти заполненные поля формы.';
        }

        return [
            'form_name' => $formName,
            'body' => implode("\n", $lines),
            'fields' => $answers,
            'meta' => $meta,
        ];
    }

    private function makeDealTitle(?string $leadName, ?string $phone, string $formName): string
    {
        if ($leadName) {
            return $leadName.' - VK форма';
        }

        if ($phone) {
            return 'Заявка VK - '.$phone;
        }

        return 'Заявка VK - '.$formName;
    }

    private function shouldUpdateContactName(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return true;
        }

        if (preg_match('/^клиент\b/iu', $value) === 1) {
            return true;
        }

        return preg_match('/[\p{L}]{2,}/u', $value) !== 1;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits) {
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

    private function firstNonEmptyString(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $source[$key] ?? null;
            if (!is_scalar($value)) {
                continue;
            }

            $value = $this->normalizeText(trim((string) $value));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function flattenValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'да' : 'нет';
        }

        if (is_scalar($value)) {
            return $this->normalizeText(trim((string) $value));
        }

        if (!is_array($value)) {
            return '';
        }

        if (array_is_list($value)) {
            $items = [];
            foreach ($value as $item) {
                $flat = $this->flattenValue($item);
                if ($flat !== '') {
                    $items[] = $flat;
                }
            }

            return implode(', ', $items);
        }

        foreach (['text', 'label', 'title', 'value', 'answer'] as $key) {
            if (isset($value[$key])) {
                $flat = $this->flattenValue($value[$key]);
                if ($flat !== '') {
                    return $flat;
                }
            }
        }

        $normalized = $this->normalizePayload($value);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '';
    }

    private function combineNames(?string $firstName, ?string $lastName): ?string
    {
        $fullName = trim(implode(' ', array_filter([$firstName, $lastName])));
        return $fullName !== '' ? $fullName : null;
    }

    private function formatDateTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            if (ctype_digit($value)) {
                return Carbon::createFromTimestamp((int) $value)->format('d.m.Y H:i');
            }

            return Carbon::parse($value)->format('d.m.Y H:i');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function normalizePayload(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizePayload($item);
            }

            return $normalized;
        }

        if (is_string($value)) {
            return $this->normalizeText($value);
        }

        return $value;
    }

    private function normalizeText(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $rawValue = $value;
        $value = trim(str_replace("\u{00A0}", ' ', $value));

        if ($value === '' || !$this->looksLikeMojibake($value)) {
            return $value;
        }

        $converted = @mb_convert_encoding($rawValue, 'Windows-1251', 'UTF-8');
        if (!is_string($converted) || $converted === '' || preg_match('//u', $converted) !== 1) {
            return $value;
        }

        return $this->normalizationLooksBetter($value, $converted)
            ? trim(str_replace("\u{00A0}", ' ', $converted))
            : $value;
    }

    private function looksLikeMojibake(string $value): bool
    {
        return $this->mojibakeScore($value) > 0;
    }

    private function normalizationLooksBetter(string $original, string $candidate): bool
    {
        return $this->mojibakeScore($candidate) < $this->mojibakeScore($original)
            && $this->readableCharacterScore($candidate) > 0;
    }

    private function mojibakeScore(string $value): int
    {
        preg_match_all('/(?:[РС][\x{0400}-\x{040F}\x{0450}-\x{045F}\x{00A0}-\x{00FF}\x{2010}-\x{203A}\x{20AC}\x{2116}\x{2122}]|[ÐÑ][\x{0080}-\x{00FF}])/u', $value, $matches);

        return count($matches[0]);
    }

    private function readableCharacterScore(string $value): int
    {
        preg_match_all('/[\x{0410}-\x{044F}ЁёA-Za-z0-9]/u', $value, $matches);

        return count($matches[0]);
    }
}

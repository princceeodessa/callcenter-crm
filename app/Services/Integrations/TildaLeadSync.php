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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TildaLeadSync
{
    public function handle(IntegrationConnection $connection, IntegrationEvent $event, Request $request): ?Deal
    {
        $payload = is_array($event->payload) ? $event->payload : [];
        if ($this->isTestPing($payload)) {
            return null;
        }

        return DB::transaction(function () use ($connection, $event, $request, $payload) {
            $accountId = $connection->account_id;
            $externalId = $this->extractExternalId($payload, $event);
            $existingDeal = $this->findExistingDeal($accountId, $externalId);
            if ($existingDeal) {
                return $existingDeal;
            }

            [$pipeline, $stage] = $this->getDefaultPipelineAndStage($accountId);

            $leadName = $this->extractLeadName($payload);
            $phone = $this->normalizePhone($this->extractPhone($payload));
            $email = $this->extractEmail($payload);
            $sourceUrl = $this->extractSourceUrl($payload, $request);
            $contact = $this->resolveContact($accountId, $leadName, $phone, $email);
            $responsibleId = $this->getDefaultResponsibleUserId($accountId);
            $formatted = $this->formatSubmission($payload, $request, $connection);

            $deal = Deal::create([
                'account_id' => $accountId,
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stage->id,
                'title' => $this->makeDealTitle($leadName, $phone),
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
                    'provider' => 'tilda',
                    'external_id' => $externalId,
                    'source_url' => $sourceUrl,
                    'fields' => $formatted['fields'],
                    'meta' => $formatted['meta'],
                    'raw' => $payload,
                    'integration_event_id' => $event->id,
                ],
            ]);

            return $deal;
        });
    }

    private function isTestPing(array $payload): bool
    {
        return isset($payload['test']) && (string) $payload['test'] === 'test';
    }

    private function extractExternalId(array $payload, IntegrationEvent $event): ?string
    {
        foreach (['tranid', 'lead_id', 'id', 'formid'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_scalar($value)) {
                $value = trim((string) $value);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return is_string($event->external_id) && trim($event->external_id) !== ''
            ? trim($event->external_id)
            : null;
    }

    private function findExistingDeal(int $accountId, ?string $externalId): ?Deal
    {
        if (! $externalId) {
            return null;
        }

        $activity = DealActivity::query()
            ->where('account_id', $accountId)
            ->where('type', 'lead_form')
            ->where('payload->provider', 'tilda')
            ->where('payload->external_id', $externalId)
            ->latest('id')
            ->first();

        return $activity?->deal;
    }

    private function resolveContact(int $accountId, ?string $leadName, ?string $phone, ?string $email): ?Contact
    {
        if (! $leadName && ! $phone && ! $email) {
            return null;
        }

        $contact = null;
        if ($phone) {
            $contact = Contact::query()
                ->where('account_id', $accountId)
                ->where('phone', $phone)
                ->first();
        }

        if (! $contact && $email) {
            $contact = Contact::query()
                ->where('account_id', $accountId)
                ->where('email', $email)
                ->first();
        }

        if (! $contact) {
            return Contact::create([
                'account_id' => $accountId,
                'name' => $leadName ?: ($phone ? 'Клиент '.$phone : ($email ?: 'Клиент сайта')),
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

    private function getDefaultPipelineAndStage(int $accountId): array
    {
        $pipeline = Pipeline::query()
            ->where('account_id', $accountId)
            ->where('is_default', 1)
            ->first();

        if (! $pipeline) {
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

    private function formatSubmission(array $payload, Request $request, IntegrationConnection $connection): array
    {
        $fields = [];
        $meta = [];
        $skipKeys = [
            'test',
            'token',
            'crm_token',
            (string) (($connection->settings ?? [])['api_field_name'] ?? 'crm_token'),
            'tranid',
            'formid',
            'formname',
            'form_name',
            'formtitle',
            'url',
            'page',
            'pageurl',
            'siteurl',
            'referer',
            'http_referer',
        ];
        $skipMap = [];
        foreach ($skipKeys as $skipKey) {
            $normalized = $this->normalizeKey($skipKey);
            if ($normalized !== '') {
                $skipMap[$normalized] = true;
            }
        }

        foreach ($payload as $key => $value) {
            $label = trim((string) $key);
            if ($label === '') {
                continue;
            }

            if (isset($skipMap[$this->normalizeKey($label)])) {
                continue;
            }

            $flat = $this->flattenValue($value);
            if ($flat === '') {
                continue;
            }

            $fields[] = [
                'key' => $label,
                'label' => $label,
                'value' => $flat,
            ];
        }

        foreach ([
            'Код заявки' => $this->findValue($payload, ['tranid', 'lead_id', 'id']),
            'Код блока' => $this->findValue($payload, ['formid']),
            'Форма' => $this->findValue($payload, ['formname', 'form_name', 'formtitle']),
            'Страница' => $this->extractSourceUrl($payload, $request),
        ] as $label => $value) {
            if ($value !== null && trim($value) !== '') {
                $meta[] = [
                    'label' => $label,
                    'value' => trim($value),
                ];
            }
        }

        $lines = [];
        if ($fields !== []) {
            $lines[] = 'Содержание заявки:';
            foreach ($fields as $field) {
                $lines[] = $field['label'].': '.$field['value'];
            }
        }

        if ($meta !== []) {
            if ($lines !== []) {
                $lines[] = '';
            }
            $lines[] = 'Дополнительная информация:';
            foreach ($meta as $item) {
                $lines[] = $item['label'].': '.$item['value'];
            }
        }

        if ($lines === []) {
            $lines[] = 'Новая заявка из Tilda';
        }

        return [
            'body' => implode("\n", $lines),
            'fields' => $fields,
            'meta' => $meta,
        ];
    }

    private function extractLeadName(array $payload): ?string
    {
        foreach (['name', 'имя', 'fio', 'фио', 'fullname', 'full_name', 'client_name', 'customer_name', 'contact_name'] as $key) {
            $value = $this->findValue($payload, [$key]);
            if ($value !== null && preg_match('/[\p{L}]{2,}/u', $value) === 1) {
                return trim($value);
            }
        }

        return null;
    }

    private function extractPhone(array $payload): ?string
    {
        return $this->findValue($payload, ['phone', 'телефон', 'tel', 'telephone', 'mobile', 'whatsapp']);
    }

    private function extractEmail(array $payload): ?string
    {
        $value = $this->findValue($payload, ['email', 'e-mail', 'почта']);
        if ($value === null) {
            return null;
        }

        $value = trim(mb_strtolower($value));
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    private function extractSourceUrl(array $payload, Request $request): ?string
    {
        foreach ([
            $request->header('Referer'),
            $this->findValue($payload, ['url', 'page', 'pageurl', 'siteurl', 'referer', 'http_referer']),
        ] as $candidate) {
            $candidate = is_string($candidate) ? trim($candidate) : '';
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_URL)) {
                return $candidate;
            }
        }

        return null;
    }

    private function makeDealTitle(?string $leadName, ?string $phone): string
    {
        if ($leadName) {
            return $leadName.' - Tilda';
        }

        if ($phone) {
            return 'Заявка с сайта - '.$phone;
        }

        return 'Заявка с сайта - Tilda';
    }

    private function shouldUpdateContactName(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return true;
        }

        if (preg_match('/^Клиент/u', $value) === 1) {
            return true;
        }

        return preg_match('/[\p{L}]{2,}/u', $value) !== 1;
    }

    private function normalizePhone(?string $phone): ?string
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

    private function findValue(array $payload, array $keys): ?string
    {
        $normalizedMap = [];
        foreach ($payload as $key => $value) {
            $normalizedKey = $this->normalizeKey((string) $key);
            if ($normalizedKey === '') {
                continue;
            }
            $normalizedMap[$normalizedKey] = $this->flattenValue($value);
        }

        foreach ($keys as $key) {
            $normalizedKey = $this->normalizeKey($key);
            $value = $normalizedMap[$normalizedKey] ?? '';
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function normalizeKey(string $key): string
    {
        $key = trim(mb_strtolower($key));
        $key = str_replace([' ', '-', '.'], '_', $key);
        return $key;
    }

    private function flattenValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (! is_array($value)) {
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

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '';
    }
}
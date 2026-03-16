<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    use BelongsToAccount;

    private const SOURCE_FILTER_PHONE = 'phone';
    private const SOURCE_FILTER_CRM = 'crm';
    private const PHONE_SOURCE_FILTER_PREFIX = 'phone:';
    private const DEFAULT_SOURCE_LABEL = 'CRM';
    private const DEFAULT_SOURCE_BADGE_CLASS = 'source-badge source-badge-default';
    private const DEFAULT_SOURCE_SURFACE_CLASS = 'source-surface source-surface-default';
    private const DEFAULT_SOURCE_ICON_HTML = '<span class="source-icon source-icon-default"><i class="bi bi-chat-dots-fill"></i></span>';
    private const TILDA_SOURCE_LABEL = 'Tilda';
    private const TILDA_SOURCE_BADGE_CLASS = 'source-badge source-badge-tilda';
    private const TILDA_SOURCE_SURFACE_CLASS = 'source-surface source-surface-tilda';
    private const TILDA_SOURCE_ICON_HTML = '<span class="source-icon source-icon-tilda" aria-hidden="true"><svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg"><path d="M13 24C13 14.6112 20.6112 7 30 7C36.664 7 40.5365 9.56094 44.2817 12.0394C47.4207 14.1167 50.4702 16.1348 55 16.1348V25.1348C47.6644 25.1348 43.4048 22.3158 39.9587 20.0354C36.7068 17.8836 34.3559 16.3272 30 16.3272C25.7452 16.3272 22.2963 19.7761 22.2963 24.0309V25.1348H13V24ZM28 25H37V57H28V25Z" fill="currentColor"/></svg></span>';
    private const BITRIX_SOURCE_LABEL = 'Bitrix';
    private const BITRIX_SOURCE_BADGE_CLASS = 'source-badge source-badge-bitrix';
    private const BITRIX_SOURCE_SURFACE_CLASS = 'source-surface source-surface-bitrix';
    private const BITRIX_SOURCE_ICON_HTML = '<span class="source-icon source-icon-bitrix"><i class="bi bi-box-arrow-in-down-right"></i></span>';
    private const PHONE_SOURCE_LABEL = "\u{0422}\u{0435}\u{043B}\u{0435}\u{0444}\u{043E}\u{043D}";
    private const PHONE_SOURCE_BADGE_CLASS = 'source-badge source-badge-megafon_vats';
    private const PHONE_SOURCE_SURFACE_CLASS = 'source-surface source-surface-megafon_vats';
    private const PHONE_SOURCE_ICON_HTML = '<span class="source-icon source-icon-megafon_vats"><i class="bi bi-telephone-fill"></i></span>';
    private const INCOMING_PHONE_SOURCE_LABELS = [
        '79225150259' => 'авито частник',
        '79225070404' => 'радио',
        '79225085574' => 'вк',
        '79221797710' => 'авито',
        '79225174552' => 'сайт (директ)',
    ];
    private const INCOMING_PHONE_SOURCE_KEYS = [
        'diversion',
        'to',
        'dst',
        'dst_num',
        'destination',
        'number',
        'did',
        'redirect_number',
        'redirect',
        'callerid_dnis',
        'caller_id_dnis',
        'telnum',
    ];
    private const CALL_EMPLOYEE_KEYS = [
        'answered_by',
        'answered_user',
        'answeredUser',
        'employee',
        'employee_name',
        'operator',
        'operator_name',
        'manager',
        'manager_name',
        'user',
        'user_name',
    ];
    private const MISSED_CALL_STATUSES = [
        'missed',
        'no_answer',
        'not_answered',
        'unanswered',
        'busy',
        'cancelled',
        'canceled',
        'failed',
    ];
    protected $fillable = [
        'account_id','pipeline_id','stage_id',
        'title','title_is_custom','contact_id','responsible_user_id',
        'amount','currency',
        'readiness_status','is_unread','has_script_deviation',
        'closed_at','closed_result','closed_reason','closed_by_user_id'
    ];

    protected $casts = [
        'is_unread' => 'boolean',
        'has_script_deviation' => 'boolean',
        'closed_at' => 'datetime',
        'title_is_custom' => 'boolean',
    ];

    protected $appends = [
        'is_ready',
        'missing_fields',
    ];

    public static function sourceFilterOptions(): array
    {
        $options = [
            'vk' => 'VK',
            'telegram' => 'Telegram',
            'avito' => 'Avito',
            'tilda' => self::TILDA_SOURCE_LABEL,
            'bitrix' => self::BITRIX_SOURCE_LABEL,
            self::SOURCE_FILTER_PHONE => self::PHONE_SOURCE_LABEL,
        ];

        foreach (self::INCOMING_PHONE_SOURCE_LABELS as $number => $label) {
            $options[self::phoneSourceFilterKey($number)] = self::PHONE_SOURCE_LABEL.': '.$label;
        }

        $options[self::SOURCE_FILTER_CRM] = self::DEFAULT_SOURCE_LABEL;

        return $options;
    }

    public static function incomingPhoneSourceOptions(): array
    {
        $options = [];

        foreach (self::INCOMING_PHONE_SOURCE_LABELS as $number => $label) {
            $options[self::phoneSourceFilterKey($number)] = $label;
        }

        return $options;
    }

    public static function emptyIncomingPhoneSourceCounts(): array
    {
        return array_fill_keys(array_keys(self::incomingPhoneSourceOptions()), 0);
    }

    public static function resolveIncomingPhoneSourceFilterKeyFromPayload(?array $payload): ?string
    {
        $binding = self::resolveIncomingPhoneSourceFromPayload($payload);
        if ($binding === null) {
            return null;
        }

        return self::phoneSourceFilterKey($binding['number']);
    }

    public static function applySourceFilter(Builder $query, string $filter): Builder
    {
        if (!array_key_exists($filter, self::sourceFilterOptions())) {
            return $query;
        }

        if (in_array($filter, ['vk', 'telegram', 'avito'], true)) {
            return $query->whereHas('conversations', function (Builder $conversationQuery) use ($filter) {
                $conversationQuery->where('channel', $filter);
            });
        }

        if ($filter === 'tilda') {
            return $query->where(function (Builder $sourceQuery) {
                $sourceQuery
                    ->whereHas('conversations', function (Builder $conversationQuery) {
                        $conversationQuery->where('channel', 'tilda');
                    })
                    ->orWhereHas('activities', function (Builder $activityQuery) {
                        $activityQuery
                            ->where('type', 'lead_form')
                            ->where('payload->provider', 'tilda');
                    });
            });
        }

        if ($filter === 'bitrix') {
            return $query->whereHas('activities', function (Builder $activityQuery) {
                $activityQuery
                    ->where('type', 'import')
                    ->where('payload->provider', 'bitrix');
            });
        }

        if ($filter === self::SOURCE_FILTER_PHONE) {
            return $query->where(function (Builder $sourceQuery) {
                $sourceQuery
                    ->whereHas('activities', function (Builder $activityQuery) {
                        $activityQuery->where('type', 'call');
                    })
                    ->orWhereHas('callRecordings');
            });
        }

        if ($filter === self::SOURCE_FILTER_CRM) {
            return $query
                ->whereDoesntHave('conversations')
                ->whereDoesntHave('callRecordings')
                ->whereDoesntHave('activities', function (Builder $activityQuery) {
                    $activityQuery->where(function (Builder $sourceQuery) {
                        $sourceQuery
                            ->where('type', 'call')
                            ->orWhere(function (Builder $tildaQuery) {
                                $tildaQuery
                                    ->where('type', 'lead_form')
                                    ->where('payload->provider', 'tilda');
                            })
                            ->orWhere(function (Builder $bitrixQuery) {
                                $bitrixQuery
                                    ->where('type', 'import')
                                    ->where('payload->provider', 'bitrix');
                            });
                    });
                });
        }

        $number = self::phoneSourceNumberFromFilter($filter);
        if ($number === null) {
            return $query;
        }

        return $query->whereHas('activities', function (Builder $activityQuery) use ($number) {
            $activityQuery
                ->where('type', 'call')
                ->where(function (Builder $callQuery) use ($number) {
                    self::applyIncomingPhoneSourceNumberCondition($callQuery, $number);
                });
        });
    }

    public function getMissingFieldsAttribute(): array
    {
        $missing = [];
        if (!$this->responsible_user_id) $missing[] = 'responsible';
        if (!$this->amount || (float)$this->amount <= 0) $missing[] = 'amount';
        if (!$this->title_is_custom) $missing[] = 'title';
        return $missing;
    }

    public function getIsReadyAttribute(): bool
    {
        return count($this->missing_fields) === 0;
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function stage()
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function latestStageHistory()
    {
        return $this->hasOne(DealStageHistory::class)->ofMany([
            'changed_at' => 'max',
            'id' => 'max',
        ]);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function activities()
    {
        return $this->hasMany(DealActivity::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function callRecordings()
    {
        return $this->hasMany(CallRecording::class);
    }

    public function latestCallActivity()
    {
        return $this->hasOne(DealActivity::class)->ofMany(['id' => 'max'], function ($query) {
            $query->where('type', 'call');
        });
    }

    public function getLatestCallAnsweredByLabelAttribute(): ?string
    {
        $activity = $this->relationLoaded('latestCallActivity')
            ? $this->latestCallActivity
            : $this->latestCallActivity()->first();

        $payload = is_array($activity?->payload ?? null)
            ? $activity->payload
            : null;

        return self::resolveCallEmployeeFromPayload($payload);
    }

    public function primaryConversation(): ?Conversation
    {
        if ($this->relationLoaded('conversations')) {
            return $this->conversations
                ->sortByDesc(fn ($conversation) => optional($conversation->last_message_at)->getTimestamp() ?? 0)
                ->first();
        }

        return $this->conversations()
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->first();
    }

    public function getLeadDisplayNameAttribute(): ?string
    {
        try {
            $contactName = trim((string) ($this->contact?->name ?? ''));
            if ($contactName !== '') {
                return $contactName;
            }

            $conversation = $this->primaryConversation();
            return $conversation?->lead_name;
        } catch (\Throwable) {
            return trim((string) ($this->attributes['title'] ?? '')) ?: null;
        }
    }

    public function getLeadSourceLabelAttribute(): string
    {
        if ($conversation = $this->resolveLeadSourceConversation()) {
            return $conversation->source_label;
        }

        return $this->fallbackLeadSourceMeta()['label'];
    }

    public function getLeadSourceBadgeClassAttribute(): string
    {
        if ($conversation = $this->resolveLeadSourceConversation()) {
            return $conversation->source_badge_class;
        }

        return $this->fallbackLeadSourceMeta()['badge_class'];
    }

    public function getLeadSourceIconHtmlAttribute(): string
    {
        if ($conversation = $this->resolveLeadSourceConversation()) {
            return $conversation->source_icon_html;
        }

        return $this->fallbackLeadSourceMeta()['icon_html'];
    }

    public function getLeadSourceChatUrlAttribute(): ?string
    {
        if ($conversation = $this->resolveLeadSourceConversation()) {
            return $conversation->chat_url;
        }

        return null;
    }

    public function getLeadSourceSurfaceClassAttribute(): string
    {
        if ($conversation = $this->resolveLeadSourceConversation()) {
            return $conversation->source_surface_class;
        }

        return $this->fallbackLeadSourceMeta()['surface_class'];
    }

    public function getIncomingPhoneSourceLabelAttribute(): ?string
    {
        return $this->resolveIncomingPhoneSourceBinding()['label'] ?? null;
    }

    public function getIncomingPhoneSourceDisplayAttribute(): ?string
    {
        $binding = $this->resolveIncomingPhoneSourceBinding();
        if (!$binding) {
            return null;
        }

        return $binding['label'].' - '.$this->formatPhoneSourceNumber($binding['number']);
    }
    public function getLastMovedByLabelAttribute(): string
    {
        $history = $this->latestStageHistory;

        if (!$history) {
            return 'Еще не перемещали';
        }

        return 'Последний перенос: '.(
            $history->changedBy?->name ?? 'Система'
        );
    }
    private function resolveLeadSourceConversation(): ?Conversation
    {
        try {
            return $this->primaryConversation();
        } catch (\Throwable) {
            return null;
        }
    }
    public static function resolveIncomingPhoneSourceFromPayload(?array $payload): ?array
    {
        if (!is_array($payload) || $payload === []) {
            return null;
        }

        $callType = strtolower(trim((string) ($payload['type'] ?? '')));
        if ($callType === 'out') {
            return null;
        }

        foreach (self::INCOMING_PHONE_SOURCE_KEYS as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $binding = self::resolveIncomingPhoneSourceFromValue($payload[$key]);
            if ($binding) {
                return $binding;
            }
        }

        $binding = null;
        array_walk_recursive($payload, function ($value) use (&$binding) {
            if ($binding !== null) {
                return;
            }

            $binding = self::resolveIncomingPhoneSourceFromValue($value);
        });

        return $binding;
    }

    public static function resolveCallEmployeeFromPayload(?array $payload): ?string
    {
        if (!is_array($payload) || $payload === []) {
            return null;
        }

        $callType = strtolower(trim((string) ($payload['type'] ?? '')));
        $status = strtolower(trim((string) ($payload['status'] ?? '')));

        if ($callType === 'out' || $callType === 'missed' || in_array($status, self::MISSED_CALL_STATUSES, true)) {
            return null;
        }

        foreach (self::CALL_EMPLOYEE_KEYS as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $formatted = self::formatCallEmployeeValue($payload[$key]);
            if ($formatted !== null) {
                return $formatted;
            }
        }

        foreach ($payload as $value) {
            if (!is_array($value)) {
                continue;
            }

            $formatted = self::resolveCallEmployeeFromPayload($value);
            if ($formatted !== null) {
                return $formatted;
            }
        }

        return null;
    }

    private function fallbackLeadSourceMeta(): array
    {
        if ($this->hasTildaLeadSource()) {
            return [
                'label' => self::TILDA_SOURCE_LABEL,
                'badge_class' => self::TILDA_SOURCE_BADGE_CLASS,
                'surface_class' => self::TILDA_SOURCE_SURFACE_CLASS,
                'icon_html' => self::TILDA_SOURCE_ICON_HTML,
            ];
        }

        if ($this->hasPhoneLeadSource()) {
            return [
                'label' => self::PHONE_SOURCE_LABEL,
                'badge_class' => self::PHONE_SOURCE_BADGE_CLASS,
                'surface_class' => self::PHONE_SOURCE_SURFACE_CLASS,
                'icon_html' => self::PHONE_SOURCE_ICON_HTML,
            ];
        }

        if ($this->hasBitrixImportSource()) {
            return [
                'label' => self::BITRIX_SOURCE_LABEL,
                'badge_class' => self::BITRIX_SOURCE_BADGE_CLASS,
                'surface_class' => self::BITRIX_SOURCE_SURFACE_CLASS,
                'icon_html' => self::BITRIX_SOURCE_ICON_HTML,
            ];
        }

        return [
            'label' => self::DEFAULT_SOURCE_LABEL,
            'badge_class' => self::DEFAULT_SOURCE_BADGE_CLASS,
            'surface_class' => self::DEFAULT_SOURCE_SURFACE_CLASS,
            'icon_html' => self::DEFAULT_SOURCE_ICON_HTML,
        ];
    }

    private function hasPhoneLeadSource(): bool
    {
        if (array_key_exists('phone_call_activities_count', $this->attributes)
            && (int) $this->attributes['phone_call_activities_count'] > 0) {
            return true;
        }

        if (array_key_exists('phone_call_recordings_count', $this->attributes)
            && (int) $this->attributes['phone_call_recordings_count'] > 0) {
            return true;
        }

        if ($this->relationLoaded('activities') && $this->activities->contains(fn ($activity) => $activity->type === 'call')) {
            return true;
        }

        if ($this->relationLoaded('callRecordings') && $this->callRecordings->isNotEmpty()) {
            return true;
        }

        return $this->activities()->where('type', 'call')->exists()
            || $this->callRecordings()->exists();
    }

    private function hasTildaLeadSource(): bool
    {
        if (array_key_exists('tilda_lead_form_activities_count', $this->attributes)
            && (int) $this->attributes['tilda_lead_form_activities_count'] > 0) {
            return true;
        }

        if ($this->relationLoaded('activities')) {
            return $this->activities->contains(function ($activity) {
                $payload = is_array($activity->payload ?? null) ? $activity->payload : [];

                return $activity->type === 'lead_form'
                    && (($payload['provider'] ?? null) === 'tilda');
            });
        }

        return $this->activities()
            ->where('type', 'lead_form')
            ->where('payload->provider', 'tilda')
            ->exists();
    }

    public function resolveBitrixBinding(): ?array
    {
        $activity = $this->resolveLatestBitrixImportActivity();
        if (! $activity) {
            return null;
        }

        $payload = is_array($activity->payload ?? null) ? $activity->payload : [];
        $entityId = trim((string) ($payload['bitrix_entity_id'] ?? $payload['bitrix_lead_id'] ?? ''));
        if ($entityId === '') {
            return null;
        }

        $entityType = strtolower(trim((string) ($payload['bitrix_entity_type'] ?? 'lead')));
        if (! in_array($entityType, ['lead', 'deal'], true)) {
            $entityType = 'lead';
        }

        return [
            'entity_id' => $entityId,
            'entity_type' => $entityType,
            'owner_type_id' => $entityType === 'deal' ? 2 : 1,
            'activity_id' => $activity->id,
        ];
    }

    private function hasBitrixImportSource(): bool
    {
        return $this->resolveLatestBitrixImportActivity() !== null;
    }

    private function resolveIncomingPhoneSourceBinding(): ?array
    {
        if ($this->relationLoaded('activities')) {
            foreach ($this->activities->where('type', 'call')->sortByDesc('id') as $activity) {
                $binding = self::resolveIncomingPhoneSourceFromPayload(is_array($activity->payload ?? null) ? $activity->payload : []);
                if ($binding) {
                    return $binding;
                }
            }

            return null;
        }

        if ($this->relationLoaded('latestCallActivity')) {
            $binding = self::resolveIncomingPhoneSourceFromPayload(
                is_array($this->latestCallActivity?->payload ?? null) ? $this->latestCallActivity->payload : []
            );
            if ($binding) {
                return $binding;
            }
        }

        foreach ($this->activities()->where('type', 'call')->orderByDesc('id')->limit(5)->get() as $activity) {
            $binding = self::resolveIncomingPhoneSourceFromPayload(is_array($activity->payload ?? null) ? $activity->payload : []);
            if ($binding) {
                return $binding;
            }
        }

        return null;
    }

    private static function formatCallEmployeeValue(mixed $value): ?string
    {
        if (is_array($value)) {
            foreach (['name', 'full_name', 'fullName', 'login', 'username', 'user'] as $key) {
                if (!array_key_exists($key, $value)) {
                    continue;
                }

                $formatted = self::formatCallEmployeeValue($value[$key]);
                if ($formatted !== null) {
                    return $formatted;
                }
            }

            return null;
        }

        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^[a-z0-9_.-]+$/i', $value) === 1) {
            $value = str_replace(['_', '.', '-'], ' ', $value);
            $value = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
        }

        return $value;
    }

    private static function resolveIncomingPhoneSourceFromValue(mixed $value): ?array
    {
        $number = self::normalizePhoneSourceNumber($value);
        if (!$number) {
            return null;
        }

        $label = self::INCOMING_PHONE_SOURCE_LABELS[$number] ?? null;
        if (!$label) {
            return null;
        }

        return [
            'number' => $number,
            'label' => $label,
        ];
    }

    private static function phoneSourceFilterKey(string $number): string
    {
        return self::PHONE_SOURCE_FILTER_PREFIX.$number;
    }

    private static function phoneSourceNumberFromFilter(string $filter): ?string
    {
        if (!str_starts_with($filter, self::PHONE_SOURCE_FILTER_PREFIX)) {
            return null;
        }

        $number = substr($filter, strlen(self::PHONE_SOURCE_FILTER_PREFIX));
        if ($number === '' || !array_key_exists($number, self::INCOMING_PHONE_SOURCE_LABELS)) {
            return null;
        }

        return $number;
    }

    private static function applyIncomingPhoneSourceNumberCondition(Builder $query, string $number): Builder
    {
        $variants = self::phoneSourceSearchVariants($number);

        return $query->where(function (Builder $matchQuery) use ($variants) {
            foreach (self::INCOMING_PHONE_SOURCE_KEYS as $key) {
                foreach ($variants as $variant) {
                    $matchQuery->orWhere("payload->{$key}", $variant);
                }
            }

            foreach ($variants as $variant) {
                $matchQuery->orWhereRaw('CAST(payload AS CHAR) LIKE ?', ['%'.$variant.'%']);
            }
        });
    }

    private static function phoneSourceSearchVariants(string $number): array
    {
        $tail = substr($number, 1);

        return array_values(array_unique([
            $number,
            $tail,
            '+'.$number,
            '8'.$tail,
        ]));
    }

    private static function normalizePhoneSourceNumber(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);
        if (!$digits) {
            return null;
        }

        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            $digits = '7'.$digits;
        }

        if (!preg_match('/^7\d{10}$/', $digits)) {
            return null;
        }

        return $digits;
    }

    private function formatPhoneSourceNumber(string $digits): string
    {
        return sprintf(
            '+7 (%s) %s-%s-%s',
            substr($digits, 1, 3),
            substr($digits, 4, 3),
            substr($digits, 7, 2),
            substr($digits, 9, 2)
        );
    }
    private function resolveLatestBitrixImportActivity(): ?DealActivity
    {
        if ($this->relationLoaded('activities')) {
            return $this->activities
                ->filter(function ($activity) {
                    $payload = is_array($activity->payload ?? null) ? $activity->payload : [];

                    return $activity->type === 'import'
                        && (($payload['provider'] ?? null) === 'bitrix');
                })
                ->sortByDesc('id')
                ->first();
        }

        return $this->activities()
            ->where('type', 'import')
            ->where('payload->provider', 'bitrix')
            ->orderByDesc('id')
            ->first();
    }
}

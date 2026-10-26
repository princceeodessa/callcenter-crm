<?php

namespace App\Services\Integrations;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\DealStageHistory;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\Owner\Marketing\Http;
use App\Support\Leads\LeadDeduplicator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Заказы Лемана ПРО (бывш. Леруа Мерлен), приходящие через сервис «Руки» (hands.ru, API бэкофиса), → лиды потолков.
 * Для людей источник — «Лемана ПРО» (просьба 26.10.2026). Заказы на замер потолка назначаются нашей организации в Руках; CRM раз в 5 минут забирает новые (GET /api/v1/specialist/orders/, ключ в заголовке X-Api-Key)
 * и заводит каждый один раз (журнал integration_events, provider hands, external_id — номер заказа): как заявка
 * с сайта — сделка в «Поступил лид» или, если у номера уже есть открытая сделка, в неё (LeadDeduplicator).
 * Заказ, по которому мастер в Руках уже «Договорились» с клиентом, — сразу на этап «Замер назначен» (решение 26.10.2026):
 * колл-центр звонит только по «Назначен». Договорились позже — сделка, которую ещё не трогали (стоит на первом этапе),
 * переходит на «Замер назначен» при следующем опросе (по списку активных заказов).
 * Ключ — в «Интеграции → Руки» (integration_connections, provider hands, settings.api_key), в журналы не пишется.
 */
class HandsLeadSync
{
    public const PROVIDER = 'hands';

    public const API = 'https://api.hands.ru/api/v1/specialist';

    /** Как источник называется для людей: в сделке, ленте, отчётах. */
    public const SOURCE = 'Лемана ПРО';

    /** Со скольких дней назад берём заказы при подключении (историю не заводим — её уже обработали в Руках). */
    public const START_DAYS = 3;

    /** Статус заказа в Руках, когда мастер договорился с клиентом о замере. */
    public const AGREED = 'договорились';

    private const UNITS = ['PIECE' => 'шт', 'HOUR' => 'ч', 'METER' => 'м', 'METER_SQUARED' => 'м²', 'VISIT' => 'выезд', 'FIX' => ''];

    /** Новые заказы за последние дни (не раньше даты подключения) → лиды. @return array{orders:int, created:int} */
    public function poll(IntegrationConnection $conn): array
    {
        $settings = (array) ($conn->settings ?? []);
        $key = trim((string) ($settings['api_key'] ?? ''));
        if ($key === '') {
            throw new RuntimeException('нет ключа API');
        }
        $since = Carbon::parse((string) ($settings['import_since'] ?? now()->subDays(self::START_DAYS)->toDateString()))->startOfDay();
        $from = Carbon::today()->subDays(2)->max($since);
        $orders = self::orders($key, $from, Carbon::today());

        $created = 0;
        foreach ($orders as $order) {
            if (! empty($order['creation_time']) && Carbon::parse((string) $order['creation_time'])->lt($since)) {
                continue;
            }
            if ($this->ingest($conn, $order) !== null) {
                $created++;
            }
        }
        // уже заведённые заказы: мастер договорился — нетронутая сделка переходит на «Замер назначен»
        $moved = 0;
        foreach (self::get($key, '/orders/?'.http_build_query(['status' => 'ACTIVE', 'per_page' => 500]))['orders'] ?? [] as $order) {
            if (is_array($order) && self::agreed($order) && $this->agreeLater($conn, $order)) {
                $moved++;
            }
        }
        $conn->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();

        return ['orders' => count($orders), 'created' => $created, 'moved' => $moved];
    }

    public static function agreed(array $order): bool
    {
        return mb_strtolower(trim((string) ($order['status'] ?? ''))) === self::AGREED;
    }

    /** Заведённый заказ, по которому договорились позже: сделка ещё на первом этапе и открыта → «Замер назначен». */
    private function agreeLater(IntegrationConnection $conn, array $order): bool
    {
        $accountId = (int) $conn->account_id;
        $dealId = DealActivity::withoutGlobalScopes()->where('account_id', $accountId)->where('type', 'lead_form')
            ->where('payload->provider', self::PROVIDER)->where('payload->external_id', (string) ($order['id'] ?? ''))->value('deal_id');
        $deal = $dealId ? Deal::withoutGlobalScopes()->find($dealId) : null;
        if (! $deal || $deal->closed_at !== null || (int) $deal->stage_id !== $this->firstStage($accountId)[1]) {
            return false;
        }

        return $this->toMeasure($deal, $order);
    }

    /** Сделка → «Замер назначен» (как перенос на канбане, только автором — Руки): этап, история этапов, запись в ленте. */
    private function toMeasure(Deal $deal, array $order): bool
    {
        $to = PipelineStage::withoutGlobalScopes()->where('account_id', $deal->account_id)->where('pipeline_id', $deal->pipeline_id)
            ->where('name', 'like', '%Замер назначен%')->orderBy('sort')->first();
        if (! $to || (int) $deal->stage_id === (int) $to->id) {
            return false;
        }
        $from = (int) $deal->stage_id;
        $deal->forceFill(['stage_id' => $to->id])->save();
        $when = trim((string) ($order['work_time'] ?? '')) !== '' ? ', замер '.Carbon::parse((string) $order['work_time'])->format('d.m.Y H:i') : '';
        DealActivity::withoutGlobalScopes()->create([
            'account_id' => $deal->account_id, 'deal_id' => $deal->id, 'author_user_id' => null, 'type' => 'stage_changed',
            'body' => 'Лемана ПРО: мастер договорился с клиентом'.$when.' — этап «'.$to->name.'»',
            'payload' => ['from_stage_id' => $from, 'to_stage_id' => $to->id, 'provider' => self::PROVIDER, 'external_id' => (string) ($order['id'] ?? '')],
        ]);
        DealStageHistory::withoutGlobalScopes()->create([
            'account_id' => $deal->account_id, 'deal_id' => $deal->id, 'from_stage_id' => $from, 'to_stage_id' => $to->id,
            'changed_by_user_id' => null, 'changed_at' => now(),
        ]);

        return true;
    }

    /** Заказы, созданные в [from, to] (включительно), все статусы. */
    public static function orders(string $key, Carbon $from, Carbon $to): array
    {
        $out = [];
        for ($page = 1; $page <= 20; $page++) {
            $d = self::get($key, '/orders/?'.http_build_query([
                'date_from' => $from->toDateString(), 'date_to' => $to->toDateString(), 'page' => $page, 'per_page' => 500,
            ]));
            foreach ((array) ($d['orders'] ?? []) as $o) {
                if (is_array($o) && (string) ($o['id'] ?? '') !== '') {
                    $out[] = $o;
                }
            }
            if ($page >= (int) ($d['pages'] ?? 1)) {
                break;
            }
        }

        return $out;
    }

    /** Проверка ключа при подключении: сколько заказов за сегодня (исключение — текст ошибки Рук). */
    public static function check(string $key): int
    {
        return count(self::orders($key, Carbon::today(), Carbon::today()));
    }

    /** Один заказ → лид (null — уже был заведён раньше). */
    public function ingest(IntegrationConnection $conn, array $order, ?string $delivery = null): ?Deal
    {
        $accountId = (int) $conn->account_id;
        $orderId = (string) $order['id'];
        $phones = array_values(array_filter(array_map(fn ($p) => LeadDeduplicator::normalizePhone((string) $p), (array) ($order['client_phones'] ?? []))));
        $phone = $phones[0] ?? null;

        return LeadDeduplicator::withPhoneLock($accountId, $phone, fn () => DB::transaction(function () use ($conn, $accountId, $order, $orderId, $phones, $phone, $delivery) {
            $seen = IntegrationEvent::withoutGlobalScopes()->where('account_id', $accountId)->where('provider', self::PROVIDER)
                ->where('external_id', $orderId)->lockForUpdate()->exists();
            if ($seen) {
                return null;
            }
            $event = IntegrationEvent::withoutGlobalScopes()->create([
                'account_id' => $accountId, 'provider' => self::PROVIDER, 'direction' => 'in', 'event_type' => 'order',
                'external_id' => $orderId, 'payload' => ['order' => $order, 'delivery' => $delivery], 'received_at' => now(),
            ]);

            $name = trim((string) ($order['client_name'] ?? ''));
            $contact = $this->contact($accountId, $name, $phone);
            $deal = $phone ? LeadDeduplicator::findOpenDealByPhone($accountId, $phone, $contact?->id) : null;
            if ($deal) {
                $deal->is_unread = true;
                $deal->updated_at = now();          // флаг мог уже стоять — без этого повторный лид не всплывёт наверх
                $deal->save();
            } else {
                [$pipelineId, $stageId] = $this->firstStage($accountId);
                $deal = Deal::withoutGlobalScopes()->create([
                    'account_id' => $accountId,
                    'pipeline_id' => $pipelineId,
                    'stage_id' => $stageId,
                    'title' => ($name !== '' ? $name : ($phone ?? 'Клиент')).' — '.self::SOURCE,
                    'title_is_custom' => 0,
                    'contact_id' => $contact?->id,
                    'responsible_user_id' => User::where('account_id', $accountId)->where('is_active', 1)->where('role', 'admin')->orderBy('id')->value('id'),
                    'is_unread' => true,
                ]);
            }

            $isNew = $deal->wasRecentlyCreated;
            DealActivity::withoutGlobalScopes()->create([
                'account_id' => $accountId,
                'deal_id' => $deal->id,
                'author_user_id' => null,
                'type' => 'lead_form',
                'body' => self::describe($order, $phones),
                'payload' => [
                    'provider' => self::PROVIDER,
                    'external_id' => $orderId,
                    'integration_event_id' => $event->id,
                    'raw' => $order,
                ],
            ]);
            // мастер в Руках уже договорился — колл-центру звонить не нужно: сразу «Замер назначен»
            if ($isNew && self::agreed($order)) {
                $this->toMeasure($deal, $order);
            }

            return $deal;
        }));
    }

    /** Текст заказа для карточки сделки: что, где, когда, мастер и комментарии — как в бэкофисе Рук. */
    public static function describe(array $o, array $phones = []): string
    {
        $at = fn (string $v) => trim($v) === '' ? '' : Carbon::parse($v)->format('d.m.Y H:i');
        $works = collect((array) ($o['works'] ?? []))->filter(fn ($w) => is_array($w))->map(function ($w) {
            $qty = rtrim(rtrim((string) ($w['quantity'] ?? ''), '0'), '.');
            $unit = self::UNITS[(string) ($w['unit'] ?? '')] ?? '';

            return trim((string) ($w['name'] ?? '')).($qty !== '' && $qty !== '1' ? ' — '.$qty.($unit !== '' ? ' '.$unit : '') : '');
        })->filter()->values();
        $lines = [
            'Заказ '.self::SOURCE.' № '.$o['id'].(! empty($o['creation_time']) ? ' от '.$at((string) $o['creation_time']) : '')
                .(! empty($o['shop_name']) ? ' · магазин '.$o['shop_name'] : ''),
            'Что: '.($works->isNotEmpty() ? $works->implode('; ') : trim(preg_replace('/\s+/u', ' ', (string) ($o['title'] ?? '')))),
            'Адрес: '.trim((string) ($o['address'] ?? '')).(trim((string) ($o['directions'] ?? '')) !== '' ? ', '.trim((string) $o['directions']) : ''),
        ];
        if (count($phones) > 1) {
            $lines[] = 'Телефоны: '.implode(', ', $phones);
        }
        if (! empty($o['work_time'])) {
            $lines[] = 'Время работ: '.$at((string) $o['work_time']);
        }
        if (! empty($o['delivery_date'])) {
            $lines[] = 'Доставка товара: '.Carbon::parse((string) $o['delivery_date'])->format('d.m.Y');
        }
        $lines[] = 'Статус: '.trim((string) ($o['status'] ?? '')).(! empty($o['specialist']) ? ' · мастер '.$o['specialist'] : '')
            .(! empty($o['price']) ? ' · сумма '.$o['price'].' ₽' : '').(! empty($o['payment_status']) ? ' · '.$o['payment_status'] : '');
        $comment = trim((string) ($o['full_comment'] ?? '')) ?: trim((string) ($o['comment'] ?? ''));
        if ($comment !== '') {
            $lines[] = 'Комментарий: '.$comment;
        }

        return implode("\n", array_filter($lines, fn ($l) => trim($l) !== ''));
    }

    private function contact(int $accountId, string $name, ?string $phone): ?Contact
    {
        if ($phone === null && $name === '') {
            return null;
        }
        $contact = $phone ? Contact::withoutGlobalScopes()->where('account_id', $accountId)->whereIn('phone', LeadDeduplicator::phoneSearchVariants($phone))->first() : null;
        if (! $contact) {
            return Contact::withoutGlobalScopes()->create(['account_id' => $accountId, 'name' => $name !== '' ? $name : 'Клиент '.$phone, 'phone' => $phone]);
        }
        if ($name !== '' && (trim((string) $contact->name) === '' || str_starts_with((string) $contact->name, 'Клиент'))) {
            $contact->update(['name' => $name]);
        }

        return $contact;
    }

    /** @return array{0:int, 1:int} воронка по умолчанию и её первый этап («Поступил лид») */
    private function firstStage(int $accountId): array
    {
        $pipeline = Pipeline::withoutGlobalScopes()->where('account_id', $accountId)->orderByDesc('is_default')->orderBy('id')->firstOrFail();
        $stage = PipelineStage::withoutGlobalScopes()->where('account_id', $accountId)->where('pipeline_id', $pipeline->id)->orderBy('sort')->orderBy('id')->firstOrFail();

        return [(int) $pipeline->id, (int) $stage->id];
    }

    /** GET к API Рук; ошибка — исключение с их текстом (без ключа). */
    private static function get(string $key, string $path): array
    {
        [$status, $text] = Http::withRetry('GET', self::API.$path, ['X-Api-Key' => $key, 'Accept' => 'application/json'], null, 20);
        $data = Http::json($text);
        if ($status !== 200) {
            $msg = (string) ($data['error'] ?? $data['detail'] ?? '');
            throw new RuntimeException('hands.ru: HTTP '.$status.($msg !== '' ? ' — '.mb_substr($msg, 0, 200) : ($status === 401 || $status === 403 ? ' — ключ не принят' : '')));
        }

        return $data;
    }
}

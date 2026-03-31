<?php

namespace App\Support\Deals;

use App\Models\Deal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait InteractsWithDealBroadcasts
{
    protected function eligibleBroadcastDealsQuery(int $accountId, string $category): Builder
    {
        return $this->broadcastEligibleDealsBaseQuery($accountId)
            ->where('product_category', $category);
    }

    protected function broadcastRecipientsByCategory(int $accountId): array
    {
        $categories = Deal::productCategoryOptions();
        $recipients = array_fill_keys(array_keys($categories), []);

        $deals = $this->broadcastEligibleDealsBaseQuery($accountId)
            ->with([
                'contact:id,name,phone',
                'tasks' => function ($query) {
                    [$startAt, $endAt] = $this->todayTaskWindow();

                    $query
                        ->select(['id', 'deal_id', 'title', 'due_at'])
                        ->where('status', 'open')
                        ->whereNotNull('due_at')
                        ->where('due_at', '>=', $startAt)
                        ->where('due_at', '<', $endAt)
                        ->orderBy('due_at')
                        ->orderBy('id');
                },
                'conversations' => function ($query) {
                    $query
                        ->select(['id', 'deal_id', 'channel', 'external_id', 'last_message_at'])
                        ->whereIn('channel', ['vk', 'avito'])
                        ->whereNotNull('external_id')
                        ->where('external_id', '!=', '')
                        ->orderByDesc('last_message_at')
                        ->orderByDesc('id');
                },
            ])
            ->get();

        foreach ($deals as $deal) {
            if (!is_string($deal->product_category) || !array_key_exists($deal->product_category, $recipients)) {
                continue;
            }

            $conversations = $deal->conversations->values();

            if ($conversations->isEmpty()) {
                continue;
            }

            $taskTimes = $deal->tasks
                ->pluck('due_at')
                ->filter()
                ->map(fn ($dueAt) => $dueAt->format('H:i'))
                ->unique()
                ->values()
                ->all();

            $channels = $conversations
                ->groupBy('channel')
                ->map(function ($items, $channel) {
                    $conversation = $items->first();

                    return [
                        'channel' => (string) $channel,
                        'label' => $this->broadcastConversationLabel($conversation),
                        'count' => $items->count(),
                    ];
                })
                ->values()
                ->all();

            $contactName = trim((string) ($deal->contact?->name ?? ''));
            $phone = trim((string) ($deal->contact?->phone ?? ''));

            $recipients[$deal->product_category][] = [
                'deal_id' => $deal->id,
                'url' => route('deals.show', $deal),
                'label' => $this->broadcastDealLabel($deal),
                'contact_name' => $contactName !== '' ? $contactName : null,
                'phone' => $phone !== '' ? $phone : null,
                'task_times' => $taskTimes,
                'chat_count' => $conversations->count(),
                'primary_chat_label' => $this->broadcastConversationLabel($conversations->first()),
                'channels' => $channels,
            ];
        }

        foreach ($recipients as $category => $items) {
            usort($items, function (array $left, array $right) {
                $leftTime = $left['task_times'][0] ?? '99:99';
                $rightTime = $right['task_times'][0] ?? '99:99';

                return [$leftTime, $left['label']] <=> [$rightTime, $right['label']];
            });

            $recipients[$category] = $items;
        }

        return $recipients;
    }

    protected function todayTaskWindow(): array
    {
        $startAt = now(config('app.timezone'))->startOfDay();
        $endAt = $startAt->copy()->addDay();

        return [$startAt, $endAt];
    }

    protected function broadcastDealLabel(Deal $deal): string
    {
        $lead = trim((string) ($deal->lead_display_name ?? ''));
        $title = trim((string) ($deal->title ?? ''));

        return $lead !== '' ? $lead.' (#'.$deal->id.')' : ($title !== '' ? $title.' (#'.$deal->id.')' : 'Сделка #'.$deal->id);
    }

    protected function broadcastTemplates(): array
    {
        return [
            'ceiling' => [
                $this->makeBroadcastTemplate(
                    'ceiling_1',
                    'Шаблон 1',
                    "Добрый день! 🌸😊\nПодскажите, пожалуйста, выезд мастера на замеры актуален для Вас?"
                ),
                $this->makeBroadcastTemplate(
                    'ceiling_2',
                    'Шаблон 2',
                    "Добрый день! Ранее интересовались стоимостью натяжных потолков. Запись на замеры актуальна еще для вас?🙌😊"
                ),
                $this->makeBroadcastTemplate(
                    'ceiling_3',
                    'Шаблон 3',
                    "Добрый день!\nПодскажите, готовы ли принять нашего специалиста на бесплатный замер и консультацию?\nГотовы выехать к вам в ближайшие дни 😇"
                ),
                $this->makeBroadcastTemplate(
                    'ceiling_4',
                    'Шаблон 4',
                    "Добрый день ❤️\nУспевайте воспользоваться сразу 🔥ДВУМЯ ВЫГОДНЫМИ ПРЕДЛОЖЕНИЯМИ🔥\nКаждое 2 и 3 помещение, в подарок идет полотно, а при заключении договора на потолки, будет дополнительная скидка на ВСЮ светотехнику от 20% до 50%🥰❤️\nПодберем для Вас время на бесплатный замер?"
                ),
                $this->makeBroadcastTemplate(
                    'ceiling_5',
                    'Шаблон 5',
                    "Добрый день ✨🙌🏻 Напоминаем что до АКТУАЛЬНАЯ ДАТА у нас проходит АКЦИЯ 2 и 3 потолок в подарок 🎁 плюс скидка на светотехнику!😉 просчет с учетом акций делает специалист по замерам,давайте подберем для вас время?📝"
                ),
            ],
            'air_conditioner' => [
                $this->makeBroadcastTemplate(
                    'air_conditioner_1',
                    'Шаблон 1',
                    "Добрый день! Ранее интересовались стоимостью кондиционеров, подскажите, уже готовы принять специалиста для выбора кондиционера и просчет стоимости?📝❤️"
                ),
                $this->makeBroadcastTemplate(
                    'air_conditioner_2',
                    'Шаблон 2',
                    "Добрый день! Ранее интересовались стоимостью установки кондиционера, подскажите, уже готовы принять специалиста для просчета стоимости?📝❤️"
                ),
                $this->makeBroadcastTemplate(
                    'air_conditioner_3',
                    'Шаблон 3',
                    "Добрый день! 🌸😊\nПодскажите, пожалуйста, выезд мастера на замеры актуален для Вас? Просто почему интересуемся уже начинается сезон кондиционеров 🔥🔥🔥 и запись на монтажи уже на 10 дней вперед, поэтому если планируете установку в ближайшее время, лучше уже пригласить специалиста на просчет и консультацию стоимости и закрепить за собой цену😉🙌"
                ),
                $this->makeBroadcastTemplate(
                    'air_conditioner_4',
                    'Шаблон 4',
                    "Добрый день! Ранее интересовались стоимостью установки кондиционера. Подскажите, когда было бы удобно принять специалиста на консультацию и просчет точной стоимости? 📒💛"
                ),
            ],
            'soundproofing' => [
                $this->makeBroadcastTemplate(
                    'soundproofing_1',
                    'Шаблон 1',
                    "Добрый день! Ранее интересовались стоимостью шумоизоляции. Запись на замеры актуальна еще для вас? Замеры и консультация проходят бесплатно 🌸📝🙌"
                ),
                $this->makeBroadcastTemplate(
                    'soundproofing_2',
                    'Шаблон 2',
                    "Добрый день ☀️ Установка шумоизоляции актуальна еще для вас? Давайте подберем время на консультацию и просчет стоимости?📝"
                ),
            ],
        ];
    }

    protected function makeBroadcastTemplate(string $key, string $title, string $text): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'text' => $text,
            'preview' => Str::limit(preg_replace('/\s+/u', ' ', trim($text)) ?: $text, 120),
        ];
    }

    protected function broadcastTargetModeOptions(): array
    {
        return [
            'primary' => 'Один чат на сделку',
            'all' => 'Все чаты сделки',
        ];
    }

    private function broadcastEligibleDealsBaseQuery(int $accountId): Builder
    {
        [$startAt, $endAt] = $this->todayTaskWindow();

        return Deal::query()
            ->where('account_id', $accountId)
            ->whereNull('closed_at')
            ->whereIn('product_category', array_keys(Deal::productCategoryOptions()))
            ->whereHas('conversations', function ($query) {
                $query
                    ->whereIn('channel', ['vk', 'avito'])
                    ->whereNotNull('external_id')
                    ->where('external_id', '!=', '');
            })
            ->whereHas('tasks', function ($query) use ($startAt, $endAt) {
                $query
                    ->where('status', 'open')
                    ->whereNotNull('due_at')
                    ->where('due_at', '>=', $startAt)
                    ->where('due_at', '<', $endAt);
            });
    }

    private function broadcastConversationLabel(object $conversation): string
    {
        $sourceLabel = trim((string) ($conversation->source_label ?? ''));
        if ($sourceLabel !== '') {
            return $sourceLabel;
        }

        return match ((string) ($conversation->channel ?? '')) {
            'vk' => 'VK',
            'avito' => 'Avito',
            default => 'Чат',
        };
    }
}

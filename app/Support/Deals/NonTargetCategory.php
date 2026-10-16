<?php

namespace App\Support\Deals;

use App\Models\PipelineStage;

/**
 * Категории нецелевых обращений колл-центра потолков (deals.non_target_category). Оператор выбирает категорию, когда
 * закрывает сделку «Доп.Работы / Не целевой» или переводит её на этап «Нецелевое». Целевые исходы категорией не
 * отмечаются: «целевой записанный» — сделка дошла до «Замер назначен» (или закрыта «Успешно»), «целевой отказ» —
 * закрыта «Отказ». Только для пространства потолков: у кроссовок свои исходы.
 */
final class NonTargetCategory
{
    public const LABELS = [
        'dealer' => 'Дилерский',
        'installation' => 'Запись на монтаж',
        'extra_works' => 'Сливы и доп. работы',
        'spam' => 'Реклама и спам',
        'measurer' => 'Запрос на связь с замерщиком',
        'claim' => 'Претензии',
        'lighting' => 'Отдел светотехники',
    ];

    /** Колонки статистики: два целевых исхода и нецелевые по категориям. */
    public const COLUMNS = [
        'booked' => 'Целевой записанный',
        'lost' => 'Целевой отказ',
    ] + self::LABELS;

    public static function appliesTo(int $accountId): bool
    {
        return $accountId === (int) config('owner.ceilings_account_id');
    }

    public static function label(?string $key): ?string
    {
        return self::LABELS[(string) $key] ?? null;
    }

    public static function isNonTargetStage(?PipelineStage $stage): bool
    {
        // «Нецелевое дозвон» — другой этап (исходящие дозвоны), категория там не нужна
        return $stage !== null && mb_stripos((string) $stage->name, 'нецелев') !== false && ! OutboundDialStage::is($stage);
    }

    /** Этап «Нецелевое» пространства, если он есть. */
    public static function stageId(int $accountId): ?int
    {
        $id = PipelineStage::query()->where('account_id', $accountId)
            ->where('name', 'like', '%ецелев%')->where('name', 'not like', '%озвон%')->orderBy('sort')->value('id');

        return $id !== null ? (int) $id : null;
    }
}

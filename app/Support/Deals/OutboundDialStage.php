<?php

namespace App\Support\Deals;

use App\Models\PipelineStage;

/**
 * Этап «Нецелевое дозвон» (потолки): сделки, которые CRM заводит на исходящий звонок оператора — дозвоны клиентам,
 * незаключёнки. Это не лиды: в «Поступил лид» они не падают, категория нецелевого им не нужна, в статистике
 * менеджеров не считаются. Перезвонил клиент сам — сделка уходит в «Поступил лид» (MegafonVatsDealSync).
 */
final class OutboundDialStage
{
    public static function is(?PipelineStage $stage): bool
    {
        return $stage !== null && mb_stripos((string) $stage->name, 'дозвон') !== false;
    }

    public static function id(int $accountId): ?int
    {
        if (! NonTargetCategory::appliesTo($accountId)) {
            return null;
        }
        $id = PipelineStage::query()->where('account_id', $accountId)->where('name', 'like', '%озвон%')->orderBy('sort')->value('id');

        return $id !== null ? (int) $id : null;
    }

    /** Первый этап воронки — «Поступил лид». */
    public static function leadStageId(int $pipelineId): ?int
    {
        $id = PipelineStage::query()->where('pipeline_id', $pipelineId)->orderBy('sort')->orderBy('id')->value('id');

        return $id !== null ? (int) $id : null;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach ($this->obsoleteStages() as $obsoleteStage) {
            DB::table('pipeline_stages')
                ->where('name', $obsoleteStage['name'])
                ->orderBy('account_id')
                ->orderBy('pipeline_id')
                ->orderBy('sort')
                ->get()
                ->each(function (object $stage) {
                    $replacement = $this->resolveReplacementStage(
                        (int) $stage->account_id,
                        (int) $stage->pipeline_id,
                        (int) $stage->id,
                        (int) $stage->sort,
                    );

                    if (! $replacement) {
                        return;
                    }

                    $this->moveStageReferences((int) $stage->id, (int) $replacement->id);

                    DB::table('pipeline_stages')
                        ->where('id', (int) $stage->id)
                        ->delete();
                });
        }
    }

    public function down(): void
    {
        $pipelines = DB::table('pipelines')
            ->select(['id', 'account_id', 'is_default'])
            ->orderBy('account_id')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->groupBy('account_id');

        foreach ($pipelines as $accountId => $rows) {
            $pipelineId = (int) ($rows->firstWhere('is_default', 1)->id ?? $rows->first()->id ?? 0);

            if ($pipelineId <= 0) {
                continue;
            }

            foreach ($this->obsoleteStages() as $obsoleteStage) {
                $exists = DB::table('pipeline_stages')
                    ->where('account_id', (int) $accountId)
                    ->where('pipeline_id', $pipelineId)
                    ->where('name', $obsoleteStage['name'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('pipeline_stages')->insert([
                    'account_id' => (int) $accountId,
                    'pipeline_id' => $pipelineId,
                    'name' => $obsoleteStage['name'],
                    'sort' => $obsoleteStage['sort'],
                    'color' => null,
                    'is_final' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function moveStageReferences(int $fromStageId, int $toStageId): void
    {
        DB::table('deals')
            ->where('stage_id', $fromStageId)
            ->update([
                'stage_id' => $toStageId,
                'updated_at' => now(),
            ]);

        DB::table('deal_stage_history')
            ->where('from_stage_id', $fromStageId)
            ->update(['from_stage_id' => $toStageId]);

        DB::table('deal_stage_history')
            ->where('to_stage_id', $fromStageId)
            ->update(['to_stage_id' => $toStageId]);

        DB::table('deal_activities')
            ->where('type', 'stage_changed')
            ->where(function ($query) use ($fromStageId) {
                $query->where('payload->from_stage_id', $fromStageId)
                    ->orWhere('payload->to_stage_id', $fromStageId);
            })
            ->orderBy('id')
            ->chunkById(200, function (Collection $activities) use ($fromStageId, $toStageId) {
                foreach ($activities as $activity) {
                    $payload = $this->decodePayload($activity->payload ?? null);

                    if (! is_array($payload)) {
                        continue;
                    }

                    $changed = false;

                    if ((int) ($payload['from_stage_id'] ?? 0) === $fromStageId) {
                        $payload['from_stage_id'] = $toStageId;
                        $changed = true;
                    }

                    if ((int) ($payload['to_stage_id'] ?? 0) === $fromStageId) {
                        $payload['to_stage_id'] = $toStageId;
                        $changed = true;
                    }

                    if (! $changed) {
                        continue;
                    }

                    DB::table('deal_activities')
                        ->where('id', (int) $activity->id)
                        ->update([
                            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    private function resolveReplacementStage(int $accountId, int $pipelineId, int $stageId, int $sort): ?object
    {
        $baseQuery = DB::table('pipeline_stages')
            ->where('account_id', $accountId)
            ->where('pipeline_id', $pipelineId)
            ->where('id', '!=', $stageId);

        $nextNonFinal = (clone $baseQuery)
            ->where('sort', '>', $sort)
            ->where('is_final', 0)
            ->orderBy('sort')
            ->orderBy('id')
            ->first();

        if ($nextNonFinal) {
            return $nextNonFinal;
        }

        $previous = (clone $baseQuery)
            ->where('sort', '<', $sort)
            ->orderByDesc('sort')
            ->orderByDesc('id')
            ->first();

        if ($previous) {
            return $previous;
        }

        return (clone $baseQuery)
            ->orderBy('sort')
            ->orderBy('id')
            ->first();
    }

    private function decodePayload(mixed $payload): ?array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload) || trim($payload) === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function obsoleteStages(): array
    {
        return [
            [
                'name' => "\u{0417}\u{0430}\u{043C}\u{0435}\u{0440} \u{043F}\u{0440}\u{043E}\u{0448}\u{0435}\u{043B}/\u{0414}\u{043E}\u{0436}\u{0430}\u{0442}\u{044C} \u{0434}\u{043E} \u{0434}\u{043E}\u{0433}\u{043E}\u{0432}\u{043E}\u{0440}\u{0430}",
                'sort' => 50,
            ],
            [
                'name' => "\u{041D}\u{0435}\u{0437}\u{0430}\u{043A}\u{043B}\u{044E}\u{0447}\u{0435}\u{043D}\u{043A}\u{0430} (\u{043C}\u{043E}\u{043D}\u{0442}\u{0430}\u{0436} \u{0437}\u{0430}\u{0434}\u{0435}\u{0440}\u{0436}\u{0438}\u{0432}\u{0430}\u{0435}\u{0442}\u{0441}\u{044F})",
                'sort' => 55,
            ],
        ];
    }
};

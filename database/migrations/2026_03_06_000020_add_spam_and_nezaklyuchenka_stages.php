<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add extra stages for each default pipeline (best-effort, idempotent).
        $pipelines = DB::table('pipelines')
            ->select(['id','account_id','is_default'])
            ->orderBy('account_id')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->groupBy('account_id');

        foreach ($pipelines as $accountId => $rows) {
            $pipelineId = (int)($rows->firstWhere('is_default', 1)->id ?? $rows->first()->id);

            $this->ensureStage($accountId, $pipelineId, 'Спам', 15, null, false);
            $this->ensureStage($accountId, $pipelineId, 'Незаключенка (монтаж задерживается)', 55, null, false);
        }
    }

    private function ensureStage(int $accountId, int $pipelineId, string $name, int $sort, ?string $color, bool $isFinal): void
    {
        $exists = DB::table('pipeline_stages')
            ->where('account_id', $accountId)
            ->where('pipeline_id', $pipelineId)
            ->where('name', $name)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('pipeline_stages')->insert([
            'account_id' => $accountId,
            'pipeline_id' => $pipelineId,
            'name' => $name,
            'sort' => $sort,
            'color' => $color,
            'is_final' => $isFinal ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('pipeline_stages')->where('name', 'Спам')->delete();
        DB::table('pipeline_stages')->where('name', 'Незаключенка (монтаж задерживается)')->delete();
    }
};

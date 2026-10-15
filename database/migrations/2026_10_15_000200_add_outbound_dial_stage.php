<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Потолки: этап «Нецелевое дозвон» сразу после «Нецелевое» — туда CRM кладёт сделки, заведённые исходящим звонком
// оператора (дозвоны клиентам, незаключёнки), чтобы они не падали в «Поступил лид». Канбан прячет его, как «Нецелевое».
return new class extends Migration
{
    public function up(): void
    {
        $accountId = (int) config('owner.ceilings_account_id');
        $nonTarget = DB::table('pipeline_stages')->where('account_id', $accountId)
            ->where('name', 'like', '%ецелев%')->where('name', 'not like', '%озвон%')->orderBy('sort')->first();
        if (! $nonTarget || DB::table('pipeline_stages')->where('pipeline_id', $nonTarget->pipeline_id)->where('name', 'like', '%озвон%')->exists()) {
            return;
        }
        $next = DB::table('pipeline_stages')->where('pipeline_id', $nonTarget->pipeline_id)->where('sort', '>', $nonTarget->sort)->min('sort');
        if ($next !== null && $next - $nonTarget->sort < 2) {
            DB::table('pipeline_stages')->where('pipeline_id', $nonTarget->pipeline_id)->where('sort', '>', $nonTarget->sort)->increment('sort', 2);
        }
        DB::table('pipeline_stages')->insert([
            'account_id' => $accountId, 'pipeline_id' => $nonTarget->pipeline_id, 'name' => 'Нецелевое дозвон',
            'sort' => $nonTarget->sort + 1, 'is_final' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // этап не удаляем: в нём могут быть сделки
    }
};

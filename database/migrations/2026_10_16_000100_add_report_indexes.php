<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Сводка «Потолки» и «Отчёты» выбирают ленту сделок по типу и дате (звонки, формы, закрытия) и историю этапов по
// этапу и дате: без этих индексов каждый такой запрос перебирал все ~45 тысяч записей пространства (~200 мс).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deal_activities', function (Blueprint $table) {
            $table->index(['account_id', 'type', 'created_at'], 'deal_activities_account_type_created_index');
        });
        Schema::table('deal_stage_history', function (Blueprint $table) {
            $table->index(['account_id', 'to_stage_id', 'changed_at'], 'deal_stage_history_account_to_changed_index');
            $table->index(['account_id', 'changed_at'], 'deal_stage_history_account_changed_index');
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['account_id', 'direction', 'created_at'], 'messages_account_direction_created_index');
        });
        Schema::table('deals', function (Blueprint $table) {
            $table->index(['account_id', 'created_at'], 'deals_account_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('deal_activities', fn (Blueprint $table) => $table->dropIndex('deal_activities_account_type_created_index'));
        Schema::table('deal_stage_history', function (Blueprint $table) {
            $table->dropIndex('deal_stage_history_account_to_changed_index');
            $table->dropIndex('deal_stage_history_account_changed_index');
        });
        Schema::table('messages', fn (Blueprint $table) => $table->dropIndex('messages_account_direction_created_index'));
        Schema::table('deals', fn (Blueprint $table) => $table->dropIndex('deals_account_created_index'));
    }
};

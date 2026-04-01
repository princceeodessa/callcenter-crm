<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['deal_id']);
        });

        DB::statement('ALTER TABLE tasks MODIFY deal_id BIGINT UNSIGNED NULL');

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('deal_id')->references('id')->on('deals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['deal_id']);
        });

        DB::statement('ALTER TABLE tasks MODIFY deal_id BIGINT UNSIGNED NOT NULL');

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('deal_id')->references('id')->on('deals')->cascadeOnDelete();
        });
    }
};

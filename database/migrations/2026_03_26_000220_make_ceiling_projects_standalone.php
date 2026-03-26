<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ceiling_projects', function (Blueprint $table) {
            $table->dropForeign(['deal_id']);
        });

        DB::statement('ALTER TABLE ceiling_projects MODIFY deal_id BIGINT UNSIGNED NULL');

        Schema::table('ceiling_projects', function (Blueprint $table) {
            $table->foreign('deal_id')->references('id')->on('deals')->nullOnDelete();
            $table->string('reference_image_path')->nullable()->after('additional_cost');
        });
    }

    public function down(): void
    {
        Schema::table('ceiling_projects', function (Blueprint $table) {
            $table->dropForeign(['deal_id']);
            $table->dropColumn('reference_image_path');
        });

        DB::statement('ALTER TABLE ceiling_projects MODIFY deal_id BIGINT UNSIGNED NOT NULL');

        Schema::table('ceiling_projects', function (Blueprint $table) {
            $table->foreign('deal_id')->references('id')->on('deals')->cascadeOnDelete();
        });
    }
};

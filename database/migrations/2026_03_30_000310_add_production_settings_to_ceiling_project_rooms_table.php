<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ceiling_project_rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('ceiling_project_rooms', 'production_settings')) {
                $table->json('production_settings')->nullable()->after('light_line_shapes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ceiling_project_rooms', function (Blueprint $table) {
            if (Schema::hasColumn('ceiling_project_rooms', 'production_settings')) {
                $table->dropColumn('production_settings');
            }
        });
    }
};

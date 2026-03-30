<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ceiling_project_rooms', function (Blueprint $table): void {
            if (!Schema::hasColumn('ceiling_project_rooms', 'light_line_shapes')) {
                $table->json('light_line_shapes')->nullable()->after('feature_shapes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ceiling_project_rooms', function (Blueprint $table): void {
            if (Schema::hasColumn('ceiling_project_rooms', 'light_line_shapes')) {
                $table->dropColumn('light_line_shapes');
            }
        });
    }
};

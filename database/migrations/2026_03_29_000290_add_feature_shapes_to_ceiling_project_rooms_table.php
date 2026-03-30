<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ceiling_project_rooms', function (Blueprint $table) {
            $table->json('feature_shapes')->nullable()->after('shape_points');
        });
    }

    public function down(): void
    {
        Schema::table('ceiling_project_rooms', function (Blueprint $table) {
            $table->dropColumn('feature_shapes');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ceiling_project_room_elements', function (Blueprint $table) {
            $table->string('placement_mode', 16)->default('free')->after('quantity');
            $table->unsignedInteger('segment_index')->nullable()->after('placement_mode');
            $table->decimal('offset_m', 8, 2)->nullable()->after('segment_index');
        });
    }

    public function down(): void
    {
        Schema::table('ceiling_project_room_elements', function (Blueprint $table) {
            $table->dropColumn([
                'placement_mode',
                'segment_index',
                'offset_m',
            ]);
        });
    }
};

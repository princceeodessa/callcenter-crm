<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ceiling_projects', function (Blueprint $table) {
            $table->json('sketch_recognition')->nullable()->after('reference_image_path');
            $table->timestamp('sketch_recognized_at')->nullable()->after('sketch_recognition');
        });
    }

    public function down(): void
    {
        Schema::table('ceiling_projects', function (Blueprint $table) {
            $table->dropColumn(['sketch_recognition', 'sketch_recognized_at']);
        });
    }
};

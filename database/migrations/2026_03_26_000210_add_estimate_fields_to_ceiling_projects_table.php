<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ceiling_projects', function (Blueprint $table) {
            $table->decimal('canvas_price_per_m2', 12, 2)->default(0)->after('discount_percent');
            $table->decimal('profile_price_per_m', 12, 2)->default(0)->after('canvas_price_per_m2');
            $table->decimal('insert_price_per_m', 12, 2)->default(0)->after('profile_price_per_m');
            $table->decimal('spotlight_price', 12, 2)->default(0)->after('insert_price_per_m');
            $table->decimal('chandelier_price', 12, 2)->default(0)->after('spotlight_price');
            $table->decimal('pipe_price', 12, 2)->default(0)->after('chandelier_price');
            $table->decimal('curtain_niche_price', 12, 2)->default(0)->after('pipe_price');
            $table->decimal('ventilation_hole_price', 12, 2)->default(0)->after('curtain_niche_price');
            $table->decimal('mounting_price_per_m2', 12, 2)->default(0)->after('ventilation_hole_price');
            $table->decimal('additional_cost', 12, 2)->default(0)->after('mounting_price_per_m2');
        });
    }

    public function down(): void
    {
        Schema::table('ceiling_projects', function (Blueprint $table) {
            $table->dropColumn([
                'canvas_price_per_m2',
                'profile_price_per_m',
                'insert_price_per_m',
                'spotlight_price',
                'chandelier_price',
                'pipe_price',
                'curtain_niche_price',
                'ventilation_hole_price',
                'mounting_price_per_m2',
                'additional_cost',
            ]);
        });
    }
};

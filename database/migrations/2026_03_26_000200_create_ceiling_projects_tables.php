<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ceiling_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
            $table->foreignId('measurement_id')->nullable()->constrained('measurements')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('calculator_version', 16)->default('v1');
            $table->string('canvas_material', 32)->default('pvc');
            $table->string('canvas_texture', 32)->nullable();
            $table->string('canvas_color', 100)->nullable();
            $table->string('mounting_system', 64)->nullable();
            $table->decimal('waste_percent', 5, 2)->default(12);
            $table->decimal('extra_margin_m', 8, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'deal_id']);
            $table->index(['account_id', 'status']);
        });

        Schema::create('ceiling_project_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('ceiling_project_id')->constrained('ceiling_projects')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('name');
            $table->string('shape_type', 32)->default('rectangle');
            $table->decimal('width_m', 8, 2)->nullable();
            $table->decimal('length_m', 8, 2)->nullable();
            $table->decimal('height_m', 8, 2)->nullable();
            $table->unsignedSmallInteger('corners_count')->default(4);
            $table->decimal('manual_area_m2', 10, 2)->nullable();
            $table->decimal('manual_perimeter_m', 10, 2)->nullable();
            $table->json('shape_points')->nullable();
            $table->unsignedSmallInteger('spotlights_count')->default(0);
            $table->unsignedSmallInteger('chandelier_points_count')->default(0);
            $table->unsignedSmallInteger('pipes_count')->default(0);
            $table->unsignedSmallInteger('curtain_niches_count')->default(0);
            $table->unsignedSmallInteger('ventilation_holes_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['ceiling_project_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ceiling_project_rooms');
        Schema::dropIfExists('ceiling_projects');
    }
};

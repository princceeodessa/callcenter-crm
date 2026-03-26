<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ceiling_project_room_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('ceiling_project_room_id')->constrained('ceiling_project_rooms')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('type', 32);
            $table->string('label')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('x_m', 8, 2)->nullable();
            $table->decimal('y_m', 8, 2)->nullable();
            $table->decimal('length_m', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['ceiling_project_room_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ceiling_project_room_elements');
    }
};

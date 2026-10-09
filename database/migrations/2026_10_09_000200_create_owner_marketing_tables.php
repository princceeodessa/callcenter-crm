<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Реклама потолков для сводки владельца: дневные цифры VK Рекламы, Яндекс Директа, Авито и таблицы заявок
// (owner:marketing-collect) и состояние сбора по каждому источнику.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('owner_marketing_daily', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20);
            $table->date('day');
            $table->json('metrics');
            $table->timestamps();
            $table->unique(['source', 'day']);
        });

        Schema::create('owner_marketing_sources', function (Blueprint $table) {
            $table->string('source', 20)->primary();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_ok_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_marketing_sources');
        Schema::dropIfExists('owner_marketing_daily');
    }
};

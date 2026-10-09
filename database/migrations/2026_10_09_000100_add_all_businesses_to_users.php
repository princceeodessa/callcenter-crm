<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Владелец всех бизнесов: в сводке переключатель «Потолки | Кроссовки» (решение 09.10.2026 — только для владельца,
// у остальных пространства по-прежнему раздельные).
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('all_businesses')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('all_businesses');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->timestamp('returned_at')->nullable();   // оформлен возврат (пары вернулись на склад)
            $table->string('manual_source')->nullable();      // источник лида для кроссовок (Avito/Instagram/...)
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['returned_at', 'manual_source']);
        });
    }
};

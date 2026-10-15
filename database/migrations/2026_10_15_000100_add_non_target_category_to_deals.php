<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Нецелевая сделка потолков — по какой причине (дилерский, запись на монтаж, сливы и доп. работы, реклама и спам,
// запрос на связь с замерщиком, претензии): ставит оператор при закрытии «Не целевой» и при переводе на «Нецелевое».
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->string('non_target_category', 30)->nullable()->after('closed_reason');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn('non_target_category');
        });
    }
};

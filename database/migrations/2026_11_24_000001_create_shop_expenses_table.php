<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Расходы» кроссовок (24.11.2026): сотрудники вносят траты магазина (аренда, доставка, упаковка, такси…),
 * руководство видит все и итог «чистыми» в сводке. Траты своими деньгами помечаются к возврату.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->date('spent_on');
            $table->decimal('amount', 12, 2);
            $table->string('category', 32);
            $table->string('paid_from', 16);            // cash — из кассы, card — карта/счёт магазина, own — свои деньги (вернуть)
            $table->string('description', 255);
            $table->string('receipt_path')->nullable();  // фото чека — диск local, отдаётся через контроллер
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reimbursed_at')->nullable();
            $table->foreignId('reimbursed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['account_id', 'spent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_expenses');
    }
};

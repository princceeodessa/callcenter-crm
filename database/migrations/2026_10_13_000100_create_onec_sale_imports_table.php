<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1С «Обувь» → CRM (решение владельца 13.10.2026: белые пары продают в 1С чеками, CRM только подтягивает).
 * onec_sale_imports — строка чека (или возврата) 1С, принятая в CRM: какая сделка из неё вышла и на какую пару.
 * Ключ (документ 1С, номер строки) — повторный приём той же строки ничего не задваивает.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onec_sale_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('onec_uuid', 36);                   // документ 1С (чек, чек на возврат, отчёт без чеков)
            $table->string('row_key', 16);                     // номер строки в документе
            $table->string('doc_type', 8);                     // check | return | report
            $table->string('onec_number', 32)->nullable();
            $table->dateTime('onec_date');
            $table->string('card_code', 32)->nullable();       // карточка номенклатуры 1С
            $table->string('card_name', 255)->nullable();
            $table->string('marking_key', 64)->nullable();     // GTIN + серийный номер из кода маркировки
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('payment', 16)->nullable();          // cash | card | transfer
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24);                      // linked | unlinked | returned | return_unmatched | cancelled_in_1c
            $table->string('note', 255)->nullable();           // как нашли пару / почему не нашли
            $table->timestamps();
            $table->unique(['account_id', 'onec_uuid', 'row_key']);
            $table->index(['account_id', 'onec_date']);
            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onec_sale_imports');
    }
};

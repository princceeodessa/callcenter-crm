<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Продажи белых пар → 1С «Обувь» (один «Отчёт о розничных продажах» на день).
 *
 * deals.stock_white      — проданная пара белая (1, идёт в 1С), не белая (0), не определено (NULL: в позиции
 *                          лежат и белые, и серые пары — продавец уточняет).
 * deals.payment_method   — cash | card | transfer: в отчёте 1С наличные и безнал идут разными строками.
 * deals.onec_card_code   — карточка 1С, на которую ушла продажа (возврат идёт на неё же).
 * deals.onec_sale_day    — день отчёта 1С, в который ушла продажа (после возврата продажа остаётся в том дне).
 * onec_retail_days       — что и когда выгружено по каждому дню.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->boolean('stock_white')->nullable()->after('sold_unit_cost');
            $table->string('payment_method', 16)->nullable()->after('stock_white');
            $table->string('onec_card_code', 32)->nullable()->after('payment_method');
            $table->date('onec_sale_day')->nullable()->after('onec_card_code');
        });

        Schema::create('onec_retail_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->string('status', 16)->default('pending'); // pending | done | partial | error
            $table->string('rows_hash', 64)->nullable();      // состав дня, который выгружен
            $table->string('onec_uuid', 36)->nullable();
            $table->string('onec_number', 32)->nullable();
            $table->unsignedInteger('pairs')->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->json('unmapped')->nullable();              // пары, которым в 1С не нашлось карточки с остатком
            $table->text('error')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onec_retail_days');
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['stock_white', 'payment_method', 'onec_card_code', 'onec_sale_day']);
        });
    }
};

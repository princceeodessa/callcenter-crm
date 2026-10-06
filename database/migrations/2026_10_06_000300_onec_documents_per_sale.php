<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1С «Обувь»: вместо одного отчёта о розничных продажах на день — отдельный документ на каждую продажу белой пары
 * (и на каждый возврат). onec_sale_docs — что и когда ушло в 1С по сделке. Дневные таблица и поля (с 000200)
 * ни разу не использовались — убираются.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onec_sale_docs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 8);                         // sale | return
            $table->string('status', 16)->default('pending');  // done | waiting (нет карточки с остатком) | error
            $table->string('rows_hash', 64)->nullable();       // содержимое, с которым была последняя попытка
            $table->string('onec_uuid', 36)->nullable();
            $table->string('onec_number', 32)->nullable();
            $table->dateTime('onec_date')->nullable();
            $table->string('card_code', 32)->nullable();       // карточка 1С; возврат идёт на карточку продажи
            $table->decimal('amount', 12, 2)->default(0);
            $table->text('reason')->nullable();                // почему ждёт / текст ошибки
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'deal_id', 'kind']);
            $table->index(['account_id', 'status']);
        });

        Schema::dropIfExists('onec_retail_days');
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['onec_card_code', 'onec_sale_day']);
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->string('onec_card_code', 32)->nullable()->after('payment_method');
            $table->date('onec_sale_day')->nullable()->after('onec_card_code');
        });
        Schema::create('onec_retail_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->string('status', 16)->default('pending');
            $table->string('rows_hash', 64)->nullable();
            $table->string('onec_uuid', 36)->nullable();
            $table->string('onec_number', 32)->nullable();
            $table->unsignedInteger('pairs')->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->json('unmapped')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'day']);
        });
        Schema::dropIfExists('onec_sale_docs');
    }
};

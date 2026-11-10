<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Подарочные сертификаты кроссовок (10.11.2026): номиналы с остатком непроданных, продажи сертификатов (не выручка —
 * выручка, когда сертификатом платят за пару) и оплата сертификатом у продажи пары.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('nominal');            // ₽
            $table->integer('stock')->default(0);          // непроданных на руках
            $table->timestamps();
            $table->unique(['account_id', 'nominal']);
        });

        Schema::create('gift_certificate_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('nominal');
            $table->unsignedInteger('qty');
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 16)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_name')->nullable();
            $table->string('client_phone', 32)->nullable();
            $table->dateTime('sold_at');
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['account_id', 'sold_at']);
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->decimal('cert_value', 12, 2)->nullable()->after('payment_method');      // сертификатами (номиналы; остаток сверх цены сгорает)
            $table->string('pay_extra_method', 16)->nullable()->after('cert_value');       // доплата сверх сертификатов
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['cert_value', 'pay_extra_method']);
        });
        Schema::dropIfExists('gift_certificate_sales');
        Schema::dropIfExists('gift_certificates');
    }
};

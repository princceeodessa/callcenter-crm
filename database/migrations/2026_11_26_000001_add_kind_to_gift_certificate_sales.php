<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Обналичивание подарочного сертификата (26.11.2026): клиент сдаёт сертификат и получает деньги — строка журнала
 * сертификатов с kind = cashout (деньги из кассы, не выручка и не расход; сертификат возвращается в остаток).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_certificate_sales', function (Blueprint $table) {
            $table->string('kind', 16)->default('sale')->after('account_id');   // sale — продали, cashout — обналичили
        });
    }

    public function down(): void
    {
        Schema::table('gift_certificate_sales', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};

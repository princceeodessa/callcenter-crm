<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Продажи, внесённые без привязки к складу (загрузка истории 07.07.2026): склад → «Продажи без списания».
 * stock_linked_at — пару выбрали при сверке и списали (можно отменить);
 * stock_link_skipped_at — «списывать не нужно» (пары не было на складе / уже списали вручную).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->timestamp('stock_linked_at')->nullable()->after('stock_deducted_at');
            $table->timestamp('stock_link_skipped_at')->nullable()->after('stock_linked_at');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['stock_linked_at', 'stock_link_skipped_at']);
        });
    }
};

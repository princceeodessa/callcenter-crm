<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->decimal('avg_cost', 12, 2)->nullable()->after('sale_price'); // средневзвешенная себестоимость пары
            $table->integer('reserved')->default(0)->after('quantity');           // зарезервировано открытыми сделками
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->integer('stocked_quantity')->nullable()->after('stocked_at'); // сколько пар реально оприходовано (для ре-синка)
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->decimal('sold_unit_cost', 12, 2)->nullable();   // снимок себестоимости пары на момент продажи
            $table->timestamp('stock_reserved_at')->nullable();      // резерв со склада (стадия «Бронь/Счёт»)
        });

        Schema::table('pipeline_stages', function (Blueprint $table) {
            $table->boolean('is_reserve')->default(false)->after('is_final'); // стадия, резервирующая товар на складе
        });
    }

    public function down(): void
    {
        Schema::table('pipeline_stages', function (Blueprint $table) {
            $table->dropColumn('is_reserve');
        });
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['sold_unit_cost', 'stock_reserved_at']);
        });
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('stocked_quantity');
        });
        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->dropColumn(['avg_cost', 'reserved']);
        });
    }
};

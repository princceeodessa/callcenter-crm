<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('warehouse_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->string('brand')->default('');
            $table->string('model')->default('');
            $table->string('size', 50)->default('');
            $table->integer('quantity')->default(0);
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->integer('low_stock_threshold')->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'brand', 'model', 'size']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('warehouse_item_id')->constrained('warehouse_items')->cascadeOnDelete();
            $table->string('type', 32);              // in, in_reversal, out, out_reversal, replenish, adjust
            $table->integer('quantity');             // signed delta (+ приход, - расход)
            $table->string('source_type', 32)->nullable(); // purchase, deal, manual
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'warehouse_item_id']);
        });

        Schema::table('purchase_stages', function (Blueprint $table) {
            $table->boolean('is_stock_in')->default(false)->after('is_final');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->timestamp('stocked_at')->nullable()->after('closed_at');
            $table->foreignId('warehouse_item_id')->nullable()->after('stocked_at')
                ->constrained('warehouse_items')->nullOnDelete();
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->foreignId('warehouse_item_id')->nullable()->constrained('warehouse_items')->nullOnDelete();
            $table->integer('sold_quantity')->nullable();
            $table->timestamp('stock_deducted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_item_id');
            $table->dropColumn(['sold_quantity', 'stock_deducted_at']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_item_id');
            $table->dropColumn('stocked_at');
        });

        Schema::table('purchase_stages', function (Blueprint $table) {
            $table->dropColumn('is_stock_in');
        });

        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('warehouse_items');
    }
};

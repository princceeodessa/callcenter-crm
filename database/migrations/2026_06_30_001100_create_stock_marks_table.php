<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('warehouse_item_id')->nullable()->constrained('warehouse_items')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->string('code', 512);                           // DataMatrix («Честный знак») или другой уникальный код
            $table->string('status', 32)->default('in_stock');     // in_stock, sold, returned
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'code']);
            $table->index(['account_id', 'status']);
            $table->index(['warehouse_item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_marks');
    }
};

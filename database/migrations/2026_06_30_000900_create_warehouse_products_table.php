<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('warehouse_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->string('brand')->default('');
            $table->string('model')->default('');
            $table->string('custom_name')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'brand', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_products');
    }
};

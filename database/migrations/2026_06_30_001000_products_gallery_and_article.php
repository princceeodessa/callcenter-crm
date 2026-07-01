<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('warehouse_products', function (Blueprint $table) {
            $table->string('article', 64)->nullable()->after('model');
            $table->unique(['account_id', 'article']);
        });

        Schema::create('warehouse_product_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_product_id')->constrained('warehouse_products')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['warehouse_product_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_product_photos');
        Schema::table('warehouse_products', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'article']);
            $table->dropColumn('article');
        });
    }
};

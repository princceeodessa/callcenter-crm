<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('warehouse_products', function (Blueprint $table) {
            $table->string('category', 32)->nullable()->after('custom_name');   // sport / casual / premium / winter / kids
            $table->string('gender', 16)->nullable()->after('category');        // male / female / unisex / kids
            $table->string('season', 16)->nullable()->after('gender');          // summer / winter / demi

            $table->index(['account_id', 'category']);
            $table->index(['account_id', 'gender']);
            $table->index(['account_id', 'season']);
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_products', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'category']);
            $table->dropIndex(['account_id', 'gender']);
            $table->dropIndex(['account_id', 'season']);
            $table->dropColumn(['category', 'gender', 'season']);
        });
    }
};

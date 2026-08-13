<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->integer('consigned')->default(0)->after('reserved');
        });

        Schema::create('warehouse_consignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('warehouse_item_id')->constrained('warehouse_items')->cascadeOnDelete();
            $table->string('consignee');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('status', 16)->default('given'); // given, sold, returned
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('given_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->boolean('is_white')->default(false)->after('article');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('is_white');
        });

        Schema::dropIfExists('warehouse_consignments');

        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->dropColumn('consigned');
        });
    }
};

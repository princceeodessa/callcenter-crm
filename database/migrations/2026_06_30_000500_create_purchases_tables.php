<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->string('name');
            $table->integer('sort')->default(100);
            $table->string('color', 32)->nullable();
            $table->boolean('is_final')->default(false);
            $table->timestamps();

            $table->index(['account_id', 'sort']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('purchase_stage_id')->constrained('purchase_stages');
            $table->string('title');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('size', 50)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('supplier')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->char('currency', 3)->default('RUB');
            $table->decimal('expected_sale_price', 12, 2)->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('article')->nullable();
            $table->text('notes')->nullable();
            $table->integer('sort')->default(100);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'purchase_stage_id']);
            $table->index(['account_id', 'responsible_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('purchase_stages');
    }
};

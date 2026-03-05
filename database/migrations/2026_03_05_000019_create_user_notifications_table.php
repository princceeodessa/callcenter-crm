<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('type', 50); // e.g. task_due
            $table->string('title');
            $table->text('body')->nullable();

            // Prevent duplicates like "task #123 is due".
            $table->string('source_type', 30)->nullable(); // task, deal, ...
            $table->unsignedBigInteger('source_id')->nullable();

            $table->json('payload')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['account_id','user_id','is_read','id']);
            $table->unique(['user_id','type','source_type','source_id'], 'uniq_user_notif_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};

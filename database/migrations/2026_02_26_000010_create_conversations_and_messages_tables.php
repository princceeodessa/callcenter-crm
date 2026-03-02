<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('conversations', function (Blueprint $table) {
      $table->id();
      $table->foreignId('account_id')->constrained('accounts');
      $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
      $table->string('channel', 50);
      $table->string('external_id')->nullable();
      $table->string('status', 30)->default('open');
      $table->integer('unread_count')->default(0);
      $table->dateTime('last_message_at')->nullable();
      $table->json('meta')->nullable();
      $table->timestamps();

      $table->index(['deal_id']);
    });

    Schema::create('messages', function (Blueprint $table) {
      $table->id();
      $table->foreignId('account_id')->constrained('accounts');
      $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
      $table->string('direction', 10);
      $table->string('author')->nullable();
      $table->text('body')->nullable();
      $table->json('payload')->nullable();
      $table->timestamps();

      $table->index(['conversation_id','created_at']);
    });
  }

  public function down(): void {
    Schema::dropIfExists('messages');
    Schema::dropIfExists('conversations');
  }
};

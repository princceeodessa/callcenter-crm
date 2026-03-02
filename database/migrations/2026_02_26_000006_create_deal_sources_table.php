<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('deal_sources', function (Blueprint $table) {
      $table->id();
      $table->foreignId('account_id')->constrained('accounts');
      $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
      $table->string('channel', 50);
      $table->string('source_name')->nullable();
      $table->text('source_url')->nullable();
      $table->json('path')->nullable();
      $table->json('meta')->nullable();
      $table->timestamps();

      $table->index(['deal_id']);
    });
  }
  public function down(): void {
    Schema::dropIfExists('deal_sources');
  }
};

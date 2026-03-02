<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('deal_activities', function (Blueprint $table) {
      $table->id();
      $table->foreignId('account_id')->constrained('accounts');
      $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
      $table->foreignId('author_user_id')->nullable()->constrained('users');
      $table->string('type', 50);
      $table->text('body')->nullable();
      $table->json('payload')->nullable();
      $table->timestamps();

      $table->index(['deal_id','created_at']);
    });
  }
  public function down(): void {
    Schema::dropIfExists('deal_activities');
  }
};

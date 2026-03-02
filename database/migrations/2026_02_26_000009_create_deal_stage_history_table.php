<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('deal_stage_history', function (Blueprint $table) {
      $table->id();
      $table->foreignId('account_id')->constrained('accounts');
      $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
      $table->foreignId('from_stage_id')->nullable()->constrained('pipeline_stages');
      $table->foreignId('to_stage_id')->constrained('pipeline_stages');
      $table->foreignId('changed_by_user_id')->nullable()->constrained('users');
      $table->dateTime('changed_at');
      $table->index(['deal_id','changed_at']);
    });
  }
  public function down(): void {
    Schema::dropIfExists('deal_stage_history');
  }
};

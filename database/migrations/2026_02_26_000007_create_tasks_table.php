<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('tasks', function (Blueprint $table) {
      $table->id();
      $table->foreignId('account_id')->constrained('accounts');
      $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
      $table->foreignId('assigned_user_id')->nullable()->constrained('users');
      $table->string('title');
      $table->text('description')->nullable();
      $table->string('status', 30)->default('open');
      $table->dateTime('due_at')->nullable();
      $table->dateTime('completed_at')->nullable();
      $table->timestamps();

      $table->index(['deal_id','status']);
      $table->index(['account_id','assigned_user_id','status','due_at']);
    });
  }
  public function down(): void {
    Schema::dropIfExists('tasks');
  }
};

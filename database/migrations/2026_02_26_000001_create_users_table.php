<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('users', function (Blueprint $table) {
      $table->id();
      $table->foreignId('account_id')->constrained('accounts');
      $table->string('name');
      $table->string('email')->nullable();
      $table->string('password');
      $table->string('role', 50)->default('operator');
      $table->boolean('is_active')->default(true);
      $table->rememberToken();
      $table->timestamps();

      $table->unique(['account_id','email']);
      $table->index(['account_id','is_active']);
    });
  }
  public function down(): void {
    Schema::dropIfExists('users');
  }
};

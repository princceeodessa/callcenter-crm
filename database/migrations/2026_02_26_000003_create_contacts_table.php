<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('contacts', function (Blueprint $table) {
      $table->id();
      $table->foreignId('account_id')->constrained('accounts');
      $table->string('name')->nullable();
      $table->string('phone', 32)->nullable();
      $table->string('email')->nullable();
      $table->timestamps();

      $table->index(['account_id','phone']);
    });
  }
  public function down(): void {
    Schema::dropIfExists('contacts');
  }
};

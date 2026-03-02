<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('tags', function (Blueprint $table) {
      $table->id();
      $table->foreignId('account_id')->constrained('accounts');
      $table->string('name', 100);
      $table->string('color', 32)->nullable();
      $table->timestamps();
      $table->unique(['account_id','name']);
    });

    Schema::create('deal_tags', function (Blueprint $table) {
      $table->foreignId('account_id')->constrained('accounts');
      $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
      $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
      $table->primary(['deal_id','tag_id']);
      $table->index(['account_id']);
    });
  }

  public function down(): void {
    Schema::dropIfExists('deal_tags');
    Schema::dropIfExists('tags');
  }
};

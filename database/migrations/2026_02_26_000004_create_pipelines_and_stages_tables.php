<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('pipelines', function (Blueprint $table) {
      $table->id();
      $table->foreignId('account_id')->constrained('accounts');
      $table->string('name');
      $table->boolean('is_default')->default(false);
      $table->timestamps();
      $table->index(['account_id','is_default']);
    });

    Schema::create('pipeline_stages', function (Blueprint $table) {
      $table->id();
      $table->foreignId('account_id')->constrained('accounts');
      $table->foreignId('pipeline_id')->constrained('pipelines');
      $table->string('name');
      $table->integer('sort')->default(100);
      $table->string('color', 32)->nullable();
      $table->boolean('is_final')->default(false);
      $table->timestamps();

      $table->index(['pipeline_id','sort']);
    });
  }

  public function down(): void {
    Schema::dropIfExists('pipeline_stages');
    Schema::dropIfExists('pipelines');
  }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('deals', function (Blueprint $table) {
      $table->id();
      $table->foreignId('account_id')->constrained('accounts');
      $table->foreignId('pipeline_id')->constrained('pipelines');
      $table->foreignId('stage_id')->constrained('pipeline_stages');
      $table->string('title');
      $table->foreignId('contact_id')->nullable()->constrained('contacts');
      $table->foreignId('responsible_user_id')->nullable()->constrained('users');
      $table->decimal('amount', 12, 2)->nullable();
      $table->char('currency', 3)->default('RUB');
      $table->string('readiness_status', 50)->nullable();
      $table->boolean('is_unread')->default(false);
      $table->boolean('has_script_deviation')->default(false);
      $table->timestamp('closed_at')->nullable();
      $table->timestamps();

      $table->index(['account_id','pipeline_id','stage_id']);
      $table->index(['account_id','responsible_user_id','stage_id']);
    });
  }
  public function down(): void {
    Schema::dropIfExists('deals');
  }
};

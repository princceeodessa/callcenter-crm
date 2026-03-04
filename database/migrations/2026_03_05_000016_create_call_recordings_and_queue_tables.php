<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('call_recordings', function (Blueprint $table) {
      $table->id();
      $table->foreignId('account_id')->constrained('accounts');
      $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
      $table->string('callid', 128)->index();
      $table->text('recording_url')->nullable();
      $table->string('local_path', 512)->nullable();
      $table->integer('duration_seconds')->nullable();

      $table->string('transcript_status', 30)->default('none'); // none|queued|processing|done|failed
      $table->longText('transcript_text')->nullable();
      $table->text('transcript_error')->nullable();
      $table->timestamp('transcribed_at')->nullable();

      $table->timestamps();

      $table->unique(['account_id','callid']);
      $table->index(['deal_id','created_at']);
    });

    // Queue tables for database driver (created only if missing)
    if (!Schema::hasTable('jobs')) {
      Schema::create('jobs', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts')->default(0);
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
      });
    }

    if (!Schema::hasTable('failed_jobs')) {
      Schema::create('failed_jobs', function (Blueprint $table) {
        $table->id();
        $table->string('uuid')->unique();
        $table->text('connection');
        $table->text('queue');
        $table->longText('payload');
        $table->longText('exception');
        $table->timestamp('failed_at')->useCurrent();
      });
    }
  }

  public function down(): void {
    Schema::dropIfExists('call_recordings');
    // Do not drop jobs/failed_jobs automatically (might be used by other apps)
  }
};

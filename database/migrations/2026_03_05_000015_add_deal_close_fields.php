<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('deals', function (Blueprint $table) {
      if (!Schema::hasColumn('deals', 'closed_result')) {
        $table->string('closed_result', 20)->nullable()->after('closed_at'); // won|lost
      }
      if (!Schema::hasColumn('deals', 'closed_reason')) {
        $table->string('closed_reason', 255)->nullable()->after('closed_result');
      }
      if (!Schema::hasColumn('deals', 'closed_by_user_id')) {
        $table->foreignId('closed_by_user_id')->nullable()->after('closed_reason')->constrained('users');
      }
      $table->index(['account_id','closed_at','closed_result']);
    });
  }

  public function down(): void {
    Schema::table('deals', function (Blueprint $table) {
      if (Schema::hasColumn('deals', 'closed_by_user_id')) {
        $table->dropConstrainedForeignId('closed_by_user_id');
      }
      if (Schema::hasColumn('deals', 'closed_reason')) {
        $table->dropColumn('closed_reason');
      }
      if (Schema::hasColumn('deals', 'closed_result')) {
        $table->dropColumn('closed_result');
      }
    });
  }
};

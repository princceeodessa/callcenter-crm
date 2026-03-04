<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'external_id')) {
                $table->string('external_id', 255)->nullable()->after('body');
            }
            if (!Schema::hasColumn('messages', 'status')) {
                $table->string('status', 30)->default('ok')->after('payload');
            }
            if (!Schema::hasColumn('messages', 'error')) {
                $table->text('error')->nullable()->after('status');
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Drop index first (name may vary). This is best-effort.
            try {
                $table->dropIndex(['conversation_id', 'external_id']);
            } catch (Throwable $e) {
                // ignore
            }
            if (Schema::hasColumn('messages', 'error')) {
                $table->dropColumn('error');
            }
            if (Schema::hasColumn('messages', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('messages', 'external_id')) {
                $table->dropColumn('external_id');
            }
        });
    }
};

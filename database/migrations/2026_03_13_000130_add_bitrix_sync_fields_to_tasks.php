<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'external_provider')) {
                $table->string('external_provider', 50)->nullable()->after('completed_at');
            }

            if (! Schema::hasColumn('tasks', 'external_id')) {
                $table->string('external_id', 191)->nullable()->after('external_provider');
            }

            if (! Schema::hasColumn('tasks', 'external_sync_status')) {
                $table->string('external_sync_status', 30)->nullable()->after('external_id');
            }

            if (! Schema::hasColumn('tasks', 'external_sync_error')) {
                $table->text('external_sync_error')->nullable()->after('external_sync_status');
            }

            if (! Schema::hasColumn('tasks', 'external_payload')) {
                $table->json('external_payload')->nullable()->after('external_sync_error');
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['external_provider', 'external_sync_status'], 'tasks_external_provider_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_external_provider_status_idx');
        });

        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'external_payload')) {
                $table->dropColumn('external_payload');
            }

            if (Schema::hasColumn('tasks', 'external_sync_error')) {
                $table->dropColumn('external_sync_error');
            }

            if (Schema::hasColumn('tasks', 'external_sync_status')) {
                $table->dropColumn('external_sync_status');
            }

            if (Schema::hasColumn('tasks', 'external_id')) {
                $table->dropColumn('external_id');
            }

            if (Schema::hasColumn('tasks', 'external_provider')) {
                $table->dropColumn('external_provider');
            }
        });
    }
};
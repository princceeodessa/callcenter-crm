<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dateTime('notified_at')->nullable()->after('due_at');
            $table->index(['account_id','assigned_user_id','status','due_at','notified_at'], 'tasks_notify_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_notify_lookup');
            $table->dropColumn('notified_at');
        });
    }
};

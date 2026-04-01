<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ceiling_projects', function (Blueprint $table) {
            $table->foreignId('archived_by_user_id')->nullable()->after('updated_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->after('last_calculated_at');
            $table->unsignedBigInteger('archived_slot')->default(0)->after('archived_at');

            $table->dropUnique('ceiling_projects_account_id_deal_id_unique');
            $table->unique(['account_id', 'deal_id', 'archived_slot'], 'ceiling_projects_account_deal_archive_unique');
            $table->index(['account_id', 'archived_at'], 'ceiling_projects_account_archived_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('ceiling_projects', function (Blueprint $table) {
            $table->dropUnique('ceiling_projects_account_deal_archive_unique');
            $table->dropIndex('ceiling_projects_account_archived_at_index');
            $table->dropForeign(['archived_by_user_id']);
            $table->dropColumn(['archived_by_user_id', 'archived_at', 'archived_slot']);

            $table->unique(['account_id', 'deal_id']);
        });
    }
};

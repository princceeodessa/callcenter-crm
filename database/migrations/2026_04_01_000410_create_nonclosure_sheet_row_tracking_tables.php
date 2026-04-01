<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('non_closure_sheet_row_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('workbook_sheet_id')->constrained('non_closure_workbook_sheets')->cascadeOnDelete();
            $table->unsignedInteger('row_index');
            $table->string('status', 32)->default('new')->index();
            $table->text('comment')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['workbook_sheet_id', 'row_index'], 'non_closure_sheet_row_states_sheet_row_unique');
            $table->index(['account_id', 'workbook_sheet_id'], 'non_closure_sheet_row_states_account_sheet_index');
        });

        Schema::create('non_closure_sheet_row_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('workbook_sheet_id')->constrained('non_closure_workbook_sheets')->cascadeOnDelete();
            $table->foreignId('row_state_id')->nullable()->constrained('non_closure_sheet_row_states')->nullOnDelete();
            $table->unsignedInteger('row_index');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32)->default('note')->index();
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'workbook_sheet_id', 'row_index'], 'non_closure_sheet_row_activities_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_closure_sheet_row_activities');
        Schema::dropIfExists('non_closure_sheet_row_states');
    }
};

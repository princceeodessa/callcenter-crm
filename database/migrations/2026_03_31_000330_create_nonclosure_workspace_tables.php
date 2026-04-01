<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('non_closure_workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('title')->default('Незаключёнка Workspace');
            $table->longText('document_html')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('account_id');
        });

        Schema::create('non_closure_workbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained('non_closure_workspaces')->cascadeOnDelete();
            $table->string('title');
            $table->string('source_name')->nullable();
            $table->string('source_hash', 64)->nullable()->index();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('summary')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'workspace_id']);
        });

        Schema::create('non_closure_workbook_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('workbook_id')->constrained('non_closure_workbooks')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('category', 32)->default('other')->index();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('header_row_index')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('column_count')->default(0);
            $table->longText('header')->nullable();
            $table->longText('rows')->nullable();
            $table->longText('notes')->nullable();
            $table->longText('preview_text')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['workbook_id', 'slug']);
            $table->index(['workbook_id', 'position']);
        });

        Schema::create('non_closure_workbook_sheet_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workbook_sheet_id')->constrained('non_closure_workbook_sheets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('can_edit')->default(false);
            $table->timestamps();

            $table->unique(['workbook_sheet_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_closure_workbook_sheet_user');
        Schema::dropIfExists('non_closure_workbook_sheets');
        Schema::dropIfExists('non_closure_workbooks');
        Schema::dropIfExists('non_closure_workspaces');
    }
};

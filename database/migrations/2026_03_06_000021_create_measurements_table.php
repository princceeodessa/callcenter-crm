<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();

            $table->dateTime('scheduled_at')->index();
            $table->unsignedInteger('duration_minutes')->default(60);

            $table->string('address', 500)->default('');
            $table->string('phone', 32)->nullable()->index();
            $table->string('status', 32)->default('planned')->index();

            $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();

            $table->text('callcenter_comment')->nullable();
            $table->text('measurer_comment')->nullable();

            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurements');
    }
};

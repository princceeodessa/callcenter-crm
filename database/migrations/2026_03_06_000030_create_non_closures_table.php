<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('non_closures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();
            $table->date('entry_date')->nullable()->index();
            $table->string('address', 500)->default('');
            $table->text('reason')->nullable();

            $table->unsignedBigInteger('measurer_user_id')->nullable()->index();
            $table->string('measurer_name', 120)->nullable()->index();

            $table->unsignedBigInteger('responsible_user_id')->nullable()->index();
            $table->string('responsible_name', 120)->nullable()->index();

            $table->text('comment')->nullable();
            $table->date('follow_up_date')->nullable()->index();
            $table->string('result_status', 32)->nullable()->index();
            $table->text('special_calculation')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->unsignedBigInteger('updated_by_user_id')->nullable()->index();

            $table->string('source', 32)->default('manual')->index();
            $table->string('unique_hash', 64)->nullable()->index();

            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->foreign('measurer_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('responsible_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_closures');
    }
};

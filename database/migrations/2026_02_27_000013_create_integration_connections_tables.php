<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('provider', 50); // vk/avito/telegram/megafon_vats
            $table->string('status', 30)->default('disabled'); // disabled/active/error
            $table->json('settings')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'provider']);
            $table->index(['account_id', 'provider', 'status']);

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
        });

        Schema::create('integration_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('provider', 50);
            $table->string('direction', 10)->default('in'); // in/out
            $table->string('event_type', 80)->nullable();
            $table->string('external_id', 255)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('received_at')->useCurrent();

            $table->index(['provider', 'received_at']);
            $table->index(['account_id', 'provider', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_events');
        Schema::dropIfExists('integration_connections');
    }
};

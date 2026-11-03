<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Несколько подключений одного провайдера в аккаунте — связанный кабинет Авито («Дмитрий Мастер», 03.11.2026).
 * Чей чат — помнит conversations.meta.avito_user_id (App\Services\Integrations\AvitoConnections).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_connections', function (Blueprint $table) {
            $table->index(['account_id', 'provider'], 'integration_connections_account_provider_idx');
        });
        Schema::table('integration_connections', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::table('integration_connections', function (Blueprint $table) {
            $table->unique(['account_id', 'provider']);
        });
        Schema::table('integration_connections', function (Blueprint $table) {
            $table->dropIndex('integration_connections_account_provider_idx');
        });
    }
};

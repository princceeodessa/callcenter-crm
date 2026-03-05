<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\CallRecordingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\UserController as SettingsUserController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Webhooks\MegafonVatsWebhookController;
use App\Http\Controllers\Webhooks\TelegramWebhookController;
use App\Http\Controllers\Webhooks\VkWebhookController;
use App\Http\Controllers\Webhooks\AvitoWebhookController;
use App\Http\Controllers\MediaController;

// Webhooks (public)
Route::post('/webhooks/megafon/vats', [MegafonVatsWebhookController::class, 'handle'])
    ->name('webhooks.megafon.vats');

Route::post('/webhooks/telegram', [TelegramWebhookController::class, 'handle'])
    ->name('webhooks.telegram');

Route::post('/webhooks/vk', [VkWebhookController::class, 'handle'])
    ->name('webhooks.vk');

// Avito: POST (events) + GET (OAuth redirect)
Route::match(['GET','POST'], '/webhooks/avito', [AvitoWebhookController::class, 'handle'])
    ->name('webhooks.avito');

Route::get('/', function () {
    return redirect()->route('deals.kanban');
})->middleware('auth');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.perform');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/deals/kanban', [DealController::class, 'kanban'])->name('deals.kanban');
    Route::get('/deals', [DealController::class, 'index'])->name('deals.index');
    Route::get('/deals/closed', [DealController::class, 'closed'])->name('deals.closed');
    Route::get('/deals/create', [DealController::class, 'create'])->name('deals.create');
    Route::post('/deals', [DealController::class, 'store'])->name('deals.store');
    Route::get('/deals/{deal}', [DealController::class, 'show'])->name('deals.show');
    Route::patch('/deals/{deal}', [DealController::class, 'update'])->name('deals.update');
    Route::post('/deals/{deal}/stage', [DealController::class, 'changeStage'])->name('deals.stage');
    Route::post('/deals/{deal}/close', [DealController::class, 'close'])->name('deals.close');

    // Kanban drag&drop move
    Route::post('/deals/{deal}/move', [DealController::class, 'move'])->name('deals.move');

    Route::post('/deals/{deal}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/poll', [NotificationController::class, 'poll'])->name('notifications.poll');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Call recordings
    Route::post('/recordings/{recording}/transcribe', [CallRecordingController::class, 'transcribe'])->name('recordings.transcribe');

    // Media proxy (Telegram files without exposing bot token)
    Route::get('/media/telegram/{conversation}/{fileId}', [MediaController::class, 'telegram'])
        ->name('media.telegram');

    // Settings: integrations (main_operator only)
    Route::middleware('admin')->group(function () {
        Route::get('/settings/integrations', [IntegrationController::class, 'index'])->name('settings.integrations.index');
        Route::get('/settings/integrations/{provider}', [IntegrationController::class, 'show'])->name('settings.integrations.show');
        Route::post('/settings/integrations/{provider}/connect', [IntegrationController::class, 'connect'])->name('settings.integrations.connect');
        Route::post('/settings/integrations/{provider}/disconnect', [IntegrationController::class, 'disconnect'])->name('settings.integrations.disconnect');
        Route::post('/settings/integrations/{provider}/test-send', [IntegrationController::class, 'testSend'])->name('settings.integrations.testSend');

        // Avito OAuth flow (redirect starts here, callback hits /webhooks/avito)
        Route::get('/settings/integrations/avito/oauth/start', [IntegrationController::class, 'avitoOauthStart'])
            ->name('settings.integrations.avito.oauth.start');
    });

    // Settings: users (admin only)
    Route::get('/settings/users', [SettingsUserController::class, 'index'])
        ->middleware('admin')
        ->name('settings.users.index');
    Route::post('/settings/users', [SettingsUserController::class, 'store'])
        ->middleware('admin')
        ->name('settings.users.store');
    Route::post('/settings/users/{user}/toggle', [SettingsUserController::class, 'toggleActive'])
        ->middleware('admin')
        ->name('settings.users.toggle');

    // Chats (Messenger)
    Route::get('/chats', [ChatController::class, 'index'])->name('chats.index');
    Route::get('/chats/{conversation}', [ChatController::class, 'show'])->name('chats.show');
    Route::get('/chats/{conversation}/poll', [ChatController::class, 'poll'])->name('chats.poll');
    Route::post('/chats/{conversation}/messages', [ChatController::class, 'send'])->name('chats.send');
    Route::post('/chats/{conversation}/read', [ChatController::class, 'markRead'])->name('chats.read');

    // Reports (main_operator only)
    Route::get('/reports/monthly', [ReportController::class, 'monthly'])
        ->middleware('admin')
        ->name('reports.monthly');
});
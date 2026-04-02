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
use App\Http\Controllers\Webhooks\TildaWebhookController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MeasurementController;
use App\Http\Controllers\NonClosureController;
use App\Http\Controllers\CeilingProjectController;
use App\Http\Controllers\Settings\BitrixImportController;

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

Route::post('/webhooks/tilda', [TildaWebhookController::class, 'handle'])
    ->name('webhooks.tilda');

Route::get('/', function () {
    $user = request()->user();

    if (!$user) {
        return redirect()->route('login');
    }

    return redirect()->route(match ($user->role) {
        'measurer' => 'calendar.index',
        'constructor' => 'ceiling-projects.index',
        'documents_operator' => 'nonclosures.index',
        default => 'deals.kanban',
    });
})->middleware('auth');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.perform');
Route::match(['GET', 'POST'], '/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/poll', [NotificationController::class, 'poll'])->name('notifications.poll');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::middleware('desk')->group(function () {
        Route::get('/deals/kanban', [DealController::class, 'kanban'])->name('deals.kanban');
        Route::get('/deals', [DealController::class, 'index'])->name('deals.index');
        Route::get('/deals/closed', [DealController::class, 'closed'])->name('deals.closed');
        Route::get('/deals/create', [DealController::class, 'create'])->name('deals.create');
        Route::post('/deals', [DealController::class, 'store'])->name('deals.store');
        Route::get('/deals/{deal}', [DealController::class, 'show'])->name('deals.show');
        Route::patch('/deals/{deal}', [DealController::class, 'update'])->name('deals.update');
        Route::post('/deals/{deal}/stage', [DealController::class, 'changeStage'])->name('deals.stage');
        Route::post('/deals/{deal}/close', [DealController::class, 'close'])->name('deals.close');
        Route::post('/deals/broadcast-today', [DealController::class, 'broadcastToday'])->name('deals.broadcast-today');
        Route::middleware('admin.only')->group(function () {
            Route::get('/deals/{deal}/ceiling-project', [CeilingProjectController::class, 'showForDeal'])->name('deals.ceiling-project.show');
        });

        // Kanban drag&drop move
        Route::post('/deals/{deal}/move', [DealController::class, 'move'])->name('deals.move');
        Route::post('/deals/bulk-move', [DealController::class, 'bulkMove'])->name('deals.bulk-move');

        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::post('/tasks', [TaskController::class, 'storeFromPage'])->name('tasks.page.store');
        Route::post('/deals/{deal}/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');

        // Call recordings
        Route::post('/recordings/{recording}/transcribe', [CallRecordingController::class, 'transcribe'])->name('recordings.transcribe');

        // Media proxy (Telegram files without exposing bot token)
        Route::get('/media/telegram/{conversation}/{fileId}', [MediaController::class, 'telegram'])
            ->name('media.telegram');

        // Chats (Messenger)
        Route::get('/chats', [ChatController::class, 'index'])->name('chats.index');
        Route::post('/chats/read-all', [ChatController::class, 'markAllRead'])->name('chats.read-all');
        Route::get('/chats/{conversation}', [ChatController::class, 'show'])->name('chats.show');
        Route::get('/chats/{conversation}/poll', [ChatController::class, 'poll'])->name('chats.poll');
        Route::post('/chats/{conversation}/messages', [ChatController::class, 'send'])->name('chats.send');
        Route::post('/chats/{conversation}/read', [ChatController::class, 'markRead'])->name('chats.read');
    });

    // Projecting (admin + constructor)
    Route::middleware('projecting')->group(function () {
        Route::get('/ceiling-projects', [CeilingProjectController::class, 'index'])->name('ceiling-projects.index');
        Route::post('/ceiling-projects', [CeilingProjectController::class, 'store'])->name('ceiling-projects.store');
        Route::get('/ceiling-projects/{project}', [CeilingProjectController::class, 'show'])->name('ceiling-projects.show');
        Route::get('/ceiling-projects/{project}/drafting', [CeilingProjectController::class, 'drafting'])->name('ceiling-projects.drafting');
        Route::post('/ceiling-projects/{project}/duplicate', [CeilingProjectController::class, 'duplicate'])->name('ceiling-projects.duplicate');
        Route::patch('/ceiling-projects/{project}/archive', [CeilingProjectController::class, 'archive'])->name('ceiling-projects.archive');
        Route::patch('/ceiling-projects/{project}/restore', [CeilingProjectController::class, 'restore'])->name('ceiling-projects.restore');
        Route::patch('/ceiling-projects/{project}', [CeilingProjectController::class, 'update'])->name('ceiling-projects.update');
        Route::delete('/ceiling-projects/{project}', [CeilingProjectController::class, 'destroy'])->name('ceiling-projects.destroy');
        Route::post('/ceiling-projects/{project}/apply-estimate', [CeilingProjectController::class, 'applyEstimate'])->name('ceiling-projects.apply-estimate');
        Route::post('/ceiling-projects/{project}/sketch-image', [CeilingProjectController::class, 'uploadSketchSheet'])->name('ceiling-projects.sketch-image.upload');
        Route::get('/ceiling-projects/{project}/sketch-image', [CeilingProjectController::class, 'sketchImage'])->name('ceiling-projects.sketch-image.show');
        Route::post('/ceiling-projects/{project}/sketch-crop', [CeilingProjectController::class, 'saveSketchCrop'])->name('ceiling-projects.sketch-crop.update');
        Route::post('/ceiling-projects/{project}/reference-image', [CeilingProjectController::class, 'uploadReferenceImage'])->name('ceiling-projects.reference-image.upload');
        Route::get('/ceiling-projects/{project}/reference-image', [CeilingProjectController::class, 'referenceImage'])->name('ceiling-projects.reference-image.show');
        Route::post('/ceiling-projects/{project}/sketch-recognition', [CeilingProjectController::class, 'recognizeSketchCrop'])->name('ceiling-projects.sketch-recognition');
        Route::post('/ceiling-projects/{project}/sketch-recognition/apply', [CeilingProjectController::class, 'applySketchRecognition'])->name('ceiling-projects.sketch-recognition.apply');
        Route::post('/ceiling-projects/{project}/rooms', [CeilingProjectController::class, 'storeRoom'])->name('ceiling-projects.rooms.store');
        Route::patch('/ceiling-projects/{project}/rooms/{room}', [CeilingProjectController::class, 'updateRoom'])->name('ceiling-projects.rooms.update');
        Route::patch('/ceiling-projects/{project}/rooms/{room}/geometry', [CeilingProjectController::class, 'updateRoomGeometry'])->name('ceiling-projects.rooms.geometry.update');
        Route::get('/ceiling-projects/{project}/rooms/{room}/panels', [CeilingProjectController::class, 'roomPanels'])->name('ceiling-projects.rooms.panels.show');
        Route::get('/ceiling-projects/{project}/production', [CeilingProjectController::class, 'productionPacket'])->name('ceiling-projects.production.show');
        Route::post('/ceiling-projects/{project}/rooms/{room}/elements', [CeilingProjectController::class, 'storeRoomElement'])->name('ceiling-projects.rooms.elements.store');
        Route::patch('/ceiling-projects/{project}/rooms/{room}/elements/{element}', [CeilingProjectController::class, 'updateRoomElement'])->name('ceiling-projects.rooms.elements.update');
        Route::delete('/ceiling-projects/{project}/rooms/{room}/elements/{element}', [CeilingProjectController::class, 'destroyRoomElement'])->name('ceiling-projects.rooms.elements.destroy');
        Route::delete('/ceiling-projects/{project}/rooms/{room}', [CeilingProjectController::class, 'destroyRoom'])->name('ceiling-projects.rooms.destroy');
    });

    // Settings: integrations (admin only)
    Route::middleware('admin.only')->group(function () {
        Route::get('/settings/integrations', [IntegrationController::class, 'index'])->name('settings.integrations.index');
        Route::get('/settings/integrations/{provider}', [IntegrationController::class, 'show'])->name('settings.integrations.show');
        Route::post('/settings/integrations/{provider}/connect', [IntegrationController::class, 'connect'])->name('settings.integrations.connect');
        Route::post('/settings/integrations/{provider}/disconnect', [IntegrationController::class, 'disconnect'])->name('settings.integrations.disconnect');
        Route::post('/settings/integrations/{provider}/test-send', [IntegrationController::class, 'testSend'])->name('settings.integrations.testSend');
        Route::get('/settings/imports/bitrix', [BitrixImportController::class, 'index'])->name('settings.imports.bitrix.index');
        Route::post('/settings/imports/bitrix', [BitrixImportController::class, 'import'])->name('settings.imports.bitrix.import');

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

    // Reports (personal for users, aggregated for main_operator/admin)
    Route::get('/reports/monthly', [ReportController::class, 'monthly'])
        ->middleware('reports')
        ->name('reports.monthly');

    // Measurements calendar (admin + call-center + measurers)
    Route::middleware('calendar')->group(function () {
        Route::get('/calendar', [MeasurementController::class, 'index'])->name('calendar.index');
        Route::get('/calendar/events', [MeasurementController::class, 'events'])->name('calendar.events');
        Route::post('/calendar/measurements', [MeasurementController::class, 'store'])->name('calendar.store');
        Route::patch('/calendar/measurements/{measurement}', [MeasurementController::class, 'update'])->name('calendar.update');
        Route::post('/calendar/measurements/{measurement}/claim', [MeasurementController::class, 'claim'])->name('calendar.claim');
        Route::post('/calendar/measurements/{measurement}/release', [MeasurementController::class, 'release'])->name('calendar.release');
    });

    // Non-closures table (admin + call-center)
    Route::middleware('nonclosure')->group(function () {
        Route::get('/nonclosures', [NonClosureController::class, 'index'])->name('nonclosures.index');
        Route::get('/nonclosures/sheets/{sheet}', [NonClosureController::class, 'showSheet'])->name('nonclosures.sheets.show');
        Route::post('/nonclosures/sheets/{sheet}/rows', [NonClosureController::class, 'storeRow'])->name('nonclosures.sheets.rows.store');
        Route::patch('/nonclosures/sheets/{sheet}/rows/{rowIndex}', [NonClosureController::class, 'updateRowState'])->name('nonclosures.sheets.rows.update');
        Route::delete('/nonclosures/sheets/{sheet}/rows/{rowIndex}', [NonClosureController::class, 'destroyRow'])->name('nonclosures.sheets.rows.destroy');
        Route::post('/nonclosures/sheets/{sheet}/columns', [NonClosureController::class, 'storeColumn'])->name('nonclosures.sheets.columns.store');
        Route::patch('/nonclosures/sheets/{sheet}/columns/{columnIndex}', [NonClosureController::class, 'updateColumn'])->name('nonclosures.sheets.columns.update');
        Route::delete('/nonclosures/sheets/{sheet}/columns/{columnIndex}', [NonClosureController::class, 'destroyColumn'])->name('nonclosures.sheets.columns.destroy');
        Route::patch('/nonclosures/sheets/{sheet}/metrics', [NonClosureController::class, 'updateMetrics'])->name('nonclosures.sheets.metrics.update');
        Route::post('/nonclosures/sheets/{sheet}/tasks', [TaskController::class, 'storeDocumentTask'])->name('nonclosures.sheets.tasks.store');
        Route::patch('/nonclosures/workspace', [NonClosureController::class, 'updateWorkspace'])->name('nonclosures.workspace.update');
        Route::post('/nonclosures/workbooks/import', [NonClosureController::class, 'importWorkbook'])->name('nonclosures.workbooks.import');
        Route::patch('/nonclosures/workbooks/{workbook}/access', [NonClosureController::class, 'updateWorkbookAccess'])->name('nonclosures.workbooks.access.update');
        Route::patch('/nonclosures/sheets/{sheet}/access', [NonClosureController::class, 'updateSheetAccess'])->name('nonclosures.sheets.access.update');
        Route::post('/nonclosures', [NonClosureController::class, 'store'])->name('nonclosures.store');
        Route::patch('/nonclosures/{nonclosure}', [NonClosureController::class, 'update'])->name('nonclosures.update');
        Route::post('/nonclosures/import', [NonClosureController::class, 'import'])->name('nonclosures.import');
    });
});

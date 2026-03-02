<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Webhooks\MegafonVatsWebhookController;

// Webhooks (public)
Route::post('/webhooks/megafon/vats', [MegafonVatsWebhookController::class, 'handle'])
    ->name('webhooks.megafon.vats');

Route::get('/', function () {
    return redirect()->route('deals.kanban');
})->middleware('auth');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.perform');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/deals/kanban', [DealController::class, 'kanban'])->name('deals.kanban');
    Route::get('/deals', [DealController::class, 'index'])->name('deals.index');
    Route::get('/deals/create', [DealController::class, 'create'])->name('deals.create');
    Route::post('/deals', [DealController::class, 'store'])->name('deals.store');
    Route::get('/deals/{deal}', [DealController::class, 'show'])->name('deals.show');
    Route::post('/deals/{deal}/stage', [DealController::class, 'changeStage'])->name('deals.stage');

    // Kanban drag&drop move
    Route::post('/deals/{deal}/move', [DealController::class, 'move'])->name('deals.move');

    Route::post('/deals/{deal}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');

    // Settings: integrations
    Route::get('/settings/integrations', [IntegrationController::class, 'index'])->name('settings.integrations.index');
    Route::get('/settings/integrations/{provider}', [IntegrationController::class, 'show'])->name('settings.integrations.show');
    Route::post('/settings/integrations/{provider}/connect', [IntegrationController::class, 'connect'])->name('settings.integrations.connect');
    Route::post('/settings/integrations/{provider}/disconnect', [IntegrationController::class, 'disconnect'])->name('settings.integrations.disconnect');
});
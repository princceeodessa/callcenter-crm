<?php

use App\Http\Controllers\Api\OnecRetailController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn() => ['ok' => true]);

// Станция 1С «Обувь»: продажи белых пар → «Отчёт о розничных продажах» (по токену подключения onec_obuv)
Route::get('/onec/retail/pending', [OnecRetailController::class, 'pending'])->name('api.onec.pending');
Route::post('/onec/retail/days/{day}', [OnecRetailController::class, 'result'])->name('api.onec.result');

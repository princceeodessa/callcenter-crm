<?php

use App\Http\Controllers\Api\OnecRetailController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn() => ['ok' => true]);

// Станция 1С «Обувь»: продажи белых пар → «Отчёт о розничных продажах» на каждую продажу (по токену подключения onec_obuv)
Route::get('/onec/retail/pending', [OnecRetailController::class, 'pending'])->name('api.onec.pending');
Route::post('/onec/retail/results', [OnecRetailController::class, 'results'])->name('api.onec.results');
// С 13.10.2026 наоборот: белые продажи делают в 1С чеками, станция привозит их в CRM (статистика и склад)
Route::get('/onec/retail/import-since', [OnecRetailController::class, 'importSince'])->name('api.onec.import-since');
Route::post('/onec/retail/imports', [OnecRetailController::class, 'imports'])->name('api.onec.imports');

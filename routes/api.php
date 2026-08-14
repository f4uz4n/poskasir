<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['api.token', 'api.sync'])->group(function () {
    Route::get('/sync/pull', [SyncController::class, 'pull'])->name('api.v1.sync.pull');
    Route::post('/sync/push', [SyncController::class, 'push'])->name('api.v1.sync.push');

    Route::get('/products', [ActivityController::class, 'products'])->name('api.v1.products');
    Route::get('/categories', [ActivityController::class, 'categories'])->name('api.v1.categories');
    Route::get('/transactions', [ActivityController::class, 'transactions'])->name('api.v1.transactions');
    Route::get('/purchases', [ActivityController::class, 'purchases'])->name('api.v1.purchases');
    Route::get('/receivables', [ActivityController::class, 'receivables'])->name('api.v1.receivables');
    Route::get('/payables', [ActivityController::class, 'payables'])->name('api.v1.payables');
    Route::get('/stock-opnames', [ActivityController::class, 'stockOpnames'])->name('api.v1.stock-opnames');

    Route::prefix('reports')->name('api.v1.reports.')->group(function () {
        Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('/stock', [ReportController::class, 'stock'])->name('stock');
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/roi', [ReportController::class, 'roi'])->name('roi');
    });
});

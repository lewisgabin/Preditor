<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DrawController;
use App\Http\Controllers\Api\V1\LotteryController;
use App\Http\Controllers\Api\V1\SyncRunController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');

Route::prefix('v1/auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/lotteries', [LotteryController::class, 'index'])->name('lotteries.index');
    Route::get('/lotteries/{lottery}', [LotteryController::class, 'show'])->name('lotteries.show');
    Route::get('/draws', [DrawController::class, 'index'])->name('draws.index');
    Route::get('/draws/{draw}', [DrawController::class, 'show'])->name('draws.show');
    Route::get('/sync-runs', [SyncRunController::class, 'index'])->name('sync-runs.index');
    Route::get('/sync-runs/{syncRun}', [SyncRunController::class, 'show'])->name('sync-runs.show');
});

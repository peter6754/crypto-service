<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CryptoBalanceController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']); // только получить токен для юзера из сидов

Route::middleware('auth:sanctum')->prefix('crypto')->group(function () {
    Route::get('/balance', [CryptoBalanceController::class, 'balance']);
    Route::post('/deposit', [CryptoBalanceController::class, 'deposit']);
    Route::post('/withdraw', [CryptoBalanceController::class, 'withdraw']);
    Route::post('/withdraw/pending', [CryptoBalanceController::class, 'createPendingWithdraw']);
    Route::post('/withdraw/confirm', [CryptoBalanceController::class, 'confirmWithdraw']);
    Route::post('/withdraw/cancel', [CryptoBalanceController::class, 'cancelWithdraw']);
    Route::get('/transactions', [CryptoBalanceController::class, 'transactions']);
});

<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('jobs', [JobController::class, 'index']);
    Route::post('jobs', [JobController::class, 'store']);
    Route::put('jobs/{job}', [JobController::class, 'update']);
    Route::get('jobs/staff-options', [JobController::class, 'staffOptions']);
    Route::get('jobs/{job}', [JobController::class, 'show']);
    Route::patch('jobs/{job}/status', [JobController::class, 'updateStatus']);
    Route::post('jobs/{job}/assign', [JobController::class, 'assign']);
    Route::post('jobs/{job}/notes', [JobController::class, 'updateNotes']);
    Route::post('jobs/{job}/whatsapp-sent', [JobController::class, 'markWhatsappSent']);
    Route::post('jobs/{job}/images', [JobController::class, 'uploadImage']);
    Route::delete('jobs/{job}/images/{image}', [JobController::class, 'deleteImage']);
    Route::post('jobs/{job}/payments', [PaymentController::class, 'store']);

    Route::get('inventory', [InventoryController::class, 'index']);
    Route::get('inventory/{item}/movements', [InventoryController::class, 'movements']);
    Route::post('inventory/{item}/movements', [InventoryController::class, 'storeMovement']);

    Route::get('customers', [CustomerController::class, 'index']);
    Route::post('customers', [CustomerController::class, 'store']);
    Route::get('customers/{customer}', [CustomerController::class, 'show']);

    Route::get('finance/invoices', [FinanceController::class, 'invoices']);
    Route::get('finance/payments', [FinanceController::class, 'payments']);
    Route::get('finance/daily-close', [FinanceController::class, 'dailyClose']);
    Route::get('finance/monthly-csv', [FinanceController::class, 'monthlyCsv']);
});

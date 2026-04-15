<?php

use App\Http\Controllers\Api\FinanceController;
use Illuminate\Support\Facades\Route;

require __DIR__ . '/api.php';

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('finance')->group(function () {
        Route::post('/invoices/{invoiceId}/payments', [FinanceController::class, 'recordInvoicePayment']);
    });
});

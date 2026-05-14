<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AdminManagementController;
use Illuminate\Support\Facades\Route;

// Note: File will be fully reconstructed with all required routes below

Route::get('/health', static fn () => response()->json([
    'status' => 'ok',
    'service' => 'nozan-backend',
]));

Route::prefix('auth')->middleware('')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// Public endpoints - no authentication required
Route::post('/bookings', [BookingController::class, 'store'])->middleware('throttle:public-booking');
Route::get('/public/jobs/{job}/tracking', [JobController::class, 'publicTracking']);

// Admin diagnostics endpoints
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/whatsapp-status', [AdminManagementController::class, 'whatsappStatus']);
    Route::post('/admin/whatsapp-test', [AdminManagementController::class, 'whatsappTest']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/app-config', [AppConfigController::class, 'index']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::prefix('jobs')->group(function () {
        Route::get('/', [JobController::class, 'index']);
        Route::post('/', [JobController::class, 'store']);
        Route::get('/staff-options', [JobController::class, 'staffOptions']);
        Route::get('/overdue-summary', [JobController::class, 'overdueSummary']);

        Route::get('/{job}', [JobController::class, 'show']);
        Route::put('/{job}', [JobController::class, 'update']);
        Route::delete('/{job}', [JobController::class, 'destroy']);
        Route::any('/{job}/status', [JobController::class, 'updateStatus']);
        Route::post('/{job}/assign', [JobController::class, 'assign']);
        Route::post('/{job}/notes', [JobController::class, 'updateNotes']);
        Route::post('/{job}/whatsapp-sent', [JobController::class, 'markWhatsappSent']);
        Route::post('/{job}/images', [JobController::class, 'uploadImage']);
        Route::delete('/{job}/images/{image}', [JobController::class, 'deleteImage']);
        Route::post('/{job}/return', [JobController::class, 'createReturnJob']);
        Route::post('/{job}/payments', [PaymentController::class, 'store']);
    });

    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::get('/{customer}', [CustomerController::class, 'show']);
    });

    Route::prefix('finance')->group(function () {
        Route::get('/dashboard', [FinanceController::class, 'dashboard']);
        Route::get('/invoices', [FinanceController::class, 'invoices']);
        Route::get('/invoice-candidates', [FinanceController::class, 'invoiceCandidates']);
        Route::post('/invoices', [FinanceController::class, 'storeInvoice']);
        // Note: POST /invoices/{invoiceId}/payments is registered in api_with_invoice_payment.php (bootstrap workaround)
        Route::get('/payments', [FinanceController::class, 'payments']);
        Route::get('/debts', [FinanceController::class, 'debts']);
        Route::get('/expenses', [FinanceController::class, 'expenses']);
        Route::post('/expenses', [FinanceController::class, 'storeExpense']);
        Route::get('/daily-close', [FinanceController::class, 'dailyClose']);
        Route::get('/monthly-reports', [FinanceController::class, 'monthlyReports']);
        Route::get('/monthly-csv', [FinanceController::class, 'monthlyCsv']);
    });

    Route::prefix('inventory')->group(function () {
        Route::get('/', [InventoryController::class, 'index']);
        Route::post('/', [InventoryController::class, 'store']);
        Route::get('/{item}', [InventoryController::class, 'show']);
        Route::put('/{item}', [InventoryController::class, 'update']);
        Route::delete('/{item}', [InventoryController::class, 'destroy']);
        Route::get('/{item}/movements', [InventoryController::class, 'movements']);
        Route::post('/{item}/movements', [InventoryController::class, 'recordMovement']);
    });

    Route::prefix('admin')->group(function () {
        Route::get('/staff', [AdminManagementController::class, 'staffIndex']);
        Route::post('/staff', [AdminManagementController::class, 'createStaff']);
        Route::delete('/staff/{user}', [AdminManagementController::class, 'removeStaff']);
        Route::post('/staff/{user}/restore', [AdminManagementController::class, 'restoreStaff']);
        Route::post('/staff/{user}/reset-password', [AdminManagementController::class, 'resetStaffPassword']);

        Route::put('/management/sections', [AdminManagementController::class, 'updateSections']);
        Route::put('/management/intake-form', [AdminManagementController::class, 'updateIntakeForm']);
        Route::put('/management/hidden-staff', [AdminManagementController::class, 'updateHiddenStaffIds']);

        Route::prefix('bookings')->group(function () {
            Route::get('/', [BookingController::class, 'index']);
            Route::post('/{booking}/convert', [BookingController::class, 'convertToJob']);
            Route::get('/{booking}', [BookingController::class, 'show']);
            Route::put('/{booking}', [BookingController::class, 'update']);
            Route::delete('/{booking}', [BookingController::class, 'destroy']);
        });
    });
});
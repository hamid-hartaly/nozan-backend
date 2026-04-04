<?php

use App\Models\ServiceJob;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');
Route::view('/ops-preview', 'ops.preview');

Route::get('/login', function () {
	return redirect('/admin/login');
})->name('login');

Route::middleware('auth')->get('/admin/invoices/{serviceJob}/print', function (ServiceJob $serviceJob) {
	$serviceJob->load(['customer', 'payments']);

	return view('invoices.print', [
		'job' => $serviceJob,
	]);
})->name('invoices.print');

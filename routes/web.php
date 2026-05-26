<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\PayrollRunController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::resource('employees', EmployeeController::class);
    Route::resource('holidays', HolidayController::class);
    Route::resource('payroll-runs', PayrollRunController::class);
    Route::post('payroll-runs/{payrollRun}/upload', [PayrollRunController::class, 'upload'])->name('payroll-runs.upload');
    Route::post('payroll-runs/{payrollRun}/compute', [PayrollRunController::class, 'compute'])->name('payroll-runs.compute');
    Route::post('payroll-runs/{payrollRun}/lock', [PayrollRunController::class, 'lock'])->name('payroll-runs.lock');
});

require __DIR__.'/settings.php';

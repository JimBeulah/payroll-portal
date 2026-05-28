<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\PayrollEntryController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\PayslipController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::resource('employees', EmployeeController::class);
    Route::resource('holidays', HolidayController::class);
    Route::resource('payroll-runs', PayrollRunController::class);
    Route::get('payroll-runs/{payrollRun}/debug', [PayrollRunController::class, 'debug'])->name('payroll-runs.debug');
    Route::post('payroll-runs/{payrollRun}/upload', [PayrollRunController::class, 'upload'])->name('payroll-runs.upload');
    Route::delete('attendance-uploads/{attendanceUpload}', [PayrollRunController::class, 'destroyUpload'])->name('attendance-uploads.destroy');
    Route::post('payroll-runs/{payrollRun}/compute', [PayrollRunController::class, 'compute'])->name('payroll-runs.compute');
    Route::post('payroll-runs/{payrollRun}/entries', [PayrollRunController::class, 'storeEntry'])->name('payroll-runs.entries.store');
    Route::post('payroll-runs/{payrollRun}/lock', [PayrollRunController::class, 'lock'])->name('payroll-runs.lock');
    Route::put('payroll-entries/{payrollEntry}', [PayrollEntryController::class, 'update'])->name('payroll-entries.update');
    Route::get('payroll-entries/{payrollEntry}/payslip', [PayslipController::class, 'download'])->name('payslip.download');
    Route::get('payroll-runs/{payrollRun}/payslips/download-all', [PayslipController::class, 'downloadAll'])->name('payslip.download-all');
    Route::get('payroll-runs/{payrollRun}/export', [PayrollRunController::class, 'export'])->name('payroll-runs.export');
});

require __DIR__.'/settings.php';

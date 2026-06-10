<?php

use App\Http\Controllers\AttendanceUploadController;
use App\Http\Controllers\PayrollManualAttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\PayrollComputeController;
use App\Http\Controllers\PayrollEntryController;
use App\Http\Controllers\PayrollExportController;
use App\Http\Controllers\PayrollLockController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\PayslipController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

// TEMPORARY EXPORT — remove after use
Route::get('/__export/{token}', function (string $token) {
    abort_if($token !== 'beulah-export-2026', 403);

    $data = [
        'employees'                 => DB::table('employees')->get(),
        'users'                     => DB::table('users')->get(),
        'holidays'                  => DB::table('holidays')->get(),
        'app_settings'              => DB::table('app_settings')->get(),
        'payroll_runs'              => DB::table('payroll_runs')->get(),
        'payroll_entries'           => DB::table('payroll_entries')->get(),
        'payroll_manual_attendances'=> DB::table('payroll_manual_attendances')->get(),
        'attendance_uploads'        => DB::table('attendance_uploads')->get(),
    ];

    return response()->json($data)
        ->header('Content-Disposition', 'attachment; filename="payroll-export-' . now()->format('Y-m-d') . '.json"');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('employees', EmployeeController::class);
    Route::resource('holidays', HolidayController::class);

    Route::resource('payroll-runs', PayrollRunController::class)->only(['index', 'create', 'store', 'show', 'destroy']);

    Route::post('payroll-runs/{payrollRun}/upload', [AttendanceUploadController::class, 'store'])->name('payroll-runs.upload');
    Route::delete('attendance-uploads/{attendanceUpload}', [AttendanceUploadController::class, 'destroy'])->name('attendance-uploads.destroy');

    Route::post('payroll-runs/{payrollRun}/manual-attendances', [PayrollManualAttendanceController::class, 'store'])->name('payroll-runs.manual-attendances.store');
    Route::put('payroll-manual-attendances/{payrollManualAttendance}', [PayrollManualAttendanceController::class, 'update'])->name('payroll-manual-attendances.update');
    Route::delete('payroll-manual-attendances/{payrollManualAttendance}', [PayrollManualAttendanceController::class, 'destroy'])->name('payroll-manual-attendances.destroy');

    Route::post('payroll-runs/{payrollRun}/compute', [PayrollComputeController::class, 'store'])->name('payroll-runs.compute');

    Route::post('payroll-runs/{payrollRun}/lock', [PayrollLockController::class, 'store'])->name('payroll-runs.lock');
    Route::post('payroll-runs/{payrollRun}/unlock', [PayrollLockController::class, 'destroy'])->name('payroll-runs.unlock');

    Route::put('payroll-entries/{payrollEntry}', [PayrollEntryController::class, 'update'])->name('payroll-entries.update');

    Route::get('payroll-entries/{payrollEntry}/payslip', [PayslipController::class, 'download'])->name('payslip.download');
    Route::get('payroll-runs/{payrollRun}/payslips/download-all', [PayslipController::class, 'downloadAll'])->name('payslip.download-all');
    Route::get('payroll-runs/{payrollRun}/payslips/print', [PayslipController::class, 'printAll'])->name('payslip.print-all');

    Route::get('payroll-runs/{payrollRun}/export', [PayrollExportController::class, 'show'])->name('payroll-runs.export');
});

require __DIR__.'/settings.php';

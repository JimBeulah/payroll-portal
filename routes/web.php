<?php

use App\Http\Controllers\AttendanceUploadController;
use App\Http\Controllers\CashAdvanceRequestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeAttendanceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\PayrollComputeController;
use App\Http\Controllers\PayrollEntryController;
use App\Http\Controllers\PayrollExportController;
use App\Http\Controllers\PayrollLockController;
use App\Http\Controllers\PayrollManualAttendanceController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RequestApprovalController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard is the shared landing route. Employees are redirected to their
    // request portal from inside the controller (they have no payroll access).
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // --- Employee request portal (any authenticated user with a linked employee) ---
    Route::get('my-requests', [CashAdvanceRequestController::class, 'index'])->name('my-requests.index');
    Route::post('my-requests/cash-advance', [CashAdvanceRequestController::class, 'store'])->name('my-requests.cash-advance.store');
    Route::post('my-requests/leave', [LeaveRequestController::class, 'store'])->name('my-requests.leave.store');
    Route::get('my-attendance', [EmployeeAttendanceController::class, 'index'])->name('my-attendance.index');

    // --- Push notification subscriptions (any authenticated user) ---
    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

    // --- Admin + HR + Overseer: read-only payroll/employee/holiday data & approvals list ---
    // Overseer can view everything below but cannot create/edit/delete/approve/compute/lock.
    Route::middleware('role:admin,hr,overseer')->group(function () {
        Route::resource('employees', EmployeeController::class)->only(['index', 'show']);
        Route::resource('holidays', HolidayController::class)->only(['index', 'show']);
        Route::resource('payroll-runs', PayrollRunController::class)->only(['index', 'show']);

        Route::get('payroll-entries/{payrollEntry}/payslip', [PayslipController::class, 'download'])->name('payslip.download');
        Route::get('payroll-runs/{payrollRun}/payslips/download-all', [PayslipController::class, 'downloadAll'])->name('payslip.download-all');
        Route::get('payroll-runs/{payrollRun}/payslips/print', [PayslipController::class, 'printAll'])->name('payslip.print-all');

        Route::get('payroll-runs/{payrollRun}/export', [PayrollExportController::class, 'show'])->name('payroll-runs.export');

        Route::get('approvals', [RequestApprovalController::class, 'index'])->name('approvals.index');
    });

    // --- Admin + HR only: payroll management & request approvals (write actions) ---
    Route::middleware('role:admin,hr')->group(function () {
        Route::resource('employees', EmployeeController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
        Route::resource('holidays', HolidayController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
        Route::resource('payroll-runs', PayrollRunController::class)->only(['create', 'store', 'update', 'destroy']);

        Route::post('payroll-runs/{payrollRun}/upload', [AttendanceUploadController::class, 'store'])->name('payroll-runs.upload');
        Route::delete('attendance-uploads/{attendanceUpload}', [AttendanceUploadController::class, 'destroy'])->name('attendance-uploads.destroy');

        Route::post('payroll-runs/{payrollRun}/manual-attendances', [PayrollManualAttendanceController::class, 'store'])->name('payroll-runs.manual-attendances.store');
        Route::put('payroll-manual-attendances/{payrollManualAttendance}', [PayrollManualAttendanceController::class, 'update'])->name('payroll-manual-attendances.update');
        Route::delete('payroll-manual-attendances/{payrollManualAttendance}', [PayrollManualAttendanceController::class, 'destroy'])->name('payroll-manual-attendances.destroy');

        Route::post('payroll-runs/{payrollRun}/compute', [PayrollComputeController::class, 'store'])->name('payroll-runs.compute');

        Route::post('payroll-runs/{payrollRun}/lock', [PayrollLockController::class, 'store'])->name('payroll-runs.lock');
        Route::post('payroll-runs/{payrollRun}/unlock', [PayrollLockController::class, 'destroy'])->name('payroll-runs.unlock');

        Route::put('payroll-entries/{payrollEntry}', [PayrollEntryController::class, 'update'])->name('payroll-entries.update');

        // Request approvals
        Route::post('approvals/cash-advance/{cashAdvanceRequest}/approve', [RequestApprovalController::class, 'approveCashAdvance'])->name('approvals.cash-advance.approve');
        Route::post('approvals/cash-advance/{cashAdvanceRequest}/reject', [RequestApprovalController::class, 'rejectCashAdvance'])->name('approvals.cash-advance.reject');
        Route::post('approvals/leave/{leaveRequest}/approve', [RequestApprovalController::class, 'approveLeave'])->name('approvals.leave.approve');
        Route::post('approvals/leave/{leaveRequest}/reject', [RequestApprovalController::class, 'rejectLeave'])->name('approvals.leave.reject');
    });
});

require __DIR__.'/settings.php';

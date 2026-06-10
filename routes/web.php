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

// TEMPORARY IMPORT — remove after use
Route::get('/__import', function () {
    return '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:500px;margin:60px auto">
        <h2>Import Payroll JSON</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="_token" value="' . csrf_token() . '">
            <p><input type="file" name="jsonfile" accept=".json" required style="margin-bottom:12px"><br>
            <button type="submit" style="padding:10px 24px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer">
                Import Data
            </button></p>
        </form></body></html>';
});

Route::post('/__import', function (\Illuminate\Http\Request $request) {
    $file = $request->file('jsonfile');
    if (!$file) {
        return response('No file uploaded.', 400);
    }

    $data = json_decode(file_get_contents($file->getRealPath()), true);
    if (!$data) {
        return response('Invalid JSON.', 400);
    }

    DB::statement('SET session_replication_role = replica');

    $tables = ['payroll_entries','payroll_manual_attendances','attendance_uploads',
               'payroll_runs','app_settings','holidays','employees','users'];
    foreach ($tables as $t) { DB::table($t)->truncate(); }

    DB::statement('SET session_replication_role = DEFAULT');

    $counts = [];
    foreach (['users','employees','holidays','payroll_runs','payroll_entries',
              'payroll_manual_attendances','attendance_uploads'] as $table) {
        $rows = array_map(fn($r) => (array)$r, $data[$table] ?? []);
        if ($rows) {
            foreach (array_chunk($rows, 100) as $chunk) { DB::table($table)->insert($chunk); }
            $max = DB::table($table)->max('id');
            if ($max) { DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), {$max})"); }
        }
        $counts[$table] = count($rows);
    }
    foreach (($data['app_settings'] ?? []) as $row) { DB::table('app_settings')->insert((array)$row); }
    $counts['app_settings'] = count($data['app_settings'] ?? []);

    $summary = implode('<br>', array_map(fn($t,$c) => "✓ $t: $c rows", array_keys($counts), $counts));
    return "<!DOCTYPE html><html><body style='font-family:sans-serif;max-width:500px;margin:60px auto'>
        <h2>✅ Import Complete</h2><p>$summary</p>
        <p style='color:red'><strong>Remember to remove the /__import route now.</strong></p>
        </body></html>";
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

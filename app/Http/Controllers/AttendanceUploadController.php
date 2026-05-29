<?php
namespace App\Http\Controllers;

use App\Models\AttendanceUpload;
use App\Models\PayrollRun;
use App\Services\AttendanceParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttendanceUploadController extends Controller
{
    public function store(Request $request, PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls']]);

        $file = $request->file('file');
        $storagePath = $file->store('attendance');

        AttendanceUpload::create([
            'payroll_run_id' => $payrollRun->id,
            'filename'       => $file->getClientOriginalName(),
            'storage_path'   => $storagePath,
            'uploaded_at'    => now(),
        ]);

        return redirect()->route('payroll-runs.show', $payrollRun)
            ->with('success', 'Attendance file uploaded.');
    }

    public function destroy(AttendanceUpload $attendanceUpload)
    {
        $run = $attendanceUpload->payrollRun;
        abort_if($run->isLocked(), 403);

        if ($attendanceUpload->storage_path && Storage::exists($attendanceUpload->storage_path)) {
            Storage::delete($attendanceUpload->storage_path);
        }

        $attendanceUpload->delete();
        $run->entries()->delete();

        return redirect()->route('payroll-runs.show', $run)
            ->with('success', 'Attendance file removed and payroll entries cleared.');
    }
}

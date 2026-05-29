<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Services\AttendanceParser;
use App\Services\PayrollCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PayrollComputeController extends Controller
{
    public function store(Request $request, PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403);

        $upload = $payrollRun->uploads()->latest()->first();

        if (!$upload || !$upload->storage_path) {
            return back()->withErrors(['file' => 'Please upload an attendance file first.']);
        }

        $fullPath = Storage::path($upload->storage_path);

        if (!file_exists($fullPath)) {
            return back()->withErrors(['file' => 'Attendance file not found. Please re-upload.']);
        }

        $parsed = (new AttendanceParser())->parse($fullPath);

        $holidays = Holiday::whereBetween('date', [
            $payrollRun->period_start,
            $payrollRun->period_end,
        ])->get();

        $calculator = new PayrollCalculator();
        $unmatched  = [];
        $entries    = [];

        foreach ($parsed as $row) {
            $employee = Employee::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($row['name']))])
                ->whereRaw('LOWER(TRIM(department)) = ?', [strtolower(trim($row['department']))])
                ->first();

            if (!$employee) {
                $unmatched[] = "{$row['name']} ({$row['department']})";
                continue;
            }

            $periodStart = $payrollRun->period_start->format('Y-m-d');
            $periodEnd   = $payrollRun->period_end->format('Y-m-d');

            $periodAttendance = array_filter(
                $row['attendance'],
                fn($date) => $date >= $periodStart && $date <= $periodEnd,
                ARRAY_FILTER_USE_KEY
            );

            $computed = $calculator->calculate($employee, $periodAttendance, $holidays);

            $entries[] = array_merge([
                'payroll_run_id'   => $payrollRun->id,
                'employee_id'      => $employee->id,
                'cash_advance'     => 0,
                'other_deductions' => 0,
                'total_deductions' => 0,
                'net_pay'          => $computed['gross_pay'],
                'first_release'    => 0,
                'second_release'   => 0,
                'created_at'       => now(),
                'updated_at'       => now(),
            ], $computed);
        }

        DB::transaction(function () use ($payrollRun, $entries) {
            $payrollRun->entries()->delete();
            if (!empty($entries)) {
                PayrollEntry::insert($entries);
            }
        });

        if (!empty($unmatched)) {
            return redirect()->route('payroll-runs.show', $payrollRun)
                ->with('unmatched', $unmatched);
        }

        return redirect()->route('payroll-runs.show', $payrollRun)
            ->with('success', 'Payroll computed.');
    }
}

<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollEntry;
use App\Models\PayrollManualAttendance;
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

        $periodStart = $payrollRun->period_start->format('Y-m-d');
        $periodEnd   = $payrollRun->period_end->format('Y-m-d');

        $holidays = Holiday::whereBetween('date', [$periodStart, $periodEnd])->get();

        // Build attendance map: employee_id → [date → times]
        // Starts empty; populated from Excel then manual entries are merged in.
        $attendanceByEmployee = [];  // [employee_id => ['employee' => Employee, 'days' => [date => times]]]

        // --- Step 1: Parse Excel attendance file ---
        $upload = $payrollRun->uploads()->latest()->first();

        if ($upload && $upload->storage_path) {
            $fullPath = Storage::path($upload->storage_path);

            if (file_exists($fullPath)) {
                $parsed   = (new AttendanceParser())->parse($fullPath);
                $unmatched = [];

                foreach ($parsed as $row) {
                    $employee = Employee::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($row['name']))])
                        ->whereRaw('LOWER(TRIM(department)) = ?', [strtolower(trim($row['department']))])
                        ->first();

                    if (!$employee) {
                        $unmatched[] = "{$row['name']} ({$row['department']})";
                        continue;
                    }

                    $periodDays = array_filter(
                        $row['attendance'],
                        fn($date) => $date >= $periodStart && $date <= $periodEnd,
                        ARRAY_FILTER_USE_KEY
                    );

                    $attendanceByEmployee[$employee->id] = [
                        'employee' => $employee,
                        'days'     => $periodDays,
                    ];
                }
            }
        }

        // --- Step 2: Merge manual attendance entries ---
        // If a manual entry exists for a date that is already in the Excel, the Excel
        // entry for that date is REMOVED and replaced by the manual entry (or entries).
        // This lets admins correct a reassigned shift without double-counting the day.
        // For a true double-shift day, add two separate manual entries for that date.
        $manualEntries = PayrollManualAttendance::with('employee')
            ->where('payroll_run_id', $payrollRun->id)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->get();

        // Only drop the Excel entry for a date when the admin explicitly marked is_override=true.
        // Additive entries (is_override=false, the default) stack on top — used for second shifts.
        foreach ($manualEntries->where('is_override', true) as $entry) {
            $empId = $entry->employee_id;
            $date  = $entry->date->format('Y-m-d');
            unset($attendanceByEmployee[$empId]['days'][$date]);
        }

        // Add manual entries (each uses its own shift_start/shift_end)
        foreach ($manualEntries as $entry) {
            $id = $entry->employee_id;

            if (!isset($attendanceByEmployee[$id])) {
                $attendanceByEmployee[$id] = [
                    'employee' => $entry->employee,
                    'days'     => [],
                ];
            }

            // Unique key per entry so two manual entries on the same date (double shift) both count
            $dayKey = $entry->date->format('Y-m-d') . '_m' . $entry->id;

            $attendanceByEmployee[$id]['days'][$dayKey] = [
                'sw'          => $entry->sw,
                'ew'          => $entry->ew,
                'shift_start' => $entry->shift_start,
                'shift_end'   => $entry->shift_end,
                '_date'       => $entry->date->format('Y-m-d'),
            ];
        }

        // --- Step 3: Compute payroll for each employee ---
        $calculator = new PayrollCalculator();
        $entries    = [];

        foreach ($attendanceByEmployee as ['employee' => $employee, 'days' => $days]) {
            $computed = $calculator->calculate($employee, $days, $holidays);

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

        if (!empty($unmatched ?? [])) {
            return redirect()->route('payroll-runs.show', $payrollRun)
                ->with('unmatched', $unmatched);
        }

        return redirect()->route('payroll-runs.show', $payrollRun)
            ->with('success', 'Payroll computed.');
    }
}

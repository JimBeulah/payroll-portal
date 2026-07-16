<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\PayrollManualAttendance;
use App\Models\PayrollRun;
use App\Services\AttendanceCalendarService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeAttendanceController extends Controller
{
    /**
     * The employee's own read-only attendance calendar for a chosen payroll run.
     */
    public function index(Request $request, AttendanceCalendarService $attendanceCalendar)
    {
        $employee = $request->user()->employee;

        abort_if($employee === null, 403, 'No employee record is linked to your account.');

        $payrollRuns = PayrollRun::whereHas('entries', fn ($q) => $q->where('employee_id', $employee->id))
            ->orderByDesc('period_start')
            ->get(['id', 'period_start', 'period_end']);

        abort_if($payrollRuns->isEmpty(), 404, 'No payroll history found for your account yet.');

        $selectedRunId = (int) $request->query('run', $payrollRuns->first()->id);
        $payrollRun = $payrollRuns->firstWhere('id', $selectedRunId);

        abort_if($payrollRun === null, 404, 'Payroll run not found.');

        $calendar = $attendanceCalendar->buildForRun($payrollRun);

        $manualAttendances = PayrollManualAttendance::where('payroll_run_id', $payrollRun->id)
            ->where('employee_id', $employee->id)
            ->get()
            ->makeHidden(['note']);

        $holidays = Holiday::whereBetween('date', [
            $payrollRun->period_start->format('Y-m-d'),
            $payrollRun->period_end->format('Y-m-d'),
        ])->get(['date', 'name', 'type']);

        return Inertia::render('attendance/index', [
            'employee' => $employee->only(['id', 'name', 'employee_number', 'department']),
            'payrollRuns' => $payrollRuns,
            'selectedRun' => $payrollRun,
            'manualAttendances' => $manualAttendances,
            'attendanceData' => $calendar['attendanceData'][$employee->id] ?? [],
            'leaveData' => $calendar['leaveData'][$employee->id] ?? [],
            'holidays' => $holidays,
        ]);
    }
}

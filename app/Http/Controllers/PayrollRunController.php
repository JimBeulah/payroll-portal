<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollRunRequest;
use App\Http\Requests\UpdatePayrollRunRequest;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Services\AttendanceCalendarService;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PayrollRunController extends Controller
{
    public function index()
    {
        return Inertia::render('payroll/index', [
            'runs' => PayrollRun::latest()->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('payroll/create');
    }

    public function store(StorePayrollRunRequest $request)
    {
        $run = new PayrollRun($request->validated());
        $run->created_by = auth()->id();
        $run->save();

        AuditLogger::record(
            'payroll_run.created',
            $run,
            'Payroll Run: '.$run->period_start->format('M Y'),
            ['period_start' => $run->period_start->format('Y-m-d'), 'period_end' => $run->period_end->format('Y-m-d')]
        );

        return redirect()->route('payroll-runs.show', $run);
    }

    public function show(PayrollRun $payrollRun, AttendanceCalendarService $attendanceCalendar)
    {
        $payrollRun->load([
            'entries.employee' => fn ($q) => $q->withTrashed(),
            'uploads',
            'manualAttendances.employee' => fn ($q) => $q->withTrashed(),
        ]);

        $calendar = $attendanceCalendar->buildForRun($payrollRun);

        return Inertia::render('payroll/show', [
            'run' => $payrollRun,
            'entries' => $payrollRun->entries,
            'uploads' => $payrollRun->uploads,
            'manualAttendances' => $payrollRun->manualAttendances,
            'employees' => Employee::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'department', 'shift_start', 'shift_end']),
            'attendanceData' => $calendar['attendanceData'],
            'leaveData' => $calendar['leaveData'],
        ]);
    }

    public function update(UpdatePayrollRunRequest $request, PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403, 'Locked payroll runs cannot be edited.');

        $payrollRun->update($request->validated());

        AuditLogger::record(
            'payroll_run.updated',
            $payrollRun,
            'Payroll Run: '.$payrollRun->period_start->format('M Y'),
            $request->validated()
        );

        return back()->with('success', 'Payroll run updated.');
    }

    public function destroy(PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403, 'Locked payroll runs cannot be deleted.');

        $label = 'Payroll Run: '.$payrollRun->period_start->format('M Y');

        DB::transaction(function () use ($payrollRun) {
            $payrollRun->entries()->delete();
            $payrollRun->uploads()->delete();
            $payrollRun->delete();
        });

        AuditLogger::record('payroll_run.deleted', $payrollRun, $label);

        return redirect()->route('payroll-runs.index')
            ->with('success', 'Payroll run deleted.');
    }
}

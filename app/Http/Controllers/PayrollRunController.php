<?php
namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollRunRequest;
use App\Models\Employee;
use App\Models\PayrollRun;
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

        return redirect()->route('payroll-runs.show', $run);
    }

    public function show(PayrollRun $payrollRun)
    {
        $payrollRun->load('entries.employee', 'uploads', 'manualAttendances.employee');

        return Inertia::render('payroll/show', [
            'run'              => $payrollRun,
            'entries'          => $payrollRun->entries,
            'uploads'          => $payrollRun->uploads,
            'manualAttendances' => $payrollRun->manualAttendances,
            'employees'        => Employee::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'department', 'shift_start', 'shift_end']),
        ]);
    }

    public function destroy(PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403, 'Locked payroll runs cannot be deleted.');

        DB::transaction(function () use ($payrollRun) {
            $payrollRun->entries()->delete();
            $payrollRun->uploads()->delete();
            $payrollRun->delete();
        });

        return redirect()->route('payroll-runs.index')
            ->with('success', 'Payroll run deleted.');
    }
}

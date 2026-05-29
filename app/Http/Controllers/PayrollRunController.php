<?php
namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollEntryRequest;
use App\Http\Requests\StorePayrollRunRequest;
use App\Models\Employee;
use App\Models\PayrollEntry;
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
        $payrollRun->load('entries.employee', 'uploads');

        $existingEmployeeIds = $payrollRun->entries->pluck('employee_id');

        $availableEmployees = Employee::where('is_active', true)
            ->whereNotIn('id', $existingEmployeeIds)
            ->orderBy('name')
            ->get(['id', 'name', 'department', 'daily_rate']);

        return Inertia::render('payroll/show', [
            'run'                => $payrollRun,
            'entries'            => $payrollRun->entries,
            'uploads'            => $payrollRun->uploads,
            'availableEmployees' => $availableEmployees,
        ]);
    }

    public function storeEntry(StorePayrollEntryRequest $request, PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403);

        $data = $request->validated();

        if (PayrollEntry::where('payroll_run_id', $payrollRun->id)->where('employee_id', $data['employee_id'])->exists()) {
            return back()->withErrors(['employee_id' => 'Employee already has an entry in this payroll run.']);
        }

        $employee = Employee::find($data['employee_id']);
        $basicPay = round($employee->daily_rate * $data['days_present'], 2);

        PayrollEntry::create([
            'payroll_run_id'      => $payrollRun->id,
            'employee_id'         => $employee->id,
            'days_present'        => $data['days_present'],
            'total_basic_pay'     => $basicPay,
            'overtime_minutes'    => 0,
            'overtime_pay'        => 0,
            'late_minutes'        => 0,
            'late_deduction'      => 0,
            'undertime_minutes'   => 0,
            'undertime_deduction' => 0,
            'holiday_pay'         => 0,
            'gross_pay'           => $basicPay,
            'cash_advance'        => 0,
            'other_deductions'    => 0,
            'total_deductions'    => 0,
            'net_pay'             => $basicPay,
            'first_release'       => 0,
            'second_release'      => 0,
        ]);

        return redirect()->route('payroll-runs.show', $payrollRun)
            ->with('success', "{$employee->name} added to payroll.");
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

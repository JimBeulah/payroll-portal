<?php
namespace App\Http\Controllers;

use App\Models\PayrollRun;

class PayrollLockController extends Controller
{
    public function store(PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403);

        $payrollRun->update(['status' => 'locked']);

        return redirect()->route('payroll-runs.show', $payrollRun)
            ->with('success', 'Payroll run locked.');
    }

    public function destroy(PayrollRun $payrollRun)
    {
        abort_unless($payrollRun->isLocked(), 403);

        $payrollRun->update(['status' => 'draft']);

        return redirect()->route('payroll-runs.show', $payrollRun)
            ->with('success', 'Payroll run unlocked.');
    }
}

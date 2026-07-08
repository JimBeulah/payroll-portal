<?php

namespace App\Http\Controllers;

use App\Models\CashAdvanceRequest;
use App\Models\PayrollRun;

class PayrollLockController extends Controller
{
    public function store(PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403);

        $payrollRun->update(['status' => 'locked']);

        // Stamp the approved advances that fell into this run so a later run cannot
        // double-count them. Only claim advances not already tied to another run.
        CashAdvanceRequest::approved()
            ->whereBetween('needed_date', [
                $payrollRun->period_start->format('Y-m-d'),
                $payrollRun->period_end->format('Y-m-d'),
            ])
            ->whereNull('applied_payroll_run_id')
            ->update(['applied_payroll_run_id' => $payrollRun->id]);

        return redirect()->route('payroll-runs.show', $payrollRun)
            ->with('success', 'Payroll run locked.');
    }

    public function destroy(PayrollRun $payrollRun)
    {
        abort_unless($payrollRun->isLocked(), 403);

        $payrollRun->update(['status' => 'draft']);

        // Release advances claimed by this run so they can be recomputed/re-locked.
        CashAdvanceRequest::where('applied_payroll_run_id', $payrollRun->id)
            ->update(['applied_payroll_run_id' => null]);

        return redirect()->route('payroll-runs.show', $payrollRun)
            ->with('success', 'Payroll run unlocked.');
    }
}

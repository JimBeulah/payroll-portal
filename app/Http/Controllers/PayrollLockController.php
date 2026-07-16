<?php

namespace App\Http\Controllers;

use App\Models\CashAdvanceRequest;
use App\Models\PayrollRun;
use App\Services\AuditLogger;

class PayrollLockController extends Controller
{
    public function store(PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403);

        $payrollRun->update(['status' => 'locked']);

        // Stamp the advances that fell into this run so a later run cannot double-count
        // them. Only claim advances not already tied to another run.
        CashAdvanceRequest::dueForPayrollRun($payrollRun)
            ->whereNull('applied_payroll_run_id')
            ->update(['applied_payroll_run_id' => $payrollRun->id]);

        AuditLogger::record(
            'payroll_run.locked',
            $payrollRun,
            'Payroll Run: '.$payrollRun->period_start->format('M Y')
        );

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

        AuditLogger::record(
            'payroll_run.unlocked',
            $payrollRun,
            'Payroll Run: '.$payrollRun->period_start->format('M Y')
        );

        return redirect()->route('payroll-runs.show', $payrollRun)
            ->with('success', 'Payroll run unlocked.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePayrollEntryRequest;
use App\Models\PayrollEntry;
use App\Services\AuditLogger;

class PayrollEntryController extends Controller
{
    public function update(UpdatePayrollEntryRequest $request, PayrollEntry $payrollEntry)
    {
        abort_if($payrollEntry->payrollRun->isLocked(), 403);

        $data = $request->validated();
        $totalDeductions = $data['cash_advance'] + $data['other_deductions'];
        $netPay = round($payrollEntry->gross_pay - $totalDeductions, 2);
        $oldNetPay = $payrollEntry->net_pay;

        $payrollEntry->update([
            ...$data,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
        ]);

        AuditLogger::record(
            'payroll_entry.updated',
            $payrollEntry,
            $payrollEntry->employee->name.' — '.$payrollEntry->payrollRun->period_start->format('M Y'),
            ['before' => ['net_pay' => $oldNetPay], 'after' => $data + ['net_pay' => $netPay]]
        );

        return back()->with('success', 'Entry updated.');
    }
}

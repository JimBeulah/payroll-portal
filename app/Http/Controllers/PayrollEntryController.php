<?php
namespace App\Http\Controllers;

use App\Http\Requests\UpdatePayrollEntryRequest;
use App\Models\PayrollEntry;

class PayrollEntryController extends Controller
{
    public function update(UpdatePayrollEntryRequest $request, PayrollEntry $payrollEntry)
    {
        abort_if($payrollEntry->payrollRun->isLocked(), 403);

        $data            = $request->validated();
        $totalDeductions = $data['cash_advance'] + $data['other_deductions'];
        $netPay          = round($payrollEntry->gross_pay - $totalDeductions, 2);

        $payrollEntry->update([
            ...$data,
            'total_deductions' => $totalDeductions,
            'net_pay'          => $netPay,
        ]);

        return back()->with('success', 'Entry updated.');
    }
}

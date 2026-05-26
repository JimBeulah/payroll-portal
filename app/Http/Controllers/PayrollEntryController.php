<?php
namespace App\Http\Controllers;

use App\Models\PayrollEntry;
use Illuminate\Http\Request;

class PayrollEntryController extends Controller
{
    public function update(Request $request, PayrollEntry $payrollEntry)
    {
        abort_if($payrollEntry->payrollRun->isLocked(), 403);

        $data = $request->validate([
            'cash_advance'     => ['required', 'numeric', 'min:0'],
            'other_deductions' => ['required', 'numeric', 'min:0'],
            'first_release'    => ['required', 'numeric', 'min:0'],
            'second_release'   => ['required', 'numeric', 'min:0'],
        ]);

        $totalDeductions = $data['cash_advance'] + $data['other_deductions'];
        $netPay = round($payrollEntry->gross_pay - $totalDeductions, 2);

        $payrollEntry->update([
            ...$data,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
        ]);

        return back()->with('success', 'Entry updated.');
    }
}

<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreManualAttendanceRequest;
use App\Models\PayrollManualAttendance;
use App\Models\PayrollRun;

class PayrollManualAttendanceController extends Controller
{
    public function store(StoreManualAttendanceRequest $request, PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403);

        $payrollRun->manualAttendances()->create($request->validated());

        return back()->with('success', 'Manual attendance entry added.');
    }

    public function update(StoreManualAttendanceRequest $request, PayrollManualAttendance $payrollManualAttendance)
    {
        abort_if($payrollManualAttendance->payrollRun->isLocked(), 403);

        $payrollManualAttendance->update($request->validated());

        return back()->with('success', 'Manual attendance entry updated.');
    }

    public function destroy(PayrollManualAttendance $payrollManualAttendance)
    {
        abort_if($payrollManualAttendance->payrollRun->isLocked(), 403);

        $payrollManualAttendance->delete();

        return back()->with('success', 'Manual attendance entry removed.');
    }
}

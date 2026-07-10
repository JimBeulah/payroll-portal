<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCashAdvanceRequest;
use App\Models\CashAdvanceRequest;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\NewRequestSubmitted;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class CashAdvanceRequestController extends Controller
{
    /**
     * The employee's own request portal — lists both cash advance and leave requests.
     */
    public function index()
    {
        $employee = request()->user()->employee;

        abort_if($employee === null, 403, 'No employee record is linked to your account.');

        return Inertia::render('requests/index', [
            'employee' => $employee->only(['id', 'name', 'employee_number', 'department']),
            'cashAdvances' => CashAdvanceRequest::where('employee_id', $employee->id)
                ->latest()
                ->get(),
            'leaveRequests' => LeaveRequest::where('employee_id', $employee->id)
                ->latest()
                ->get(),
        ]);
    }

    public function store(StoreCashAdvanceRequest $request)
    {
        $employee = $request->user()->employee;

        $cashAdvanceRequest = CashAdvanceRequest::create([
            'employee_id' => $employee->id,
            'amount' => $request->validated('amount'),
            'needed_date' => $request->validated('needed_date'),
            'reason' => $request->validated('reason'),
            'status' => CashAdvanceRequest::STATUS_PENDING,
        ]);

        Notification::send(User::canApproveRequests(), new NewRequestSubmitted($cashAdvanceRequest));

        return redirect()->route('my-requests.index')->with('success', 'Cash advance request submitted.');
    }
}

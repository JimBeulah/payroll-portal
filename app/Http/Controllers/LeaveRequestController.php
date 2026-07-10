<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveRequest;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\NewRequestSubmitted;
use Illuminate\Support\Facades\Notification;

class LeaveRequestController extends Controller
{
    public function store(StoreLeaveRequest $request)
    {
        $employee = $request->user()->employee;

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'start_date' => $request->validated('start_date'),
            'end_date' => $request->validated('end_date'),
            'reason' => $request->validated('reason'),
            'status' => LeaveRequest::STATUS_PENDING,
        ]);

        try {
            Notification::send(User::canApproveRequests(), new NewRequestSubmitted($leaveRequest));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('my-requests.index')->with('success', 'Leave/absent request submitted.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\CashAdvanceRequest;
use App\Models\LeaveRequest;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RequestApprovalController extends Controller
{
    public function index()
    {
        return Inertia::render('approvals/index', [
            'cashAdvances' => CashAdvanceRequest::with('employee:id,name,department', 'reviewer:id,name')
                ->latest()
                ->get(),
            'leaveRequests' => LeaveRequest::with('employee:id,name,department', 'reviewer:id,name')
                ->latest()
                ->get(),
        ]);
    }

    public function approveCashAdvance(Request $request, CashAdvanceRequest $cashAdvanceRequest)
    {
        $this->review($request, $cashAdvanceRequest, CashAdvanceRequest::STATUS_APPROVED);

        return back()->with('success', 'Cash advance approved.');
    }

    public function rejectCashAdvance(Request $request, CashAdvanceRequest $cashAdvanceRequest)
    {
        $this->review($request, $cashAdvanceRequest, CashAdvanceRequest::STATUS_REJECTED);

        return back()->with('success', 'Cash advance rejected.');
    }

    public function approveLeave(Request $request, LeaveRequest $leaveRequest)
    {
        $this->review($request, $leaveRequest, LeaveRequest::STATUS_APPROVED);

        return back()->with('success', 'Leave request approved.');
    }

    public function rejectLeave(Request $request, LeaveRequest $leaveRequest)
    {
        $this->review($request, $leaveRequest, LeaveRequest::STATUS_REJECTED);

        return back()->with('success', 'Leave request rejected.');
    }

    /**
     * Apply a reviewer decision to a request model.
     */
    private function review(Request $request, CashAdvanceRequest|LeaveRequest $model, string $status): void
    {
        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $model->update([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'] ?? null,
        ]);

        $type = $model instanceof CashAdvanceRequest ? 'cash_advance' : 'leave';
        $decision = $status === CashAdvanceRequest::STATUS_APPROVED ? 'approved' : 'rejected';

        AuditLogger::record(
            "{$type}.{$decision}",
            $model,
            $model->employee->name,
            ['review_note' => $validated['review_note'] ?? null]
        );
    }
}

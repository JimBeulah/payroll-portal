<?php

namespace Tests\Feature;

use App\Models\CashAdvanceRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\RequestReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RequestReviewedTest extends TestCase
{
    use RefreshDatabase;

    private function employeeUser(): User
    {
        $user = User::factory()->employee()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    public function test_approving_cash_advance_notifies_the_employees_linked_user(): void
    {
        Notification::fake();

        $hr = User::factory()->hr()->create();
        $employeeUser = $this->employeeUser();
        $request = CashAdvanceRequest::create([
            'employee_id' => $employeeUser->employee->id,
            'amount' => 500,
            'needed_date' => '2026-05-10',
            'reason' => 'Emergency expense',
            'status' => 'pending',
        ]);

        $this->actingAs($hr)
            ->post("/approvals/cash-advance/{$request->id}/approve", ['review_note' => 'OK'])
            ->assertRedirect();

        Notification::assertSentTo($employeeUser, RequestReviewed::class);
    }

    public function test_rejecting_leave_notifies_the_employees_linked_user(): void
    {
        Notification::fake();

        $hr = User::factory()->hr()->create();
        $employeeUser = $this->employeeUser();
        $request = LeaveRequest::create([
            'employee_id' => $employeeUser->employee->id,
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-13',
            'reason' => 'Family matter',
            'status' => 'pending',
        ]);

        $this->actingAs($hr)
            ->post("/approvals/leave/{$request->id}/reject", ['review_note' => 'Denied'])
            ->assertRedirect();

        Notification::assertSentTo($employeeUser, RequestReviewed::class);
    }

    public function test_reviewing_request_does_not_notify_when_employee_has_no_linked_user(): void
    {
        Notification::fake();

        $hr = User::factory()->hr()->create();
        $employee = Employee::factory()->create();
        $request = CashAdvanceRequest::create([
            'employee_id' => $employee->id,
            'amount' => 500,
            'needed_date' => '2026-05-10',
            'reason' => 'Emergency expense',
            'status' => 'pending',
        ]);

        $this->actingAs($hr)
            ->post("/approvals/cash-advance/{$request->id}/approve", ['review_note' => 'OK'])
            ->assertRedirect();

        Notification::assertNothingSent();
    }
}

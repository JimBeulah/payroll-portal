<?php

namespace Tests\Feature;

use App\Models\CashAdvanceRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function employeeUser(): User
    {
        $user = User::factory()->employee()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    public function test_employee_cannot_access_manager_routes(): void
    {
        $user = $this->employeeUser();

        $this->actingAs($user)->get('/employees')->assertForbidden();
        $this->actingAs($user)->get('/payroll-runs')->assertForbidden();
        $this->actingAs($user)->get('/approvals')->assertForbidden();
    }

    public function test_employee_lands_on_their_request_portal(): void
    {
        $user = $this->employeeUser();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/my-requests');
        $this->actingAs($user)->get('/my-requests')->assertOk();
    }

    public function test_employee_can_submit_cash_advance_and_leave(): void
    {
        $user = $this->employeeUser();
        $employeeId = $user->employee->id;

        $this->actingAs($user)->post('/my-requests/cash-advance', [
            'amount' => 1500,
            'needed_date' => '2026-05-10',
            'reason' => 'Medical',
        ])->assertRedirect('/my-requests');

        $this->actingAs($user)->post('/my-requests/leave', [
            'type' => 'leave',
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-13',
            'reason' => 'Family matter',
        ])->assertRedirect('/my-requests');

        $this->assertDatabaseHas('cash_advance_requests', [
            'employee_id' => $employeeId,
            'amount' => 1500,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $employeeId,
            'type' => 'leave',
            'status' => 'pending',
        ]);
    }

    public function test_hr_can_approve_requests(): void
    {
        $hr = User::factory()->hr()->create();
        $employee = Employee::factory()->create();
        $request = CashAdvanceRequest::create([
            'employee_id' => $employee->id,
            'amount' => 500,
            'needed_date' => '2026-05-10',
            'status' => 'pending',
        ]);

        $this->actingAs($hr)->get('/approvals')->assertOk();

        $this->actingAs($hr)
            ->post("/approvals/cash-advance/{$request->id}/approve", ['review_note' => 'OK'])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_advance_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'reviewed_by' => $hr->id,
        ]);
    }

    public function test_manager_creates_employee_with_linked_login_account(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)->post('/employees', [
            'name' => 'Newbie Worker',
            'department' => 'OPS',
            'daily_rate' => 500,
            'shift_start' => '08:00',
            'shift_end' => '17:00',
            'username' => 'newbieworker',
            'password' => '123700',
        ])->assertRedirect('/employees');

        $this->assertDatabaseHas('users', ['username' => 'newbieworker', 'role' => 'employee']);
        $created = User::where('username', 'newbieworker')->first();
        $this->assertDatabaseHas('employees', ['name' => 'Newbie Worker', 'user_id' => $created->id]);

        // The freshly created employee account can log into its own portal.
        $this->actingAs($created)->get('/my-requests')->assertOk();
    }

    public function test_leave_request_rejects_invalid_type(): void
    {
        $user = $this->employeeUser();

        $this->actingAs($user)->post('/my-requests/leave', [
            'type' => 'vacation',
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-13',
        ])->assertSessionHasErrors('type');
    }
}

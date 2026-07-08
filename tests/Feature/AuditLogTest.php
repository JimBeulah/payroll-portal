<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logs_page_is_admin_only(): void
    {
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->employee()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($hr)->get('/settings/audit-logs')->assertForbidden();
        $this->actingAs($employee)->get('/settings/audit-logs')->assertForbidden();
        $this->actingAs($admin)->get('/settings/audit-logs')->assertOk();
    }

    public function test_locking_payroll_run_creates_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        $run = PayrollRun::factory()->create();

        $this->actingAs($admin)
            ->post("/payroll-runs/{$run->id}/lock")
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payroll_run.locked',
            'user_id' => $admin->id,
            'auditable_id' => $run->id,
        ]);
    }

    public function test_approving_leave_request_creates_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();
        $request = LeaveRequest::create([
            'employee_id' => $employee->id,
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-13',
            'reason' => 'Family matter',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post("/approvals/leave/{$request->id}/approve", ['review_note' => 'OK'])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'leave.approved',
            'user_id' => $admin->id,
            'auditable_id' => $request->id,
        ]);
    }

    public function test_deleting_user_account_creates_audit_log_with_snapshot(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->hr()->create(['name' => 'Temp HR']);

        $this->actingAs($admin)
            ->delete("/settings/users/{$target->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user_account.deleted',
            'subject_label' => $target->name.' ('.$target->username.')',
        ]);
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }
}

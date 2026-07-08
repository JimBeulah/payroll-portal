<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollEntry;
use App\Models\PayrollManualAttendance;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeavePayrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_leave_day_is_excluded_from_pay_on_compute(): void
    {
        $admin = User::factory()->admin()->create();

        $employee = Employee::factory()->create([
            'daily_rate' => 500.00,
            'shift_start' => '08:00:00',
            'shift_end' => '17:00:00',
        ]);

        $run = PayrollRun::factory()->create([
            'created_by' => $admin->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-15',
            'payable_date' => '2026-05-20',
        ]);

        // Worked day (should be paid) and a day that falls under approved leave (should not).
        PayrollManualAttendance::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-02',
            'sw' => '08:00',
            'ew' => '17:00',
            'shift_start' => '08:00',
            'shift_end' => '17:00',
            'is_override' => true,
        ]);

        PayrollManualAttendance::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-05',
            'sw' => '08:00',
            'ew' => '17:00',
            'shift_start' => '08:00',
            'shift_end' => '17:00',
            'is_override' => true,
        ]);

        LeaveRequest::create([
            'employee_id' => $employee->id,
            'start_date' => '2026-05-05',
            'end_date' => '2026-05-05',
            'reason' => 'Family emergency',
            'status' => 'approved',
        ]);

        $this->actingAs($admin)->post("/payroll-runs/{$run->id}/compute")->assertRedirect();

        $entry = PayrollEntry::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $this->assertEquals(1, $entry->days_present);
        $this->assertEquals(500.0, (float) $entry->total_basic_pay);
    }

    public function test_pending_leave_does_not_affect_pay(): void
    {
        $admin = User::factory()->admin()->create();

        $employee = Employee::factory()->create([
            'daily_rate' => 500.00,
            'shift_start' => '08:00:00',
            'shift_end' => '17:00:00',
        ]);

        $run = PayrollRun::factory()->create([
            'created_by' => $admin->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-15',
            'payable_date' => '2026-05-20',
        ]);

        PayrollManualAttendance::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-05',
            'sw' => '08:00',
            'ew' => '17:00',
            'shift_start' => '08:00',
            'shift_end' => '17:00',
            'is_override' => true,
        ]);

        LeaveRequest::create([
            'employee_id' => $employee->id,
            'start_date' => '2026-05-05',
            'end_date' => '2026-05-05',
            'reason' => 'Vacation',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post("/payroll-runs/{$run->id}/compute")->assertRedirect();

        $entry = PayrollEntry::where('payroll_run_id', $run->id)->firstOrFail();

        $this->assertEquals(1, $entry->days_present);
        $this->assertEquals(500.0, (float) $entry->total_basic_pay);
    }
}

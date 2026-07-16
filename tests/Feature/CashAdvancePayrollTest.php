<?php

namespace Tests\Feature;

use App\Models\CashAdvanceRequest;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollManualAttendance;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashAdvancePayrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_cash_advance_in_period_reduces_net_pay_on_compute(): void
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

        // One worked day so the employee gets a payroll entry.
        PayrollManualAttendance::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-02',
            'sw' => '08:00',
            'ew' => '17:00',
            'shift_start' => '08:00',
            'shift_end' => '17:00',
            'is_override' => false,
        ]);

        // Approved advance whose needed_date falls within the run period.
        CashAdvanceRequest::create([
            'employee_id' => $employee->id,
            'amount' => 200,
            'reason' => 'test',
            'needed_date' => '2026-05-05',
            'status' => 'approved',
        ]);

        $this->actingAs($admin)->post("/payroll-runs/{$run->id}/compute")->assertRedirect();

        $entry = PayrollEntry::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $this->assertEquals(200.0, (float) $entry->cash_advance);
        $this->assertEquals(200.0, (float) $entry->total_deductions);
        $this->assertEquals((float) $entry->gross_pay - 200.0, (float) $entry->net_pay);
    }

    public function test_pending_advance_is_not_applied(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create(['daily_rate' => 500.00, 'shift_start' => '08:00:00', 'shift_end' => '17:00:00']);
        $run = PayrollRun::factory()->create(['created_by' => $admin->id, 'period_start' => '2026-05-01', 'period_end' => '2026-05-15', 'payable_date' => '2026-05-20']);

        PayrollManualAttendance::create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'date' => '2026-05-02',
            'sw' => '08:00', 'ew' => '17:00', 'shift_start' => '08:00', 'shift_end' => '17:00', 'is_override' => false,
        ]);

        CashAdvanceRequest::create([
            'employee_id' => $employee->id, 'amount' => 999, 'reason' => 'test', 'needed_date' => '2026-05-05', 'status' => 'pending',
        ]);

        $this->actingAs($admin)->post("/payroll-runs/{$run->id}/compute")->assertRedirect();

        $entry = PayrollEntry::where('payroll_run_id', $run->id)->firstOrFail();
        $this->assertEquals(0.0, (float) $entry->cash_advance);
    }

    public function test_locking_stamps_advance_and_unlock_clears_it(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();
        $run = PayrollRun::factory()->create(['created_by' => $admin->id, 'period_start' => '2026-05-01', 'period_end' => '2026-05-15', 'payable_date' => '2026-05-20']);

        $advance = CashAdvanceRequest::create([
            'employee_id' => $employee->id, 'amount' => 300, 'reason' => 'test', 'needed_date' => '2026-05-05', 'status' => 'approved',
        ]);

        $this->actingAs($admin)->post("/payroll-runs/{$run->id}/lock")->assertRedirect();
        $this->assertEquals($run->id, $advance->fresh()->applied_payroll_run_id);

        $this->actingAs($admin)->post("/payroll-runs/{$run->id}/unlock")->assertRedirect();
        $this->assertNull($advance->fresh()->applied_payroll_run_id);
    }

    public function test_advance_needed_after_current_period_is_deducted_from_next_open_payday(): void
    {
        $admin = User::factory()->admin()->create();

        $employee = Employee::factory()->create([
            'daily_rate' => 500.00,
            'shift_start' => '08:00:00',
            'shift_end' => '17:00:00',
        ]);

        // Already-paid run: period June 16-30, payable July 10 — locked.
        $paidRun = PayrollRun::factory()->create([
            'created_by' => $admin->id,
            'period_start' => '2026-06-16',
            'period_end' => '2026-06-30',
            'payable_date' => '2026-07-10',
            'status' => 'locked',
        ]);

        // Upcoming run: period July 1-15, payable July 25 — still open.
        $upcomingRun = PayrollRun::factory()->create([
            'created_by' => $admin->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-15',
            'payable_date' => '2026-07-25',
        ]);

        // Future run: period July 16-31, payable Aug 10 — still open.
        $futureRun = PayrollRun::factory()->create([
            'created_by' => $admin->id,
            'period_start' => '2026-07-16',
            'period_end' => '2026-07-31',
            'payable_date' => '2026-08-10',
        ]);

        PayrollManualAttendance::create([
            'payroll_run_id' => $upcomingRun->id,
            'employee_id' => $employee->id,
            'date' => '2026-07-02',
            'sw' => '08:00',
            'ew' => '17:00',
            'shift_start' => '08:00',
            'shift_end' => '17:00',
            'is_override' => false,
        ]);

        // Needed July 16 — falls inside the "future run" period, but the upcoming
        // payday (July 25) is the soonest open run that can still cover it in time.
        CashAdvanceRequest::create([
            'employee_id' => $employee->id,
            'amount' => 200,
            'reason' => 'test',
            'needed_date' => '2026-07-16',
            'status' => 'approved',
        ]);

        $this->actingAs($admin)->post("/payroll-runs/{$upcomingRun->id}/compute")->assertRedirect();

        $entry = PayrollEntry::where('payroll_run_id', $upcomingRun->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $this->assertEquals(200.0, (float) $entry->cash_advance);

        // The later "future run" must not also claim it once the upcoming run has it.
        PayrollManualAttendance::create([
            'payroll_run_id' => $futureRun->id,
            'employee_id' => $employee->id,
            'date' => '2026-07-17',
            'sw' => '08:00',
            'ew' => '17:00',
            'shift_start' => '08:00',
            'shift_end' => '17:00',
            'is_override' => false,
        ]);

        $this->actingAs($admin)->post("/payroll-runs/{$futureRun->id}/compute")->assertRedirect();

        $futureEntry = PayrollEntry::where('payroll_run_id', $futureRun->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $this->assertEquals(0.0, (float) $futureEntry->cash_advance);
    }
}

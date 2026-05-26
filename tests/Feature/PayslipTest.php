<?php
namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipTest extends TestCase
{
    use RefreshDatabase;

    private function makeLockedEntry(): PayrollEntry
    {
        $user = User::factory()->create();
        $run = PayrollRun::factory()->create([
            'created_by' => $user->id,
            'status' => 'locked',
        ]);
        $employee = Employee::factory()->create();

        return PayrollEntry::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'days_present' => 11,
            'total_basic_pay' => 5280,
            'overtime_minutes' => 120, 'overtime_pay' => 240,
            'late_minutes' => 0, 'late_deduction' => 0,
            'undertime_minutes' => 0, 'undertime_deduction' => 0,
            'holiday_pay' => 0, 'gross_pay' => 5520,
            'cash_advance' => 0, 'other_deductions' => 0,
            'total_deductions' => 0, 'net_pay' => 5520,
            'first_release' => 2760, 'second_release' => 2760,
        ]);
    }

    public function test_can_download_single_payslip_pdf(): void
    {
        $entry = $this->makeLockedEntry();

        $response = $this->actingAs(User::first())
            ->get("/payroll-entries/{$entry->id}/payslip");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_cannot_download_payslip_from_draft_run(): void
    {
        $user = User::factory()->create();
        $run = PayrollRun::factory()->create(['created_by' => $user->id, 'status' => 'draft']);
        $employee = Employee::factory()->create();
        $entry = PayrollEntry::create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id,
            'days_present' => 0, 'total_basic_pay' => 0, 'overtime_minutes' => 0,
            'overtime_pay' => 0, 'late_minutes' => 0, 'late_deduction' => 0,
            'undertime_minutes' => 0, 'undertime_deduction' => 0,
            'holiday_pay' => 0, 'gross_pay' => 0, 'cash_advance' => 0,
            'other_deductions' => 0, 'total_deductions' => 0, 'net_pay' => 0,
            'first_release' => 0, 'second_release' => 0,
        ]);

        $this->actingAs($user)->get("/payroll-entries/{$entry->id}/payslip")
            ->assertForbidden();
    }
}

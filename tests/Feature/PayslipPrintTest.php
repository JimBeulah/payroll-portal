<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_all_payslips_returns_200_for_locked_run(): void
    {
        $user = User::factory()->create();
        $run = PayrollRun::factory()->locked()->create();
        $employee = Employee::factory()->create();
        PayrollEntry::factory()->for($run)->for($employee)->create();

        $this->actingAs($user)
            ->get("/payroll-runs/{$run->id}/payslips/print")
            ->assertOk()
            ->assertSee('Print All');
    }

    public function test_print_all_payslips_returns_403_for_draft_run(): void
    {
        $user = User::factory()->create();
        $run = PayrollRun::factory()->create();

        $this->actingAs($user)
            ->get("/payroll-runs/{$run->id}/payslips/print")
            ->assertForbidden();
    }

    public function test_print_all_payslips_requires_authentication(): void
    {
        $run = PayrollRun::factory()->locked()->create();

        $this->get("/payroll-runs/{$run->id}/payslips/print")
            ->assertRedirect('/login');
    }
}

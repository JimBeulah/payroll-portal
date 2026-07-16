<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollEntry;
use App\Models\PayrollManualAttendance;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAttendanceSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_view_their_own_attendance_calendar(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create(['role' => 'employee']);
        $employee->update(['user_id' => $user->id]);

        $run = PayrollRun::factory()->create([
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-15',
        ]);
        PayrollEntry::factory()->create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id]);

        PayrollManualAttendance::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'date' => '2026-06-05',
            'sw' => '08:15',
            'ew' => '17:00',
            'shift_start' => $employee->shift_start,
            'shift_end' => $employee->shift_end,
            'note' => 'secret HR note',
            'is_override' => true,
        ]);

        Holiday::create(['name' => 'Independence Day', 'date' => '2026-06-12', 'type' => 'regular']);

        $response = $this->actingAs($user)->get('/my-attendance');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('attendance/index')
            ->where('selectedRun.id', $run->id)
            ->where('holidays.0.name', 'Independence Day')
            ->where('manualAttendances.0.is_override', true)
            ->missing('manualAttendances.0.note')
        );
    }

    public function test_employee_without_linked_record_is_forbidden(): void
    {
        $user = User::factory()->create(['role' => 'employee']);

        $this->actingAs($user)->get('/my-attendance')->assertForbidden();
    }

    public function test_employee_cannot_view_a_run_they_were_not_part_of(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create(['role' => 'employee']);
        $employee->update(['user_id' => $user->id]);

        $ownRun = PayrollRun::factory()->create(['period_start' => '2026-06-01', 'period_end' => '2026-06-15']);
        PayrollEntry::factory()->create(['payroll_run_id' => $ownRun->id, 'employee_id' => $employee->id]);

        $otherRun = PayrollRun::factory()->create(['period_start' => '2026-05-01', 'period_end' => '2026-05-15']);

        $this->actingAs($user)->get('/my-attendance?run='.$otherRun->id)->assertNotFound();
    }
}

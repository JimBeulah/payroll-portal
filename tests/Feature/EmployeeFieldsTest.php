<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_be_created_with_employee_number_and_gender(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/employees', [
                'name' => 'Juan dela Cruz',
                'department' => 'IT',
                'daily_rate' => 700,
                'shift_start' => '08:00',
                'shift_end' => '17:00',
                'employee_number' => 'EMP-001',
                'gender' => 'Male',
            ])
            ->assertRedirect('/employees');

        $employee = Employee::where('name', 'Juan dela Cruz')->first();
        $this->assertSame('EMP-001', $employee->employee_number);
        $this->assertSame('Male', $employee->gender);
    }

    public function test_employee_can_be_created_without_employee_number_and_gender(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/employees', [
                'name' => 'Maria Santos',
                'department' => 'HR',
                'daily_rate' => 600,
                'shift_start' => '08:00',
                'shift_end' => '17:00',
            ])
            ->assertRedirect('/employees');

        $employee = Employee::where('name', 'Maria Santos')->first();
        $this->assertNull($employee->employee_number);
        $this->assertNull($employee->gender);
    }

    public function test_gender_must_be_male_or_female_if_provided(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/employees', [
                'name' => 'Test Person',
                'department' => 'IT',
                'daily_rate' => 700,
                'shift_start' => '08:00',
                'shift_end' => '17:00',
                'gender' => 'Other',
            ])
            ->assertSessionHasErrors('gender');
    }
}

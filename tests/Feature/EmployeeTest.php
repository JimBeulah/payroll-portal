<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    public function test_can_list_employees(): void
    {
        Employee::factory()->count(3)->create();
        $response = $this->actingAsAdmin()->get('/employees');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees', 3)
        );
    }

    public function test_can_create_employee_with_login_account(): void
    {
        $response = $this->actingAsAdmin()->post('/employees', [
            'name' => 'Juan Dela Cruz',
            'department' => 'ADMIN',
            'daily_rate' => 550.00,
            'shift_start' => '08:00',
            'shift_end' => '17:00',
            'username' => 'juandelacruz',
            'password' => '123700',
        ]);
        $response->assertRedirect('/employees');

        $this->assertDatabaseHas('users', ['username' => 'juandelacruz', 'role' => 'employee']);
        $user = User::where('username', 'juandelacruz')->first();
        $this->assertDatabaseHas('employees', ['name' => 'Juan Dela Cruz', 'user_id' => $user->id]);
    }

    public function test_creating_employee_requires_account_fields(): void
    {
        $response = $this->actingAsAdmin()->post('/employees', [
            'name' => 'No Account',
            'daily_rate' => 500.00,
            'shift_start' => '08:00',
            'shift_end' => '17:00',
        ]);

        $response->assertSessionHasErrors(['username', 'password']);
    }

    public function test_can_update_employee_and_reset_password(): void
    {
        $user = User::factory()->employee()->create(['username' => 'old_name']);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAsAdmin()->put("/employees/{$employee->id}", [
            'name' => 'Updated Name',
            'department' => 'ADMIN',
            'daily_rate' => 600.00,
            'shift_start' => '08:00',
            'shift_end' => '17:00',
            'username' => 'updated_name',
            'password' => 'newpass1',
        ]);
        $response->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'name' => 'Updated Name']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'username' => 'updated_name']);
    }

    public function test_can_delete_employee(): void
    {
        $employee = Employee::factory()->create();
        $response = $this->actingAsAdmin()->delete("/employees/{$employee->id}");
        $response->assertRedirect('/employees');
        // Employee uses soft deletes, so the row remains but is flagged deleted.
        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
    }

    public function test_employee_number_must_be_unique(): void
    {
        Employee::factory()->create(['employee_number' => 'EMP-001']);

        $response = $this->actingAsAdmin()->post('/employees', [
            'name' => 'Another Employee',
            'employee_number' => 'EMP-001',
            'department' => 'ADMIN',
            'daily_rate' => 500.00,
            'shift_start' => '08:00',
            'shift_end' => '17:00',
        ]);

        $response->assertSessionHasErrors('employee_number');
    }
}

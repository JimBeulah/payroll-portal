<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\NewRequestSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NewRequestSubmittedTest extends TestCase
{
    use RefreshDatabase;

    private function employeeUser(): User
    {
        $user = User::factory()->employee()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    public function test_submitting_cash_advance_request_notifies_admin_hr_and_overseer(): void
    {
        Notification::fake();

        $employeeUser = $this->employeeUser();
        $admin = User::factory()->admin()->create();
        $hr = User::factory()->hr()->create();
        $overseer = User::factory()->overseer()->create();

        $this->actingAs($employeeUser)->post('/my-requests/cash-advance', [
            'amount' => 1500,
            'needed_date' => '2026-05-10',
            'reason' => 'Medical',
        ])->assertRedirect('/my-requests');

        Notification::assertSentTo($admin, NewRequestSubmitted::class);
        Notification::assertSentTo($hr, NewRequestSubmitted::class);
        Notification::assertSentTo($overseer, NewRequestSubmitted::class);
        Notification::assertNotSentTo($employeeUser, NewRequestSubmitted::class);
    }

    public function test_submitting_leave_request_notifies_admin_hr_and_overseer(): void
    {
        Notification::fake();

        $employeeUser = $this->employeeUser();
        $admin = User::factory()->admin()->create();

        $this->actingAs($employeeUser)->post('/my-requests/leave', [
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-13',
            'reason' => 'Family matter',
        ])->assertRedirect('/my-requests');

        Notification::assertSentTo($admin, NewRequestSubmitted::class);
        Notification::assertNotSentTo($employeeUser, NewRequestSubmitted::class);
    }
}

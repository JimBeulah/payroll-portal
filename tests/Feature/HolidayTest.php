<?php
namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_can_list_holidays(): void
    {
        Holiday::factory()->count(2)->create();
        $this->actingAs($this->admin())->get('/holidays')
            ->assertStatus(200)
            ->assertInertia(fn ($p) => $p->component('holidays/index')->has('holidays', 2));
    }

    public function test_can_create_holiday(): void
    {
        $this->actingAs($this->admin())->post('/holidays', [
            'name' => 'Labor Day',
            'date' => '2026-05-01',
            'type' => 'regular',
        ])->assertRedirect('/holidays');
        $this->assertDatabaseHas('holidays', ['name' => 'Labor Day']);
    }

    public function test_can_delete_holiday(): void
    {
        $holiday = Holiday::factory()->create();
        $this->actingAs($this->admin())->delete("/holidays/{$holiday->id}")
            ->assertRedirect('/holidays');
        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }
}

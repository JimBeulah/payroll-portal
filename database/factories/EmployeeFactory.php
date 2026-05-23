<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'department' => $this->faker->randomElement(['ADMIN', 'BHAGOH', 'BFAITH']),
            'daily_rate' => $this->faker->randomElement([510, 550, 600, 700, 900]),
            'shift_start' => '08:00:00',
            'shift_end' => '17:00:00',
            'is_active' => true,
        ];
    }
}

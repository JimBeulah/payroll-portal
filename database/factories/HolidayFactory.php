<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class HolidayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word() . ' Holiday',
            'date' => $this->faker->dateTimeBetween('2026-01-01', '2026-12-31')->format('Y-m-d'),
            'type' => $this->faker->randomElement(['regular', 'special']),
        ];
    }
}

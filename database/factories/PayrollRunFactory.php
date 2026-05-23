<?php
namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-15',
            'payable_date' => '2026-05-20',
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }
}

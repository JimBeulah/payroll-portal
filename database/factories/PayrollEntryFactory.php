<?php
namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollEntryFactory extends Factory
{
    protected $model = PayrollEntry::class;

    public function definition(): array
    {
        return [
            'payroll_run_id'      => PayrollRun::factory(),
            'employee_id'         => Employee::factory(),
            'days_present'        => 13,
            'total_basic_pay'     => 9100.00,
            'overtime_minutes'    => 0,
            'overtime_pay'        => 0.00,
            'late_minutes'        => 0,
            'late_deduction'      => 0.00,
            'undertime_minutes'   => 0,
            'undertime_deduction' => 0.00,
            'holiday_pay'         => 0.00,
            'gross_pay'           => 9100.00,
            'cash_advance'        => 0.00,
            'other_deductions'    => 0.00,
            'total_deductions'    => 0.00,
            'net_pay'             => 9100.00,
            'first_release'       => 0.00,
            'second_release'      => 0.00,
        ];
    }
}

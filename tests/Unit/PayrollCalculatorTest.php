<?php
namespace Tests\Unit;

use App\Models\Employee;
use App\Models\Holiday;
use App\Services\PayrollCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployee(float $rate = 480.00, string $start = '08:00', string $end = '17:00'): Employee
    {
        return Employee::factory()->create([
            'daily_rate' => $rate,
            'shift_start' => $start . ':00',
            'shift_end' => $end . ':00',
        ]);
    }

    public function test_computes_basic_pay_for_full_day(): void
    {
        $employee = $this->makeEmployee(480.00);
        $attendance = [
            '2026-05-01' => ['sw' => '08:00', 'ew' => '17:00'],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect());

        $this->assertEquals(1, $result['days_present']);
        $this->assertEquals(480.00, $result['total_basic_pay']);
        $this->assertEquals(0, $result['late_minutes']);
        $this->assertEquals(0, $result['overtime_minutes']);
    }

    public function test_computes_late_deduction(): void
    {
        $employee = $this->makeEmployee(480.00);
        // Per-minute rate = 480 / 8 / 60 = 1.00/min
        // Late 10 min = 10.00 deduction
        $attendance = [
            '2026-05-01' => ['sw' => '08:10', 'ew' => '17:00'],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect());

        $this->assertEquals(10, $result['late_minutes']);
        $this->assertEquals(10.00, $result['late_deduction']);
    }

    public function test_computes_overtime(): void
    {
        $employee = $this->makeEmployee(480.00);
        // OT 30 min = 30.00
        $attendance = [
            '2026-05-01' => ['sw' => '08:00', 'ew' => '17:30'],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect());

        $this->assertEquals(30, $result['overtime_minutes']);
        $this->assertEquals(30.00, $result['overtime_pay']);
    }

    public function test_computes_undertime_deduction(): void
    {
        $employee = $this->makeEmployee(480.00);
        // Undertime 15 min = 15.00 deduction
        $attendance = [
            '2026-05-01' => ['sw' => '08:00', 'ew' => '16:45'],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect());

        $this->assertEquals(15, $result['undertime_minutes']);
        $this->assertEquals(15.00, $result['undertime_deduction']);
    }

    public function test_absent_day_not_counted(): void
    {
        $employee = $this->makeEmployee(480.00);
        $attendance = [
            '2026-05-01' => ['sw' => null, 'ew' => null],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect());

        $this->assertEquals(0, $result['days_present']);
        $this->assertEquals(0.00, $result['total_basic_pay']);
    }

    public function test_regular_holiday_doubles_daily_pay(): void
    {
        $employee = $this->makeEmployee(480.00);
        $holiday = Holiday::factory()->create([
            'date' => '2026-05-01',
            'type' => 'regular',
        ]);
        $attendance = [
            '2026-05-01' => ['sw' => '08:00', 'ew' => '17:00'],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect([$holiday]));

        // basic pay = 480, holiday adjustment = +480 (extra 1x)
        $this->assertEquals(480.00, $result['holiday_pay']);
    }

    public function test_special_holiday_adds_30_percent(): void
    {
        $employee = $this->makeEmployee(480.00);
        $holiday = Holiday::factory()->create([
            'date' => '2026-05-01',
            'type' => 'special',
        ]);
        $attendance = [
            '2026-05-01' => ['sw' => '08:00', 'ew' => '17:00'],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect([$holiday]));

        $this->assertEqualsWithDelta(144.00, $result['holiday_pay'], 0.01);
    }

    public function test_absent_on_holiday_earns_no_holiday_pay(): void
    {
        $employee = $this->makeEmployee(480.00);
        $holiday = Holiday::factory()->create([
            'date' => '2026-05-01',
            'type' => 'regular',
        ]);
        $attendance = [
            '2026-05-01' => ['sw' => null, 'ew' => null],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect([$holiday]));

        $this->assertEquals(0.00, $result['holiday_pay']);
    }

    public function test_gross_pay_formula(): void
    {
        $employee = $this->makeEmployee(480.00);
        // Present 2 days, 10 min late day 1
        $attendance = [
            '2026-05-01' => ['sw' => '08:10', 'ew' => '17:00'],
            '2026-05-02' => ['sw' => '08:00', 'ew' => '17:00'],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect());

        // basic = 960, late_deduction = 10, gross = 950
        $this->assertEquals(960.00, $result['total_basic_pay']);
        $this->assertEquals(10.00, $result['late_deduction']);
        $this->assertEquals(950.00, $result['gross_pay']);
    }
}

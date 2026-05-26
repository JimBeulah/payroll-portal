<?php
namespace App\Services;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PayrollCalculator
{
    public function calculate(Employee $employee, array $attendanceDays, Collection $holidays): array
    {
        $perMinuteRate = $employee->daily_rate / 8 / 60;

        $daysPresent = 0;
        $totalBasicPay = 0.0;
        $overtimeMinutes = 0;
        $lateMinutes = 0;
        $undertimeMinutes = 0;
        $holidayPay = 0.0;

        $holidayMap = $holidays->keyBy(fn($h) => $h->date->format('Y-m-d'));

        foreach ($attendanceDays as $date => $times) {
            if (empty($times['sw']) || empty($times['ew'])) {
                continue;
            }

            $daysPresent++;
            $totalBasicPay += $employee->daily_rate;

            // shift_start/shift_end stored as "08:00:00" — take first 5 chars
            $shiftStartTime = substr($employee->shift_start, 0, 5);
            $shiftEndTime   = substr($employee->shift_end, 0, 5);

            $shiftStart  = Carbon::createFromFormat('Y-m-d H:i', "$date $shiftStartTime");
            $shiftEnd    = Carbon::createFromFormat('Y-m-d H:i', "$date $shiftEndTime");
            $actualStart = Carbon::createFromFormat('Y-m-d H:i', "$date {$times['sw']}");
            $actualEnd   = Carbon::createFromFormat('Y-m-d H:i', "$date {$times['ew']}");

            if ($actualStart->gt($shiftStart)) {
                $lateMinutes += abs($actualStart->diffInMinutes($shiftStart));
            }

            if ($actualEnd->lt($shiftEnd)) {
                $undertimeMinutes += abs($shiftEnd->diffInMinutes($actualEnd));
            }

            if ($actualEnd->gt($shiftEnd)) {
                $overtimeMinutes += abs($actualEnd->diffInMinutes($shiftEnd));
            }

            if (isset($holidayMap[$date])) {
                $multiplier = $holidayMap[$date]->type === 'regular' ? 2.0 : 1.3;
                $holidayPay += $employee->daily_rate * ($multiplier - 1);
            }
        }

        $lateDeduction      = round($lateMinutes * $perMinuteRate, 2);
        $undertimeDeduction = round($undertimeMinutes * $perMinuteRate, 2);
        $overtimePay        = round($overtimeMinutes * $perMinuteRate, 2);
        $grossPay           = round($totalBasicPay + $overtimePay + $holidayPay - $lateDeduction - $undertimeDeduction, 2);

        return [
            'days_present'        => $daysPresent,
            'total_basic_pay'     => round($totalBasicPay, 2),
            'overtime_minutes'    => $overtimeMinutes,
            'overtime_pay'        => $overtimePay,
            'late_minutes'        => $lateMinutes,
            'late_deduction'      => $lateDeduction,
            'undertime_minutes'   => $undertimeMinutes,
            'undertime_deduction' => $undertimeDeduction,
            'holiday_pay'         => round($holidayPay, 2),
            'gross_pay'           => $grossPay,
        ];
    }
}

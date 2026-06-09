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

        foreach ($attendanceDays as $dateKey => $times) {
            if (empty($times['sw']) || empty($times['ew'])) {
                continue;
            }

            // Manual entries carry a _date key because their dateKey includes an ID suffix
            $date = $times['_date'] ?? $dateKey;

            // Additive (callback) entries are pure OT — no daily rate, every minute worked is overtime
            if ($times['is_additive'] ?? false) {
                $actualStart = Carbon::createFromFormat('Y-m-d H:i', "$date {$times['sw']}");
                $actualEnd   = Carbon::createFromFormat('Y-m-d H:i', "$date {$times['ew']}");
                if ($actualEnd->lte($actualStart)) {
                    $actualEnd->addDay();
                }
                $overtimeMinutes += (int) $actualStart->diffInMinutes($actualEnd);
                continue;
            }

            $daysPresent++;
            $totalBasicPay += $employee->daily_rate;

            // Per-day shift override (manual entries) takes priority over employee's default
            $shiftStartTime = $times['shift_start'] ?? substr($employee->shift_start, 0, 5);
            $shiftEndTime   = $times['shift_end']   ?? substr($employee->shift_end, 0, 5);

            $shiftStart  = Carbon::createFromFormat('Y-m-d H:i', "$date $shiftStartTime");
            $shiftEnd    = Carbon::createFromFormat('Y-m-d H:i', "$date $shiftEndTime");
            $actualStart = Carbon::createFromFormat('Y-m-d H:i', "$date {$times['sw']}");
            $actualEnd   = Carbon::createFromFormat('Y-m-d H:i', "$date {$times['ew']}");

            // Night shift: if end is before start, it falls on the next calendar day
            if ($shiftEnd->lte($shiftStart)) {
                $shiftEnd->addDay();
            }
            if ($actualEnd->lte($actualStart)) {
                $actualEnd->addDay();
            }

            // Derive the unpaid break window from the gap between shift duration and 8 working hours.
            // For a standard 08:00-17:00 shift: break = 12:00-13:00.
            $shiftTotalMinutes = (int) $shiftStart->diffInMinutes($shiftEnd);
            $breakMinutes      = max(0, $shiftTotalMinutes - 8 * 60);
            $breakStart        = $shiftStart->copy()->addMinutes(4 * 60); // 4h into shift
            $breakEnd          = $breakStart->copy()->addMinutes($breakMinutes);

            if ($actualStart->gt($shiftStart)) {
                $rawLate = (int) $shiftStart->diffInMinutes($actualStart);
                // Subtract the portion of the late window that falls inside the unpaid break
                if ($breakMinutes > 0 && $actualStart->gt($breakStart)) {
                    $overlapEnd = $actualStart->lte($breakEnd) ? $actualStart : $breakEnd;
                    $rawLate   -= (int) $breakStart->diffInMinutes($overlapEnd);
                }
                $lateMinutes += max(0, $rawLate);
            }

            if ($actualEnd->lt($shiftEnd)) {
                $rawUndertime = (int) $actualEnd->diffInMinutes($shiftEnd);
                // Subtract the portion of the undertime window that falls inside the unpaid break
                if ($breakMinutes > 0 && $actualEnd->lt($breakEnd)) {
                    $overlapStart  = $actualEnd->gte($breakStart) ? $actualEnd : $breakStart;
                    $rawUndertime -= (int) $overlapStart->diffInMinutes($breakEnd);
                }
                $undertimeMinutes += max(0, $rawUndertime);
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

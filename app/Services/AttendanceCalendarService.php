<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Storage;

class AttendanceCalendarService
{
    /**
     * Build per-day attendance (from the latest uploaded file) and per-day
     * approved-leave data for every employee in the given payroll run.
     *
     * Returns ['attendanceData' => [employeeId => [date => [...]]], 'leaveData' => [employeeId => [date => [...]]]].
     */
    public function buildForRun(PayrollRun $payrollRun): array
    {
        $periodStart = $payrollRun->period_start->format('Y-m-d');
        $periodEnd = $payrollRun->period_end->format('Y-m-d');

        $attendanceData = [];
        $upload = $payrollRun->uploads()->latest()->first();

        if ($upload && $upload->storage_path) {
            $fullPath = Storage::path($upload->storage_path);

            if (file_exists($fullPath)) {
                try {
                    $parsed = (new AttendanceParser)->parse($fullPath);

                    foreach ($parsed as $row) {
                        $emp = Employee::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($row['name']))])
                            ->whereRaw('LOWER(TRIM(department)) = ?', [strtolower(trim($row['department']))])
                            ->first();

                        if (! $emp) {
                            continue;
                        }

                        $shiftStart = substr($emp->shift_start, 0, 5);
                        $shiftEnd = substr($emp->shift_end, 0, 5);
                        $days = [];

                        foreach ($row['attendance'] as $date => $times) {
                            if ($date < $periodStart || $date > $periodEnd) {
                                continue;
                            }
                            if (empty($times['sw']) || empty($times['ew'])) {
                                continue;
                            }

                            $lateMin = 0;
                            $utMin = 0;
                            $otMin = 0;

                            try {
                                $sStart = Carbon::createFromFormat('Y-m-d H:i', "$date $shiftStart");
                                $sEnd = Carbon::createFromFormat('Y-m-d H:i', "$date $shiftEnd");
                                $aStart = Carbon::createFromFormat('Y-m-d H:i', "$date {$times['sw']}");
                                $aEnd = Carbon::createFromFormat('Y-m-d H:i', "$date {$times['ew']}");

                                if ($sEnd->lte($sStart)) {
                                    $sEnd->addDay();
                                }
                                if ($aEnd->lte($aStart)) {
                                    $aEnd->addDay();
                                }

                                $shiftMins = (int) $sStart->diffInMinutes($sEnd);
                                $breakMins = max(0, $shiftMins - 480);
                                $bStart = $sStart->copy()->addMinutes(240);
                                $bEnd = $bStart->copy()->addMinutes($breakMins);

                                if ($aStart->gt($sStart)) {
                                    $raw = (int) $sStart->diffInMinutes($aStart);
                                    if ($breakMins > 0 && $aStart->gt($bStart)) {
                                        $oe = $aStart->lte($bEnd) ? $aStart : $bEnd;
                                        $raw -= (int) $bStart->diffInMinutes($oe);
                                    }
                                    $lateMin = max(0, $raw);
                                }

                                if ($aEnd->lt($sEnd)) {
                                    $raw = (int) $aEnd->diffInMinutes($sEnd);
                                    if ($breakMins > 0 && $aEnd->lt($bEnd)) {
                                        $os = $aEnd->gte($bStart) ? $aEnd : $bStart;
                                        $raw -= (int) $os->diffInMinutes($bEnd);
                                    }
                                    $utMin = max(0, $raw);
                                }

                                if ($aEnd->gt($sEnd)) {
                                    $otMin = abs((int) $aEnd->diffInMinutes($sEnd));
                                }
                            } catch (\Exception) {
                                // Skip computation on malformed time value
                            }

                            $days[$date] = [
                                'sw' => $times['sw'],
                                'ew' => $times['ew'],
                                'late_minutes' => $lateMin,
                                'undertime_minutes' => $utMin,
                                'overtime_minutes' => $otMin,
                            ];
                        }

                        $attendanceData[$emp->id] = $days;
                    }
                } catch (\Exception) {
                    // If the file can't be parsed, just return empty — don't block the page
                }
            }
        }

        // Approved leave, expanded per-day so the calendar can flag unpaid leave days.
        $leaveData = [];
        $leaveRequests = LeaveRequest::approved()
            ->where('start_date', '<=', $periodEnd)
            ->where('end_date', '>=', $periodStart)
            ->get();

        foreach ($leaveRequests as $leave) {
            $rangeStart = max($leave->start_date->format('Y-m-d'), $periodStart);
            $rangeEnd = min($leave->end_date->format('Y-m-d'), $periodEnd);

            foreach (CarbonPeriod::create($rangeStart, $rangeEnd) as $date) {
                $leaveData[$leave->employee_id][$date->format('Y-m-d')] = [
                    'reason' => $leave->reason,
                ];
            }
        }

        return ['attendanceData' => $attendanceData, 'leaveData' => $leaveData];
    }
}

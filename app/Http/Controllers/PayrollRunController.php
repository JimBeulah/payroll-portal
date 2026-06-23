<?php
namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollRunRequest;
use App\Http\Requests\UpdatePayrollRunRequest;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollRun;
use App\Services\AttendanceParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PayrollRunController extends Controller
{
    public function index()
    {
        return Inertia::render('payroll/index', [
            'runs' => PayrollRun::latest()->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('payroll/create');
    }

    public function store(StorePayrollRunRequest $request)
    {
        $run = new PayrollRun($request->validated());
        $run->created_by = auth()->id();
        $run->save();

        return redirect()->route('payroll-runs.show', $run);
    }

    public function show(PayrollRun $payrollRun)
    {
        $payrollRun->load('entries.employee', 'uploads', 'manualAttendances.employee');

        $periodStart = $payrollRun->period_start->format('Y-m-d');
        $periodEnd   = $payrollRun->period_end->format('Y-m-d');

        // Re-parse the latest uploaded attendance file to get per-day SW/EW and compute late/undertime.
        // This data is not persisted — it's derived on page load so the calendar can show file data.
        $attendanceData = [];
        $upload = $payrollRun->uploads()->latest()->first();

        if ($upload && $upload->storage_path) {
            $fullPath = Storage::path($upload->storage_path);

            if (file_exists($fullPath)) {
                try {
                    $parsed = (new AttendanceParser())->parse($fullPath);

                    foreach ($parsed as $row) {
                        $emp = Employee::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($row['name']))])
                            ->whereRaw('LOWER(TRIM(department)) = ?', [strtolower(trim($row['department']))])
                            ->first();

                        if (!$emp) continue;

                        $shiftStart = substr($emp->shift_start, 0, 5);
                        $shiftEnd   = substr($emp->shift_end, 0, 5);
                        $days = [];

                        foreach ($row['attendance'] as $date => $times) {
                            if ($date < $periodStart || $date > $periodEnd) continue;
                            if (empty($times['sw']) || empty($times['ew'])) continue;

                            $lateMin = 0;
                            $utMin   = 0;
                            $otMin   = 0;

                            try {
                                $sStart = Carbon::createFromFormat('Y-m-d H:i', "$date $shiftStart");
                                $sEnd   = Carbon::createFromFormat('Y-m-d H:i', "$date $shiftEnd");
                                $aStart = Carbon::createFromFormat('Y-m-d H:i', "$date {$times['sw']}");
                                $aEnd   = Carbon::createFromFormat('Y-m-d H:i', "$date {$times['ew']}");

                                if ($sEnd->lte($sStart))  $sEnd->addDay();
                                if ($aEnd->lte($aStart)) $aEnd->addDay();

                                $shiftMins  = (int) $sStart->diffInMinutes($sEnd);
                                $breakMins  = max(0, $shiftMins - 480);
                                $bStart     = $sStart->copy()->addMinutes(240);
                                $bEnd       = $bStart->copy()->addMinutes($breakMins);

                                if ($aStart->gt($sStart)) {
                                    $raw = (int) $sStart->diffInMinutes($aStart);
                                    if ($breakMins > 0 && $aStart->gt($bStart)) {
                                        $oe   = $aStart->lte($bEnd) ? $aStart : $bEnd;
                                        $raw -= (int) $bStart->diffInMinutes($oe);
                                    }
                                    $lateMin = max(0, $raw);
                                }

                                if ($aEnd->lt($sEnd)) {
                                    $raw = (int) $aEnd->diffInMinutes($sEnd);
                                    if ($breakMins > 0 && $aEnd->lt($bEnd)) {
                                        $os   = $aEnd->gte($bStart) ? $aEnd : $bStart;
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
                                'sw'               => $times['sw'],
                                'ew'               => $times['ew'],
                                'late_minutes'     => $lateMin,
                                'undertime_minutes'=> $utMin,
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

        return Inertia::render('payroll/show', [
            'run'              => $payrollRun,
            'entries'          => $payrollRun->entries,
            'uploads'          => $payrollRun->uploads,
            'manualAttendances' => $payrollRun->manualAttendances,
            'employees'        => Employee::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'department', 'shift_start', 'shift_end']),
            'attendanceData'   => $attendanceData,
        ]);
    }

    public function update(UpdatePayrollRunRequest $request, PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403, 'Locked payroll runs cannot be edited.');

        $payrollRun->update($request->validated());

        return back()->with('success', 'Payroll run updated.');
    }

    public function destroy(PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403, 'Locked payroll runs cannot be deleted.');

        DB::transaction(function () use ($payrollRun) {
            $payrollRun->entries()->delete();
            $payrollRun->uploads()->delete();
            $payrollRun->delete();
        });

        return redirect()->route('payroll-runs.index')
            ->with('success', 'Payroll run deleted.');
    }
}

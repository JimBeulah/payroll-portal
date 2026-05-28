<?php
namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollRunRequest;
use App\Models\AttendanceUpload;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Services\AttendanceParser;
use App\Services\PayrollCalculator;
use App\Services\PayrollExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
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
        $run = PayrollRun::create([
            ...$request->validated(),
            'created_by' => auth()->id(),
        ]);

        return redirect("/payroll-runs/{$run->id}");
    }

    public function debug(PayrollRun $payrollRun)
    {
        // Find the most recently uploaded file in storage/app/attendance (or private/attendance)
        $candidates = array_merge(
            glob(storage_path('app/attendance/*.xlsx')) ?: [],
            glob(storage_path('app/attendance/*.xls'))  ?: [],
            glob(storage_path('app/private/attendance/*.xlsx')) ?: [],
            glob(storage_path('app/private/attendance/*.xls'))  ?: [],
        );
        usort($candidates, fn($a, $b) => filemtime($b) - filemtime($a));
        $filePath = $candidates[0] ?? null;

        $rawCells = null;
        if ($filePath && file_exists($filePath)) {
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath)->getActiveSheet();
            $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
                $sheet->getHighestColumn()
            );
            $dump = [];
            for ($c = 5; $c <= min($maxCol, 22); $c++) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                $dump[$col] = [
                    'r4_val'  => $sheet->getCell("{$col}4")->getValue(),
                    'r4_type' => $sheet->getCell("{$col}4")->getDataType(),
                    'r5_val'  => $sheet->getCell("{$col}5")->getValue(),
                    'r6_val'  => $sheet->getCell("{$col}6")->getValue(),
                    'r6_type' => $sheet->getCell("{$col}6")->getDataType(),
                    'r6_fmt'  => $sheet->getCell("{$col}6")->getFormattedValue(),
                    'r7_val'  => $sheet->getCell("{$col}7")->getValue(),
                    'r7_fmt'  => $sheet->getCell("{$col}7")->getFormattedValue(),
                    'r8_val'  => $sheet->getCell("{$col}8")->getValue(),
                ];
            }
            $rawCells = ['file' => basename($filePath), 'columns' => $dump];
        } else {
            $rawCells = ['error' => 'No xlsx/xls found in storage/app/attendance'];
        }

        $parsed = session("parsed_attendance_{$payrollRun->id}", []);
        $sample = !empty($parsed) ? array_slice($parsed[0]['attendance'], 0, 3, true) : [];

        return response()->json([
            'period'           => $payrollRun->period_start->format('Y-m-d').' to '.$payrollRun->period_end->format('Y-m-d'),
            'session_empty'    => empty($parsed),
            'employees_parsed' => count($parsed),
            'parsed_sample'    => $sample,
            'raw_cells'        => $rawCells,
        ], 200, [], JSON_PRETTY_PRINT);
    }

    public function show(PayrollRun $payrollRun)
    {
        $payrollRun->load('entries.employee', 'uploads');

        $existingEmployeeIds = $payrollRun->entries->pluck('employee_id');

        $availableEmployees = Employee::where('is_active', true)
            ->whereNotIn('id', $existingEmployeeIds)
            ->orderBy('name')
            ->get(['id', 'name', 'department', 'daily_rate']);

        return Inertia::render('payroll/show', [
            'run' => $payrollRun,
            'entries' => $payrollRun->entries,
            'uploads' => $payrollRun->uploads,
            'availableEmployees' => $availableEmployees,
        ]);
    }

    public function storeEntry(Request $request, PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403);

        $data = $request->validate([
            'employee_id'  => ['required', 'exists:employees,id'],
            'days_present' => ['required', 'integer', 'min:1'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);

        $basicPay = round($employee->daily_rate * $data['days_present'], 2);

        PayrollEntry::create([
            'payroll_run_id'      => $payrollRun->id,
            'employee_id'         => $employee->id,
            'days_present'        => $data['days_present'],
            'total_basic_pay'     => $basicPay,
            'overtime_minutes'    => 0,
            'overtime_pay'        => 0,
            'late_minutes'        => 0,
            'late_deduction'      => 0,
            'undertime_minutes'   => 0,
            'undertime_deduction' => 0,
            'holiday_pay'         => 0,
            'gross_pay'           => $basicPay,
            'cash_advance'        => 0,
            'other_deductions'    => 0,
            'total_deductions'    => 0,
            'net_pay'             => $basicPay,
            'first_release'       => 0,
            'second_release'      => 0,
        ]);

        return redirect("/payroll-runs/{$payrollRun->id}")
            ->with('success', "{$employee->name} added to payroll.");
    }

    public function upload(Request $request, PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls']]);

        $file = $request->file('file');
        $path = $file->store('attendance');

        AttendanceUpload::create([
            'payroll_run_id' => $payrollRun->id,
            'filename' => $file->getClientOriginalName(),
            'uploaded_at' => now(),
        ]);

        // Resolve actual storage path (handles both local and private disks)
        $fullPath = storage_path("app/private/{$path}");
        if (!file_exists($fullPath)) {
            $fullPath = storage_path("app/{$path}");
        }
        $parsed = (new AttendanceParser())->parse($fullPath);
        session(["parsed_attendance_{$payrollRun->id}" => $parsed]);

        return redirect("/payroll-runs/{$payrollRun->id}")
            ->with('parsed', $parsed);
    }

    public function destroyUpload(AttendanceUpload $attendanceUpload)
    {
        $run = $attendanceUpload->payrollRun;
        abort_if($run->isLocked(), 403);

        $attendanceUpload->delete();

        // Clear parsed session cache and computed entries so the summary resets
        session()->forget("parsed_attendance_{$run->id}");
        $run->entries()->delete();

        return redirect("/payroll-runs/{$run->id}")
            ->with('success', 'Attendance file removed and payroll entries cleared.');
    }

    public function compute(Request $request, PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403);

        $parsed = session("parsed_attendance_{$payrollRun->id}", []);

        if (empty($parsed)) {
            return back()->withErrors(['file' => 'Please upload an attendance file first.']);
        }

        $holidays = Holiday::whereBetween('date', [
            $payrollRun->period_start,
            $payrollRun->period_end,
        ])->get();

        $calculator = new PayrollCalculator();

        $payrollRun->entries()->delete();

        $unmatched = [];

        foreach ($parsed as $row) {
            $employee = Employee::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($row['name']))])
                ->whereRaw('LOWER(TRIM(department)) = ?', [strtolower(trim($row['department']))])
                ->first();

            if (!$employee) {
                $unmatched[] = "{$row['name']} ({$row['department']})";
                continue;
            }

            $periodStart = $payrollRun->period_start->format('Y-m-d');
            $periodEnd   = $payrollRun->period_end->format('Y-m-d');

            $periodAttendance = array_filter(
                $row['attendance'],
                fn($date) => $date >= $periodStart && $date <= $periodEnd,
                ARRAY_FILTER_USE_KEY
            );

            $computed = $calculator->calculate($employee, $periodAttendance, $holidays);

            PayrollEntry::create([
                'payroll_run_id' => $payrollRun->id,
                'employee_id' => $employee->id,
                'cash_advance' => 0,
                'other_deductions' => 0,
                'total_deductions' => 0,
                'net_pay' => $computed['gross_pay'],
                'first_release' => 0,
                'second_release' => 0,
                ...$computed,
            ]);
        }

        if (!empty($unmatched)) {
            return redirect("/payroll-runs/{$payrollRun->id}")
                ->with('unmatched', $unmatched);
        }

        return redirect("/payroll-runs/{$payrollRun->id}")
            ->with('success', 'Payroll computed.');
    }

    public function destroy(PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403, 'Locked payroll runs cannot be deleted.');

        $payrollRun->entries()->delete();
        $payrollRun->uploads()->delete();
        $payrollRun->delete();

        return redirect('/payroll-runs')->with('success', 'Payroll run deleted.');
    }

    public function lock(PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403);

        $payrollRun->update(['status' => 'locked']);

        return redirect("/payroll-runs/{$payrollRun->id}")
            ->with('success', 'Payroll run locked.');
    }

    public function export(PayrollRun $payrollRun)
    {
        abort_if(!$payrollRun->isLocked(), 403);

        $path = (new PayrollExportService())->export($payrollRun);
        $filename = "payroll-{$payrollRun->period_start}-{$payrollRun->period_end}.xlsx";

        return Response::download($path, $filename)->deleteFileAfterSend(true);
    }
}

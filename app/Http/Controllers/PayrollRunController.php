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

    public function show(PayrollRun $payrollRun)
    {
        $payrollRun->load('entries.employee', 'uploads');

        return Inertia::render('payroll/show', [
            'run' => $payrollRun,
            'entries' => $payrollRun->entries,
            'uploads' => $payrollRun->uploads,
        ]);
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
            $employee = Employee::where('name', $row['name'])
                ->where('department', $row['department'])
                ->first();

            if (!$employee) {
                $unmatched[] = $row['name'];
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

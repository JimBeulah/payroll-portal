<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Employees have no payroll dashboard — send them to their request portal.
        if (! request()->user()->canViewPayroll()) {
            return redirect()->route('my-requests.index');
        }

        $totalEmployees = Employee::count();
        $activeEmployees = Employee::where('is_active', true)->count();

        $latestRun = PayrollRun::with('entries')
            ->latest()
            ->first();

        $latestRunNetPay = $latestRun?->entries->sum('net_pay') ?? 0;
        $latestRunEmployeeCount = $latestRun?->entries->count() ?? 0;
        $latestRunPeriod = $latestRun
            ? $latestRun->period_start->format('M d').' – '.$latestRun->period_end->format('M d, Y')
            : null;

        $totalRunsThisYear = PayrollRun::whereYear('created_at', now()->year)->count();
        $lockedRuns = PayrollRun::where('status', 'locked')->count();

        // Last 8 payroll runs for the trend chart
        $payrollTrend = PayrollRun::with('entries')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($run) => [
                'label' => $run->payable_date->format('M d'),
                'net_pay' => round((float) $run->entries->sum('net_pay'), 2),
                'gross_pay' => round((float) $run->entries->sum('gross_pay'), 2),
                'employee_count' => $run->entries->count(),
            ])
            ->reverse()
            ->values();

        // Department distribution
        $departmentStats = Employee::where('is_active', true)
            ->select('department', DB::raw('count(*) as total'))
            ->groupBy('department')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($d) => [
                'department' => $d->department ?: 'Unassigned',
                'total' => $d->total,
            ]);

        // Recent payroll runs (last 5)
        $recentRuns = PayrollRun::with('entries')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($run) => [
                'id' => $run->id,
                'period_start' => $run->period_start->format('Y-m-d'),
                'period_end' => $run->period_end->format('Y-m-d'),
                'payable_date' => $run->payable_date->format('Y-m-d'),
                'status' => $run->status,
                'net_pay' => round((float) $run->entries->sum('net_pay'), 2),
                'employee_count' => $run->entries->count(),
            ]);

        // Top earners from latest run
        $topEarners = $latestRun
            ? PayrollEntry::where('payroll_run_id', $latestRun->id)
                ->with('employee:id,name,department')
                ->orderByDesc('net_pay')
                ->limit(5)
                ->get()
                ->map(fn ($e) => [
                    'name' => $e->employee?->name ?? 'Unknown',
                    'department' => $e->employee?->department ?? '—',
                    'net_pay' => round((float) $e->net_pay, 2),
                ])
            : collect();

        return Inertia::render('dashboard', [
            'stats' => [
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'inactive_employees' => $totalEmployees - $activeEmployees,
                'latest_run_net_pay' => $latestRunNetPay,
                'latest_run_employees' => $latestRunEmployeeCount,
                'latest_run_period' => $latestRunPeriod,
                'total_runs_this_year' => $totalRunsThisYear,
                'locked_runs' => $lockedRuns,
            ],
            'payrollTrend' => $payrollTrend,
            'departmentStats' => $departmentStats,
            'recentRuns' => $recentRuns,
            'topEarners' => $topEarners,
        ]);
    }
}

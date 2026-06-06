<?php
namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Services\PayrollExportService;
use Illuminate\Support\Facades\Response;

class PayrollExportController extends Controller
{
    public function show(PayrollRun $payrollRun)
    {
        abort_if(!$payrollRun->isLocked(), 403);

        $path     = (new PayrollExportService())->export($payrollRun);
        $start    = $payrollRun->period_start->format('M d, Y');
        $end      = $payrollRun->period_end->format('M d, Y');
        $filename = "payroll-{$start}-{$end}.xlsx";

        return Response::download($path, $filename)->deleteFileAfterSend(true);
    }
}

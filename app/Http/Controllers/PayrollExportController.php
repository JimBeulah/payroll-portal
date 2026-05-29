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
        $filename = "payroll-{$payrollRun->period_start}-{$payrollRun->period_end}.xlsx";

        return Response::download($path, $filename)->deleteFileAfterSend(true);
    }
}

<?php
namespace App\Http\Controllers;

use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Response;
use ZipArchive;

class PayslipController extends Controller
{
    public function download(PayrollEntry $payrollEntry)
    {
        abort_if(!$payrollEntry->payrollRun->isLocked(), 403);

        $payrollEntry->load('employee', 'payrollRun');

        $pdf = Pdf::loadView('payslip', [
            'entry'    => $payrollEntry,
            'employee' => $payrollEntry->employee,
            'run'      => $payrollEntry->payrollRun,
        ])->setPaper('a4', 'portrait');

        $filename = str($payrollEntry->employee->name)->slug() . '-payslip.pdf';
        return $pdf->download($filename);
    }

    public function downloadAll(PayrollRun $payrollRun)
    {
        abort_if(!$payrollRun->isLocked(), 403);

        $payrollRun->load('entries.employee');

        $zipPath = sys_get_temp_dir() . "/payslips-{$payrollRun->id}.zip";
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($payrollRun->entries as $entry) {
            $pdf = Pdf::loadView('payslip', [
                'entry'    => $entry,
                'employee' => $entry->employee,
                'run'      => $payrollRun,
            ])->setPaper('a4', 'portrait');

            $filename = str($entry->employee->name)->slug() . '-payslip.pdf';
            $zip->addFromString($filename, $pdf->output());
        }

        $zip->close();

        return Response::download($zipPath, "payslips-{$payrollRun->period_start}-{$payrollRun->period_end}.zip")
            ->deleteFileAfterSend(true);
    }

    public function printAll(PayrollRun $payrollRun)
    {
        abort_if(!$payrollRun->isLocked(), 403);

        $payrollRun->load('entries.employee');

        return view('payslip-batch', [
            'run'     => $payrollRun,
            'entries' => $payrollRun->entries,
        ]);
    }
}

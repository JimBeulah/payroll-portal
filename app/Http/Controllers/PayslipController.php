<?php
namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class PayslipController extends Controller
{
    private function companyData(): array
    {
        $logoPath = AppSetting::get('company_logo');

        return [
            'companyName' => AppSetting::get('company_name', 'Beulah Information Technology Services and Business Solutions Inc.'),
            'logoSrc'     => $logoPath
                ? Storage::disk('public')->path($logoPath)
                : public_path('payroll-logo.png'),
        ];
    }

    public function download(PayrollEntry $payrollEntry)
    {
        abort_if(!$payrollEntry->payrollRun->isLocked(), 403);

        $payrollEntry->load('employee', 'payrollRun');

        $logoPath = AppSetting::get('company_logo');

        return view('payslip-single', array_merge($this->companyData(), [
            'entry'    => $payrollEntry,
            'employee' => $payrollEntry->employee,
            'run'      => $payrollEntry->payrollRun,
            'logoSrc'  => $logoPath
                ? '/storage/' . $logoPath
                : '/payroll-logo.png',
        ]));
    }

    public function downloadAll(PayrollRun $payrollRun)
    {
        abort_if(!$payrollRun->isLocked(), 403);

        $payrollRun->load('entries.employee');

        $companyData = $this->companyData();

        $zipPath = sys_get_temp_dir() . "/payslips-{$payrollRun->id}.zip";
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($payrollRun->entries as $entry) {
            $pdf = Pdf::loadView('payslip', array_merge($companyData, [
                'entry'    => $entry,
                'employee' => $entry->employee,
                'run'      => $payrollRun,
            ]))->setPaper('a4', 'portrait');

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

        $logoPath = AppSetting::get('company_logo');

        return view('payslip-batch', array_merge($this->companyData(), [
            'run'     => $payrollRun,
            'entries' => $payrollRun->entries,
            'logoSrc' => $logoPath
                ? '/storage/' . $logoPath
                : '/payroll-logo.png',
        ]));
    }
}

<?php
namespace App\Services;

use App\Models\PayrollRun;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PayrollExportService
{
    public function export(PayrollRun $run): string
    {
        $run->load('entries.employee');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'PAYROLL SUMMARY');
        $sheet->setCellValue('A2', "PERIOD COVERED: {$run->period_start->format('M.d')}-{$run->period_end->format('d,Y')}");
        $sheet->setCellValue('A3', "Payable Date: {$run->payable_date->format('F d, Y')}");

        $headers = [
            'Employee #', 'Department', "Employee's Name", 'Daily Rate',
            'No. of Working Days', 'Total Pay', 'Holiday', 'Overtime Pay',
            'Absences Late/Undertime', 'GROSS PAY', 'Deductions C/A', 'Others',
            'Total Deduction', '1st Release', '2nd Release', 'BALANCE', 'NET PAY',
        ];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '5', $header);
            $col++;
        }

        $row = 6;
        foreach ($run->entries as $i => $entry) {
            $sheet->setCellValue('A' . $row, $i + 1);
            $sheet->setCellValue('B' . $row, $entry->employee->department);
            $sheet->setCellValue('C' . $row, $entry->employee->name);
            $sheet->setCellValue('D' . $row, $entry->employee->daily_rate);
            $sheet->setCellValue('E' . $row, $entry->days_present);
            $sheet->setCellValue('F' . $row, $entry->total_basic_pay);
            $sheet->setCellValue('G' . $row, $entry->holiday_pay);
            $sheet->setCellValue('H' . $row, $entry->overtime_pay);
            $sheet->setCellValue('I' . $row, $entry->late_deduction + $entry->undertime_deduction);
            $sheet->setCellValue('J' . $row, $entry->gross_pay);
            $sheet->setCellValue('K' . $row, $entry->cash_advance);
            $sheet->setCellValue('L' . $row, $entry->other_deductions);
            $sheet->setCellValue('M' . $row, $entry->total_deductions);
            $sheet->setCellValue('N' . $row, $entry->first_release);
            $sheet->setCellValue('O' . $row, $entry->second_release);
            $balance = $entry->net_pay - $entry->first_release - $entry->second_release;
            $sheet->setCellValue('P' . $row, $balance);
            $sheet->setCellValue('Q' . $row, $entry->net_pay);
            $row++;
        }

        $path = sys_get_temp_dir() . "/payroll-summary-{$run->id}.xlsx";
        (new Xlsx($spreadsheet))->save($path);
        return $path;
    }
}

<?php
namespace App\Services;

use App\Models\PayrollRun;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PayrollExportService
{
    private const NAVY       = '1F4E79';
    private const NAVY_LIGHT = 'BDD7EE';
    private const ROW_ALT    = 'EBF3FB';
    private const BORDER     = 'B8CCE4';
    private const WHITE      = 'FFFFFF';
    private const MONEY_FMT  = '#,##0.00';

    public function export(PayrollRun $run): string
    {
        $run->load('entries.employee');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payroll Summary');

        $lastCol    = 'R';
        $headerRow  = 5;
        $dataStart  = 6;

        $this->buildTitleSection($sheet, $run, $lastCol);
        $this->buildColumnHeaders($sheet, $headerRow, $lastCol);
        $lastDataRow = $this->buildDataRows($sheet, $run, $dataStart);
        $totalRow    = $this->buildTotalsRow($sheet, $dataStart, $lastDataRow, $lastCol);
        $this->buildSignoffSection($sheet, $totalRow + 3, $lastCol);
        $this->applyPrintSettings($sheet, $headerRow, $lastCol, $totalRow + 7);

        $path = sys_get_temp_dir() . "/payroll-summary-{$run->id}.xlsx";
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function buildTitleSection($sheet, $run, string $lastCol): void
    {
        // Row 1 – main title
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'PAYROLL SUMMARY');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 18, 'color' => ['rgb' => self::NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(32);

        // Row 2 – period covered
        $period = strtoupper(
            "Period Covered: {$run->period_start->format('F d')} – {$run->period_end->format('F d, Y')}"
        );
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2', $period);
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '2F5496']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        // Row 3 – payable date
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->setCellValue('A3', "Payable Date: {$run->payable_date->format('F d, Y')}");
        $sheet->getStyle('A3')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '595959']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(17);

        // Row 4 – divider line
        $sheet->mergeCells("A4:{$lastCol}4");
        $sheet->getStyle("A4:{$lastCol}4")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY]],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(4);
    }

    private function buildColumnHeaders($sheet, int $headerRow, string $lastCol): void
    {
        $columns = $this->columnDefinitions();

        foreach ($columns as $col => [$label, $width]) {
            $sheet->setCellValue("{$col}{$headerRow}", $label);
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font'      => [
                'bold' => true, 'size' => 9,
                'color' => ['rgb' => self::WHITE],
            ],
            'fill'      => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::NAVY],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders'   => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '4472C4']],
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(38);
    }

    private function buildDataRows($sheet, $run, int $dataStart): int
    {
        $row = $dataStart;

        foreach ($run->entries as $i => $entry) {
            $bg = ($i % 2 === 1) ? self::ROW_ALT : self::WHITE;

            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $entry->employee->department ?? '');
            $sheet->setCellValue("C{$row}", $entry->employee->name);
            $sheet->setCellValue("D{$row}", $entry->employee->daily_rate);
            $sheet->setCellValue("E{$row}", $entry->days_present);
            $sheet->setCellValue("F{$row}", $entry->total_basic_pay);
            $sheet->setCellValue("G{$row}", $entry->holiday_pay);
            $sheet->setCellValue("H{$row}", $entry->overtime_pay);
            $sheet->setCellValue("I{$row}", $entry->late_deduction + $entry->undertime_deduction);
            $sheet->setCellValue("J{$row}", $entry->gross_pay);
            $sheet->setCellValue("K{$row}", $entry->cash_advance);
            $sheet->setCellValue("L{$row}", $entry->other_deductions);
            $sheet->setCellValue("M{$row}", $entry->total_deductions);
            $sheet->setCellValue("N{$row}", $entry->first_release);
            $sheet->setCellValue("O{$row}", $entry->second_release);
            $sheet->setCellValue("P{$row}", $entry->balance);
            $sheet->setCellValue("Q{$row}", $entry->net_pay);
            // Column R left blank for signature

            // Base row style
            $sheet->getStyle("A{$row}:R{$row}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER]],
                ],
            ]);

            // Currency format
            foreach (['D', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q'] as $c) {
                $sheet->getStyle("{$c}{$row}")->getNumberFormat()->setFormatCode(self::MONEY_FMT);
            }

            // Alignment overrides
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Signature cell – bottom border as signing line
            $sheet->getStyle("R{$row}")->applyFromArray([
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'borders' => [
                    'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '8496B0']],
                    'left'   => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => self::BORDER]],
                    'right'  => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => self::BORDER]],
                    'top'    => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => self::BORDER]],
                ],
            ]);

            $sheet->getRowDimension($row)->setRowHeight(22);
            $row++;
        }

        return $row - 1;
    }

    private function buildTotalsRow($sheet, int $dataStart, int $lastDataRow, string $lastCol): int
    {
        $totalRow = $lastDataRow + 1;

        $sheet->mergeCells("A{$totalRow}:C{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'TOTAL');

        foreach (['E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q'] as $c) {
            $sheet->setCellValue("{$c}{$totalRow}", "=SUM({$c}{$dataStart}:{$c}{$lastDataRow})");
            $sheet->getStyle("{$c}{$totalRow}")->getNumberFormat()->setFormatCode(self::MONEY_FMT);
        }
        $sheet->getStyle("E{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');

        $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::NAVY]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::NAVY_LIGHT]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => self::BORDER]],
                'top'        => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::NAVY]],
                'bottom'     => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::NAVY]],
            ],
        ]);
        $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getRowDimension($totalRow)->setRowHeight(22);

        return $totalRow;
    }

    private function buildSignoffSection($sheet, int $startRow, string $lastCol): void
    {
        $labelRow = $startRow;
        $lineRow  = $startRow + 3;

        // Three signatory boxes: Prepared / Checked / Approved
        $boxes = [
            ['A', 'F', 'Prepared by:'],
            ['G', 'L', 'Checked by:'],
            ['M', 'R', 'Approved by:'],
        ];

        foreach ($boxes as [$from, $to, $label]) {
            $sheet->mergeCells("{$from}{$labelRow}:{$to}{$labelRow}");
            $sheet->setCellValue("{$from}{$labelRow}", $label);
            $sheet->getStyle("{$from}{$labelRow}")->applyFromArray([
                'font'      => ['bold' => false, 'size' => 9, 'color' => ['rgb' => '595959']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);

            $sheet->mergeCells("{$from}{$lineRow}:{$to}{$lineRow}");
            $sheet->getStyle("{$from}{$lineRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders'   => [
                    'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::NAVY]],
                ],
            ]);

            $nameRow = $lineRow + 1;
            $sheet->mergeCells("{$from}{$nameRow}:{$to}{$nameRow}");
            $sheet->getStyle("{$from}{$nameRow}")->applyFromArray([
                'font'      => ['size' => 8, 'italic' => true, 'color' => ['rgb' => '808080']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->setCellValue("{$from}{$nameRow}", 'Name / Signature / Date');

            $sheet->getRowDimension($lineRow)->setRowHeight(24);
        }
    }

    private function applyPrintSettings($sheet, int $headerRow, string $lastCol, int $lastRow): void
    {
        $setup = $sheet->getPageSetup();
        $setup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $setup->setPaperSize(PageSetup::PAPERSIZE_LEGAL);
        $setup->setFitToPage(true);
        $setup->setFitToWidth(1);
        $setup->setFitToHeight(0);
        $setup->setRowsToRepeatAtTopByStartAndEnd(1, $headerRow);

        $sheet->getPageMargins()
            ->setTop(0.5)->setBottom(0.5)
            ->setLeft(0.4)->setRight(0.4)
            ->setHeader(0.2)->setFooter(0.2);

        $sheet->getHeaderFooter()
            ->setOddHeader("&C&\"Calibri,Bold\"&12PAYROLL SUMMARY")
            ->setOddFooter('&LConfidential&CPage &P of &N&RGenerated: &D');

        $sheet->setPrintGridlines(false);
        $sheet->setShowGridlines(true);
        $sheet->freezePane("A{$headerRow}");
        $sheet->getPageSetup()->setPrintArea("A1:{$lastCol}{$lastRow}");
    }

    private function columnDefinitions(): array
    {
        return [
            'A' => ['#',                          5],
            'B' => ['Department',                13],
            'C' => ["Employee's Name",           20],
            'D' => ['Daily Rate',                11],
            'E' => ['Working Days',              10],
            'F' => ['Basic Pay',                 12],
            'G' => ['Holiday Pay',               11],
            'H' => ['Overtime Pay',              11],
            'I' => ["Absences /\nLate & Undertime", 13],
            'J' => ['GROSS PAY',                 12],
            'K' => ['Cash Advance',              12],
            'L' => ['Other Deductions',          13],
            'M' => ['Total Deduction',           13],
            'N' => ['1st Release',               12],
            'O' => ['2nd Release',               12],
            'P' => ['BALANCE',                   12],
            'Q' => ['NET PAY',                   12],
            'R' => ['SIGNATURE',                 22],
        ];
    }
}

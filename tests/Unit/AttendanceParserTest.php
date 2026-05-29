<?php
namespace Tests\Unit;

use App\Services\AttendanceParser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AttendanceParserTest extends TestCase
{
    /**
     * Build a fixture that matches the parser's expected format:
     * - Row 4: date headers (one column per date, format "YYYY/MM/DD")
     * - Row 5: SW/EW label row (ignored by parser)
     * - Row 6+: one employee per row; attendance cell = "HH:MM HH:MM" or absent marker
     */
    private function buildFixture(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        // Row 3: metadata
        $sheet->setCellValue('A3', 'Create Time:2026-05-18  SW:Start-Work|EW:End-');

        // Row 4: column headers
        $sheet->setCellValue('A4', 'Employee ID');
        $sheet->setCellValue('B4', 'Card No.');
        $sheet->setCellValue('C4', 'Name');
        $sheet->setCellValue('D4', 'Department');
        $sheet->setCellValue('E4', '2026/05/01');
        $sheet->setCellValue('F4', '2026/05/02');

        // Row 5: SW/EW sub-headers (parser skips this row)
        $sheet->setCellValue('E5', 'SW EW');
        $sheet->setCellValue('F5', 'SW EW');

        // Employee 1: DELA CRUZ, JUAN — row 6
        $sheet->setCellValue('A6', '1');
        $sheet->setCellValue('C6', 'DELA CRUZ, JUAN');
        $sheet->setCellValue('D6', 'ADMIN');
        $sheet->setCellValue('E6', '08:05 17:10'); // present: late 5m, OT 10m
        $sheet->setCellValue('F6', '----');         // absent

        // Employee 2: SANTOS, MARIA — row 7
        $sheet->setCellValue('A7', '2');
        $sheet->setCellValue('C7', 'SANTOS, MARIA');
        $sheet->setCellValue('D7', 'BHAGOH');
        $sheet->setCellValue('E7', '08:00 16:50'); // present: undertime 10m
        $sheet->setCellValue('F7', '08:00 17:00'); // present: on time

        $path = sys_get_temp_dir() . '/test_attendance.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        return $path;
    }

    public function test_parses_employee_names_and_departments(): void
    {
        $result = (new AttendanceParser())->parse($this->buildFixture());

        $this->assertCount(2, $result);
        $this->assertEquals('DELA CRUZ, JUAN', $result[0]['name']);
        $this->assertEquals('ADMIN', $result[0]['department']);
        $this->assertEquals('SANTOS, MARIA', $result[1]['name']);
    }

    public function test_parses_present_day_times(): void
    {
        $result = (new AttendanceParser())->parse($this->buildFixture());

        $day1 = $result[0]['attendance']['2026-05-01'];
        $this->assertEquals('08:05', $day1['sw']);
        $this->assertEquals('17:10', $day1['ew']);
    }

    public function test_marks_absent_days_as_null(): void
    {
        $result = (new AttendanceParser())->parse($this->buildFixture());

        $day2 = $result[0]['attendance']['2026-05-02'];
        $this->assertNull($day2['sw']);
        $this->assertNull($day2['ew']);
    }

    public function test_throws_on_unreadable_file(): void
    {
        $this->expectException(\RuntimeException::class);
        (new AttendanceParser())->parse('/nonexistent/file.xlsx');
    }
}

<?php
namespace Tests\Unit;

use App\Services\AttendanceParser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AttendanceParserTest extends TestCase
{
    private function buildFixture(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Row 3: metadata
        $sheet->setCellValue('A3', 'Create Time:2026-05-18  SW:Start-Work|EW:End-');

        // Row 4: column headers
        $sheet->setCellValue('A4', 'Employee ID');
        $sheet->setCellValue('B4', 'Card No.');
        $sheet->setCellValue('C4', 'Name');
        $sheet->setCellValue('D4', 'Department');
        $sheet->setCellValue('E4', '2026/05/01');
        // merge E4:F4 to represent date spanning SW+EW cols
        $sheet->mergeCells('E4:F4');
        $sheet->setCellValue('G4', '2026/05/02');
        $sheet->mergeCells('G4:H4');

        // Row 5: SW/EW sub-headers
        $sheet->setCellValue('E5', 'SW');
        $sheet->setCellValue('F5', 'EW');
        $sheet->setCellValue('G5', 'SW');
        $sheet->setCellValue('H5', 'EW');

        // Employee 1: DELA CRUZ, JUAN — rows 6-7
        $sheet->setCellValue('A6', '1');
        $sheet->setCellValue('C6', 'DELA CRUZ, JUAN');
        $sheet->setCellValue('D6', 'ADMIN');
        // Day 1: present
        $sheet->setCellValue('E6', '08:05');  // late by 5 min (shift 08:00)
        $sheet->setCellValue('F6', '17:10');  // overtime 10 min
        // Day 2: absent (SW is dashes)
        $sheet->setCellValue('G6', '----');
        $sheet->setCellValue('H7', '----');

        // Employee 2: SANTOS, MARIA — rows 8-9
        $sheet->setCellValue('A8', '2');
        $sheet->setCellValue('C8', 'SANTOS, MARIA');
        $sheet->setCellValue('D8', 'BHAGOH');
        $sheet->setCellValue('E8', '08:00');
        $sheet->setCellValue('F8', '16:50');  // undertime 10 min
        $sheet->setCellValue('G8', '08:00');
        $sheet->setCellValue('H8', '17:00');

        $path = sys_get_temp_dir() . '/test_attendance.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        return $path;
    }

    public function test_parses_employee_names_and_departments(): void
    {
        $path = $this->buildFixture();
        $result = (new AttendanceParser())->parse($path);

        $this->assertCount(2, $result);
        $this->assertEquals('DELA CRUZ, JUAN', $result[0]['name']);
        $this->assertEquals('ADMIN', $result[0]['department']);
        $this->assertEquals('SANTOS, MARIA', $result[1]['name']);
    }

    public function test_parses_present_day_times(): void
    {
        $path = $this->buildFixture();
        $result = (new AttendanceParser())->parse($path);

        $day1 = $result[0]['attendance']['2026-05-01'];
        $this->assertEquals('08:05', $day1['sw']);
        $this->assertEquals('17:10', $day1['ew']);
    }

    public function test_marks_absent_days_as_null(): void
    {
        $path = $this->buildFixture();
        $result = (new AttendanceParser())->parse($path);

        $day2 = $result[0]['attendance']['2026-05-02'];
        $this->assertNull($day2['sw']);
        $this->assertNull($day2['ew']);
    }
}

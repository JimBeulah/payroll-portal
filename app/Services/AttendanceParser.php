<?php
namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class AttendanceParser
{
    public function parse(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $dateColumns = $this->detectDateColumns($sheet);
        return $this->parseEmployees($sheet, $dateColumns);
    }

    private function detectDateColumns($sheet): array
    {
        $columns = [];
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        $col = 5; // E = 5, start after Employee ID, Card No., Name, Department
        while ($col <= $maxCol) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $dateRaw = $sheet->getCell($letter . '4')->getValue();

            if (empty($dateRaw)) {
                $col++;
                continue;
            }

            // Normalize: "2026/05/01" → "2026-05-01"
            $dateKey = str_replace('/', '-', trim((string) $dateRaw));
            $ewLetter = Coordinate::stringFromColumnIndex($col + 1);

            $columns[$dateKey] = ['sw' => $letter, 'ew' => $ewLetter];
            $col += 2; // skip EW column
        }

        return $columns;
    }

    private function parseEmployees($sheet, array $dateColumns): array
    {
        $employees = [];
        $maxRow = (int) $sheet->getHighestRow();

        $row = 6;
        while ($row <= $maxRow) {
            $name = $sheet->getCell('C' . $row)->getValue();

            if (empty($name)) {
                $row++;
                continue;
            }

            $department = $sheet->getCell('D' . $row)->getValue();
            $attendance = [];

            foreach ($dateColumns as $date => ['sw' => $swCol, 'ew' => $ewCol]) {
                $swRaw = $sheet->getCell($swCol . $row)->getValue();
                // EW may be on same row or next row (merged cell variant)
                $ewRaw = $sheet->getCell($ewCol . $row)->getValue()
                    ?: $sheet->getCell($ewCol . ($row + 1))->getValue();

                $attendance[$date] = [
                    'sw' => $this->parseTime($swRaw),
                    'ew' => $this->parseTime($ewRaw),
                ];
            }

            $employees[] = [
                'name' => trim((string) $name),
                'department' => trim((string) $department),
                'attendance' => $attendance,
            ];

            $row += 2; // each employee occupies 2 rows
        }

        return $employees;
    }

    private function parseTime(mixed $value): ?string
    {
        if (empty($value)) return null;

        $str = trim((string) $value);

        // Absent indicators: dashes, x, dots
        if (preg_match('/^[-x.]+$/i', $str) || $str === '') return null;

        // Already HH:MM string format
        if (preg_match('/^\d{1,2}:\d{2}$/', $str)) return $str;

        // Excel numeric time (fraction of 24h, e.g., 0.333 = 08:00)
        if (is_numeric($value)) {
            $totalMinutes = (int) round((float) $value * 24 * 60);
            return sprintf('%02d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60);
        }

        return null;
    }
}

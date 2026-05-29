<?php
namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetException;

class AttendanceParser
{
    /**
     * @throws \RuntimeException if the file cannot be read or parsed
     */
    public function parse(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (SpreadsheetException $e) {
            throw new \RuntimeException("Could not read attendance file: {$e->getMessage()}", 0, $e);
        }

        $sheet = $spreadsheet->getActiveSheet();

        $dateColumns = $this->detectDateColumns($sheet);
        return $this->parseEmployees($sheet, $dateColumns);
    }

    private function detectDateColumns($sheet): array
    {
        $columns = [];
        $maxCol  = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        // Start at column E (5) — past Employee ID, Card No., Name, Department
        for ($col = 5; $col <= $maxCol; $col++) {
            $letter  = Coordinate::stringFromColumnIndex($col);
            $dateRaw = $sheet->getCell($letter . '4')->getValue();

            if (empty($dateRaw)) {
                continue;
            }

            // Normalize "2026/05/01" → "2026-05-01"
            $dateKey           = str_replace('/', '-', trim((string) $dateRaw));
            $columns[$dateKey] = $letter;
        }

        return $columns;
    }

    private function parseEmployees($sheet, array $dateColumns): array
    {
        $employees = [];
        $maxRow    = (int) $sheet->getHighestRow();

        // Data starts at row 6; each employee occupies exactly ONE row
        for ($row = 6; $row <= $maxRow; $row++) {
            $name = trim((string) $sheet->getCell('C' . $row)->getValue());

            if ($name === '') {
                continue;
            }

            $department = trim((string) $sheet->getCell('D' . $row)->getValue());
            $attendance = [];

            foreach ($dateColumns as $date => $col) {
                $raw       = $sheet->getCell($col . $row)->getValue();
                // Cell may contain multiple newline-separated entries; first line is the one we want
                $firstLine = trim(explode("\n", (string) $raw)[0]);

                // Format: "HH:MM HH:MM" — both SW and EW on the same line
                if (preg_match('/^(\d{1,2}:\d{2})\s+(\d{1,2}:\d{2})$/', $firstLine, $m)) {
                    $attendance[$date] = ['sw' => $m[1], 'ew' => $m[2]];
                } else {
                    // Absent / dashes / empty
                    $attendance[$date] = ['sw' => null, 'ew' => null];
                }
            }

            $employees[] = [
                'name'       => $name,
                'department' => $department,
                'attendance' => $attendance,
            ];
        }

        return $employees;
    }
}

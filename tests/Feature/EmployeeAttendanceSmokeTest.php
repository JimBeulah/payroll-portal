<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollEntry;
use App\Models\PayrollManualAttendance;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class EmployeeAttendanceSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_view_their_own_attendance_calendar(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create(['role' => 'employee']);
        $employee->update(['user_id' => $user->id]);

        $run = PayrollRun::factory()->create([
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-15',
        ]);
        PayrollEntry::factory()->create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id]);

        PayrollManualAttendance::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'date' => '2026-06-05',
            'sw' => '08:15',
            'ew' => '17:00',
            'shift_start' => $employee->shift_start,
            'shift_end' => $employee->shift_end,
            'note' => 'secret HR note',
            'is_override' => true,
        ]);

        Holiday::create(['name' => 'Independence Day', 'date' => '2026-06-12', 'type' => 'regular']);

        $response = $this->actingAs($user)->get('/my-attendance');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('attendance/index')
            ->where('selectedRun.id', $run->id)
            ->where('holidays.0.name', 'Independence Day')
            ->where('manualAttendances.0.is_override', true)
            ->missing('manualAttendances.0.note')
        );
    }

    public function test_employee_without_linked_record_is_forbidden(): void
    {
        $user = User::factory()->create(['role' => 'employee']);

        $this->actingAs($user)->get('/my-attendance')->assertForbidden();
    }

    public function test_employee_cannot_view_a_run_they_were_not_part_of(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->create(['role' => 'employee']);
        $employee->update(['user_id' => $user->id]);

        $ownRun = PayrollRun::factory()->create(['period_start' => '2026-06-01', 'period_end' => '2026-06-15']);
        PayrollEntry::factory()->create(['payroll_run_id' => $ownRun->id, 'employee_id' => $employee->id]);

        $otherRun = PayrollRun::factory()->create(['period_start' => '2026-05-01', 'period_end' => '2026-05-15']);

        $this->actingAs($user)->get('/my-attendance?run='.$otherRun->id)->assertNotFound();
    }

    public function test_employee_sees_attendance_parsed_from_the_uploaded_excel_file(): void
    {
        $employee = Employee::factory()->create([
            'name' => 'DELA CRUZ, JUAN',
            'department' => 'ADMIN',
            'shift_start' => '08:00:00',
            'shift_end' => '17:00:00',
        ]);
        $user = User::factory()->create(['role' => 'employee']);
        $employee->update(['user_id' => $user->id]);

        $admin = User::factory()->admin()->create();
        $run = PayrollRun::factory()->create([
            'created_by' => $admin->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-15',
        ]);
        PayrollEntry::factory()->create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id]);

        $this->actingAs($admin)
            ->post("/payroll-runs/{$run->id}/upload", ['file' => $this->buildAttendanceFile('DELA CRUZ, JUAN', 'ADMIN')])
            ->assertRedirect();

        // This is the crux of the storage fix: the file is read back via Storage::get()
        // (disk-agnostic) into a temp file for parsing, not assumed to be on local disk already.
        $response = $this->actingAs($user)->get('/my-attendance');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('attendance/index')
            ->where('attendanceData.2026-05-01.sw', '08:00')
            ->where('attendanceData.2026-05-01.ew', '17:00')
        );
    }

    private function buildAttendanceFile(string $name, string $dept): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A3', 'Create Time:2026-05-01');
        $sheet->setCellValue('A4', 'Employee ID');
        $sheet->setCellValue('C4', 'Name');
        $sheet->setCellValue('D4', 'Department');
        $sheet->setCellValue('E4', '2026/05/01');
        $sheet->mergeCells('E4:F4');
        $sheet->setCellValue('E5', 'SW');
        $sheet->setCellValue('F5', 'EW');
        $sheet->setCellValue('A6', '1');
        $sheet->setCellValue('C6', $name);
        $sheet->setCellValue('D6', $dept);
        $sheet->setCellValue('E6', '08:00 17:00');

        $path = sys_get_temp_dir().'/employee_attendance_test_upload.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'attendance.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}

<?php
namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PayrollRunTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_can_create_payroll_run(): void
    {
        $this->actingAs($this->admin())->post('/payroll-runs', [
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-15',
            'payable_date' => '2026-05-20',
        ])->assertRedirect();

        $this->assertDatabaseCount('payroll_runs', 1);
        $this->assertTrue(PayrollRun::whereDate('period_start', '2026-05-01')->exists());
    }

    public function test_can_upload_attendance_and_compute(): void
    {
        $employee = Employee::factory()->create([
            'name' => 'DELA CRUZ, JUAN',
            'department' => 'ADMIN',
            'daily_rate' => 480.00,
            'shift_start' => '08:00:00',
            'shift_end' => '17:00:00',
        ]);

        $run = PayrollRun::factory()->create(['created_by' => $this->admin()->id]);

        $file = $this->buildAttendanceFile('DELA CRUZ, JUAN', 'ADMIN');

        $this->actingAs(User::find($run->created_by))
            ->post("/payroll-runs/{$run->id}/upload", ['file' => $file])
            ->assertRedirect();

        $this->actingAs(User::find($run->created_by))
            ->post("/payroll-runs/{$run->id}/compute")
            ->assertRedirect();

        $this->assertDatabaseHas('payroll_entries', [
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_can_lock_payroll_run(): void
    {
        $run = PayrollRun::factory()->create(['created_by' => $this->admin()->id]);
        $this->actingAs(User::find($run->created_by))
            ->post("/payroll-runs/{$run->id}/lock")
            ->assertRedirect();

        $this->assertDatabaseHas('payroll_runs', ['id' => $run->id, 'status' => 'locked']);
    }

    public function test_cannot_recompute_locked_run(): void
    {
        $run = PayrollRun::factory()->create([
            'created_by' => $this->admin()->id,
            'status' => 'locked',
        ]);

        $this->actingAs(User::find($run->created_by))
            ->post("/payroll-runs/{$run->id}/compute")
            ->assertForbidden();
    }

    public function test_can_update_entry_deductions(): void
    {
        $run = PayrollRun::factory()->create(['created_by' => $this->admin()->id]);
        $employee = Employee::factory()->create();
        $entry = \App\Models\PayrollEntry::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'days_present' => 1,
            'total_basic_pay' => 480,
            'overtime_minutes' => 0, 'overtime_pay' => 0,
            'late_minutes' => 0, 'late_deduction' => 0,
            'undertime_minutes' => 0, 'undertime_deduction' => 0,
            'holiday_pay' => 0, 'gross_pay' => 480,
            'cash_advance' => 0, 'other_deductions' => 0,
            'total_deductions' => 0, 'net_pay' => 480,
            'first_release' => 0, 'second_release' => 0,
        ]);

        $this->actingAs(User::find($run->created_by))
            ->put("/payroll-entries/{$entry->id}", [
                'cash_advance' => 100,
                'other_deductions' => 50,
                'first_release' => 165,
                'second_release' => 165,
            ])->assertRedirect();

        $this->assertDatabaseHas('payroll_entries', [
            'id' => $entry->id,
            'cash_advance' => 100,
            'net_pay' => 330, // 480 - 150
        ]);
    }

    private function buildAttendanceFile(string $name, string $dept): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A3', 'Create Time:2026-05-18');
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
        $sheet->setCellValue('E6', '08:00');
        $sheet->setCellValue('F6', '17:00');

        $path = sys_get_temp_dir() . '/test_upload.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        return new UploadedFile($path, 'attendance.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}

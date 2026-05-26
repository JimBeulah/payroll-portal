# Payroll System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a semi-monthly payroll system with attendance Excel upload, automatic pay computation, manual deduction entry, and PDF payslip generation for HR/Admin use only.

**Architecture:** Laravel 12 backend with Inertia.js + React/TypeScript frontend. A dedicated `AttendanceParser` service reads the biometric DTR Excel format; a `PayrollCalculator` service computes pay to the minute. Payroll runs persist as `draft` until HR locks them, after which PDF payslips are generated on demand.

**Tech Stack:** Laravel 12, Inertia.js, React 18, TypeScript, shadcn/ui, `phpoffice/phpspreadsheet` (Excel parse/export), `barryvdh/laravel-dompdf` (PDF), Carbon (time arithmetic), PHPUnit, SQLite

---

## File Map

**Migrations**
- `database/migrations/xxxx_create_employees_table.php`
- `database/migrations/xxxx_create_holidays_table.php`
- `database/migrations/xxxx_create_payroll_runs_table.php`
- `database/migrations/xxxx_create_payroll_entries_table.php`
- `database/migrations/xxxx_create_attendance_uploads_table.php`

**Models + Factories**
- `app/Models/Employee.php`
- `app/Models/Holiday.php`
- `app/Models/PayrollRun.php`
- `app/Models/PayrollEntry.php`
- `app/Models/AttendanceUpload.php`
- `database/factories/EmployeeFactory.php`
- `database/factories/PayrollRunFactory.php`

**Services**
- `app/Services/AttendanceParser.php`
- `app/Services/PayrollCalculator.php`
- `app/Services/PayslipPdfGenerator.php`
- `app/Services/PayrollExportService.php`

**Controllers + Requests**
- `app/Http/Controllers/EmployeeController.php`
- `app/Http/Controllers/HolidayController.php`
- `app/Http/Controllers/PayrollRunController.php`
- `app/Http/Controllers/PayrollEntryController.php`
- `app/Http/Controllers/PayslipController.php`
- `app/Http/Requests/StoreEmployeeRequest.php`
- `app/Http/Requests/UpdateEmployeeRequest.php`
- `app/Http/Requests/StoreHolidayRequest.php`
- `app/Http/Requests/UpdateHolidayRequest.php`
- `app/Http/Requests/StorePayrollRunRequest.php`

**Views**
- `resources/views/payslip.blade.php`

**Frontend Pages**
- `resources/js/pages/employees/index.tsx`
- `resources/js/pages/employees/create.tsx`
- `resources/js/pages/employees/edit.tsx`
- `resources/js/pages/holidays/index.tsx`
- `resources/js/pages/holidays/create.tsx`
- `resources/js/pages/holidays/edit.tsx`
- `resources/js/pages/payroll/index.tsx`
- `resources/js/pages/payroll/create.tsx`
- `resources/js/pages/payroll/show.tsx`

**Frontend Components**
- `resources/js/components/payroll/attendance-preview-table.tsx`
- `resources/js/components/payroll/payroll-summary-table.tsx`
- `resources/js/components/payroll/deduction-sheet.tsx`

**Tests**
- `tests/Unit/AttendanceParserTest.php`
- `tests/Unit/PayrollCalculatorTest.php`
- `tests/Feature/EmployeeTest.php`
- `tests/Feature/HolidayTest.php`
- `tests/Feature/PayrollRunTest.php`
- `tests/Feature/PayslipTest.php`

**Modified**
- `routes/web.php`
- `resources/js/components/app-sidebar.tsx`

---

## Task 1: Install Dependencies

**Files:** `composer.json` (modified via composer)

- [ ] **Step 1: Install PHP packages**

```bash
composer require phpoffice/phpspreadsheet barryvdh/laravel-dompdf
```

Expected: both packages added to `vendor/`, no errors.

- [ ] **Step 2: Publish DomPDF config**

```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

Expected: `config/dompdf.php` created.

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock config/dompdf.php
git commit -m "chore: install phpspreadsheet and dompdf"
```

---

## Task 2: Database Migrations

**Files:** 5 new migration files

- [ ] **Step 1: Generate migration files**

```bash
php artisan make:migration create_employees_table
php artisan make:migration create_holidays_table
php artisan make:migration create_payroll_runs_table
php artisan make:migration create_payroll_entries_table
php artisan make:migration create_attendance_uploads_table
```

- [ ] **Step 2: Write employees migration**

In `database/migrations/xxxx_create_employees_table.php`:

```php
public function up(): void
{
    Schema::create('employees', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('department');
        $table->decimal('daily_rate', 10, 2);
        $table->time('shift_start');
        $table->time('shift_end');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
}
```

- [ ] **Step 3: Write holidays migration**

```php
public function up(): void
{
    Schema::create('holidays', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->date('date');
        $table->enum('type', ['regular', 'special']);
        $table->timestamps();
    });
}
```

- [ ] **Step 4: Write payroll_runs migration**

```php
public function up(): void
{
    Schema::create('payroll_runs', function (Blueprint $table) {
        $table->id();
        $table->date('period_start');
        $table->date('period_end');
        $table->date('payable_date');
        $table->enum('status', ['draft', 'locked'])->default('draft');
        $table->foreignId('created_by')->constrained('users');
        $table->timestamps();
    });
}
```

- [ ] **Step 5: Write payroll_entries migration**

```php
public function up(): void
{
    Schema::create('payroll_entries', function (Blueprint $table) {
        $table->id();
        $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
        $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
        $table->integer('days_present')->default(0);
        $table->decimal('total_basic_pay', 10, 2)->default(0);
        $table->integer('overtime_minutes')->default(0);
        $table->decimal('overtime_pay', 10, 2)->default(0);
        $table->integer('late_minutes')->default(0);
        $table->decimal('late_deduction', 10, 2)->default(0);
        $table->integer('undertime_minutes')->default(0);
        $table->decimal('undertime_deduction', 10, 2)->default(0);
        $table->decimal('holiday_pay', 10, 2)->default(0);
        $table->decimal('gross_pay', 10, 2)->default(0);
        $table->decimal('cash_advance', 10, 2)->default(0);
        $table->decimal('other_deductions', 10, 2)->default(0);
        $table->decimal('total_deductions', 10, 2)->default(0);
        $table->decimal('net_pay', 10, 2)->default(0);
        $table->decimal('first_release', 10, 2)->default(0);
        $table->decimal('second_release', 10, 2)->default(0);
        $table->timestamps();
    });
}
```

- [ ] **Step 6: Write attendance_uploads migration**

```php
public function up(): void
{
    Schema::create('attendance_uploads', function (Blueprint $table) {
        $table->id();
        $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
        $table->string('filename');
        $table->timestamp('uploaded_at');
        $table->timestamps();
    });
}
```

- [ ] **Step 7: Run migrations**

```bash
php artisan migrate
```

Expected: all 5 tables created, no errors.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/
git commit -m "feat: add payroll system migrations"
```

---

## Task 3: Models and Factories

**Files:** 5 models, 2 factories

- [ ] **Step 1: Create models**

```bash
php artisan make:model Employee
php artisan make:model Holiday
php artisan make:model PayrollRun
php artisan make:model PayrollEntry
php artisan make:model AttendanceUpload
```

- [ ] **Step 2: Write Employee model**

`app/Models/Employee.php`:

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'department', 'daily_rate', 'shift_start', 'shift_end', 'is_active',
    ];

    protected $casts = [
        'daily_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function payrollEntries()
    {
        return $this->hasMany(PayrollEntry::class);
    }
}
```

- [ ] **Step 3: Write Holiday model**

`app/Models/Holiday.php`:

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['name', 'date', 'type'];

    protected $casts = ['date' => 'date'];
}
```

- [ ] **Step 4: Write PayrollRun model**

`app/Models/PayrollRun.php`:

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    use HasFactory;

    protected $fillable = ['period_start', 'period_end', 'payable_date', 'status', 'created_by'];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'payable_date' => 'date',
    ];

    public function entries()
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function uploads()
    {
        return $this->hasMany(AttendanceUpload::class);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }
}
```

- [ ] **Step 5: Write PayrollEntry model**

`app/Models/PayrollEntry.php`:

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollEntry extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'days_present', 'total_basic_pay',
        'overtime_minutes', 'overtime_pay', 'late_minutes', 'late_deduction',
        'undertime_minutes', 'undertime_deduction', 'holiday_pay', 'gross_pay',
        'cash_advance', 'other_deductions', 'total_deductions', 'net_pay',
        'first_release', 'second_release',
    ];

    protected $casts = [
        'total_basic_pay' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'late_deduction' => 'decimal:2',
        'undertime_deduction' => 'decimal:2',
        'holiday_pay' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'cash_advance' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'first_release' => 'decimal:2',
        'second_release' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->net_pay - (float) $this->first_release - (float) $this->second_release;
    }
}
```

- [ ] **Step 6: Write AttendanceUpload model**

`app/Models/AttendanceUpload.php`:

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceUpload extends Model
{
    protected $fillable = ['payroll_run_id', 'filename', 'uploaded_at'];

    protected $casts = ['uploaded_at' => 'datetime'];
}
```

- [ ] **Step 7: Write EmployeeFactory**

`database/factories/EmployeeFactory.php`:

```php
<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'department' => $this->faker->randomElement(['ADMIN', 'BHAGOH', 'BFAITH']),
            'daily_rate' => $this->faker->randomElement([510, 550, 600, 700, 900]),
            'shift_start' => '08:00:00',
            'shift_end' => '17:00:00',
            'is_active' => true,
        ];
    }
}
```

- [ ] **Step 8: Write PayrollRunFactory**

`database/factories/PayrollRunFactory.php`:

```php
<?php
namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-15',
            'payable_date' => '2026-05-20',
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }
}
```

- [ ] **Step 9: Commit**

```bash
git add app/Models/ database/factories/
git commit -m "feat: add payroll models and factories"
```

---

## Task 4: Employee CRUD Backend

**Files:** `EmployeeController.php`, `StoreEmployeeRequest.php`, `UpdateEmployeeRequest.php`, `routes/web.php`, `tests/Feature/EmployeeTest.php`

- [ ] **Step 1: Write the failing feature test**

`tests/Feature/EmployeeTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    public function test_can_list_employees(): void
    {
        Employee::factory()->count(3)->create();
        $response = $this->actingAsAdmin()->get('/employees');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees', 3)
        );
    }

    public function test_can_create_employee(): void
    {
        $response = $this->actingAsAdmin()->post('/employees', [
            'name' => 'Juan Dela Cruz',
            'department' => 'ADMIN',
            'daily_rate' => 550.00,
            'shift_start' => '08:00',
            'shift_end' => '17:00',
        ]);
        $response->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', ['name' => 'Juan Dela Cruz']);
    }

    public function test_can_update_employee(): void
    {
        $employee = Employee::factory()->create();
        $response = $this->actingAsAdmin()->put("/employees/{$employee->id}", [
            'name' => 'Updated Name',
            'department' => 'ADMIN',
            'daily_rate' => 600.00,
            'shift_start' => '08:00',
            'shift_end' => '17:00',
        ]);
        $response->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'name' => 'Updated Name']);
    }

    public function test_can_delete_employee(): void
    {
        $employee = Employee::factory()->create();
        $response = $this->actingAsAdmin()->delete("/employees/{$employee->id}");
        $response->assertRedirect('/employees');
        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/EmployeeTest.php
```

Expected: FAIL — routes not found (404).

- [ ] **Step 3: Create form requests**

```bash
php artisan make:request StoreEmployeeRequest
php artisan make:request UpdateEmployeeRequest
```

`app/Http/Requests/StoreEmployeeRequest.php`:

```php
<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'daily_rate' => ['required', 'numeric', 'min:0'],
            'shift_start' => ['required', 'date_format:H:i'],
            'shift_end' => ['required', 'date_format:H:i'],
        ];
    }
}
```

`app/Http/Requests/UpdateEmployeeRequest.php`:

```php
<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'daily_rate' => ['required', 'numeric', 'min:0'],
            'shift_start' => ['required', 'date_format:H:i'],
            'shift_end' => ['required', 'date_format:H:i'],
        ];
    }
}
```

- [ ] **Step 4: Write EmployeeController**

`app/Http/Controllers/EmployeeController.php`:

```php
<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function index()
    {
        return Inertia::render('employees/index', [
            'employees' => Employee::orderBy('department')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('employees/create');
    }

    public function store(StoreEmployeeRequest $request)
    {
        Employee::create($request->validated());
        return redirect('/employees')->with('success', 'Employee created.');
    }

    public function edit(Employee $employee)
    {
        return Inertia::render('employees/edit', ['employee' => $employee]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $employee->update($request->validated());
        return redirect('/employees')->with('success', 'Employee updated.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return redirect('/employees')->with('success', 'Employee deleted.');
    }
}
```

- [ ] **Step 5: Add routes**

In `routes/web.php`, inside the `auth` middleware group:

```php
use App\Http\Controllers\EmployeeController;

Route::resource('employees', EmployeeController::class);
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
php artisan test tests/Feature/EmployeeTest.php
```

Expected: 4 tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/EmployeeController.php app/Http/Requests/Store* app/Http/Requests/Update* routes/web.php tests/Feature/EmployeeTest.php
git commit -m "feat: employee CRUD backend"
```

---

## Task 5: Employee CRUD Frontend

**Files:** `resources/js/pages/employees/index.tsx`, `create.tsx`, `edit.tsx`

- [ ] **Step 1: Write employees index page**

`resources/js/pages/employees/index.tsx`:

```tsx
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Employee {
    id: number;
    name: string;
    department: string;
    daily_rate: string;
    shift_start: string;
    shift_end: string;
    is_active: boolean;
}

export default function EmployeesIndex({ employees }: { employees: Employee[] }) {
    function destroy(id: number) {
        if (confirm('Delete this employee?')) {
            router.delete(`/employees/${id}`);
        }
    }

    return (
        <AppLayout>
            <Head title="Employees" />
            <div className="p-6 space-y-4">
                <div className="flex justify-between items-center">
                    <h1 className="text-2xl font-bold">Employees</h1>
                    <Button asChild><Link href="/employees/create">Add Employee</Link></Button>
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Department</TableHead>
                            <TableHead>Daily Rate</TableHead>
                            <TableHead>Shift</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {employees.map((emp) => (
                            <TableRow key={emp.id}>
                                <TableCell>{emp.name}</TableCell>
                                <TableCell>{emp.department}</TableCell>
                                <TableCell>₱{Number(emp.daily_rate).toLocaleString()}</TableCell>
                                <TableCell>{emp.shift_start} – {emp.shift_end}</TableCell>
                                <TableCell>{emp.is_active ? 'Active' : 'Inactive'}</TableCell>
                                <TableCell className="space-x-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/employees/${emp.id}/edit`}>Edit</Link>
                                    </Button>
                                    <Button variant="destructive" size="sm" onClick={() => destroy(emp.id)}>
                                        Delete
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Write employee create page**

`resources/js/pages/employees/create.tsx`:

```tsx
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';

export default function EmployeeCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        department: '',
        daily_rate: '',
        shift_start: '08:00',
        shift_end: '17:00',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/employees');
    }

    return (
        <AppLayout>
            <Head title="Add Employee" />
            <div className="p-6 max-w-lg space-y-4">
                <h1 className="text-2xl font-bold">Add Employee</h1>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label>Name</Label>
                        <Input value={data.name} onChange={e => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div>
                        <Label>Department</Label>
                        <Input value={data.department} onChange={e => setData('department', e.target.value)} />
                        <InputError message={errors.department} />
                    </div>
                    <div>
                        <Label>Daily Rate (₱)</Label>
                        <Input type="number" step="0.01" value={data.daily_rate} onChange={e => setData('daily_rate', e.target.value)} />
                        <InputError message={errors.daily_rate} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Shift Start</Label>
                            <Input type="time" value={data.shift_start} onChange={e => setData('shift_start', e.target.value)} />
                            <InputError message={errors.shift_start} />
                        </div>
                        <div>
                            <Label>Shift End</Label>
                            <Input type="time" value={data.shift_end} onChange={e => setData('shift_end', e.target.value)} />
                            <InputError message={errors.shift_end} />
                        </div>
                    </div>
                    <Button type="submit" disabled={processing}>Save Employee</Button>
                </form>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 3: Write employee edit page**

`resources/js/pages/employees/edit.tsx`:

```tsx
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';

interface Employee {
    id: number; name: string; department: string;
    daily_rate: string; shift_start: string; shift_end: string;
}

export default function EmployeeEdit({ employee }: { employee: Employee }) {
    const { data, setData, put, processing, errors } = useForm({
        name: employee.name,
        department: employee.department,
        daily_rate: employee.daily_rate,
        shift_start: employee.shift_start.slice(0, 5),
        shift_end: employee.shift_end.slice(0, 5),
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(`/employees/${employee.id}`);
    }

    return (
        <AppLayout>
            <Head title="Edit Employee" />
            <div className="p-6 max-w-lg space-y-4">
                <h1 className="text-2xl font-bold">Edit Employee</h1>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label>Name</Label>
                        <Input value={data.name} onChange={e => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div>
                        <Label>Department</Label>
                        <Input value={data.department} onChange={e => setData('department', e.target.value)} />
                        <InputError message={errors.department} />
                    </div>
                    <div>
                        <Label>Daily Rate (₱)</Label>
                        <Input type="number" step="0.01" value={data.daily_rate} onChange={e => setData('daily_rate', e.target.value)} />
                        <InputError message={errors.daily_rate} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Shift Start</Label>
                            <Input type="time" value={data.shift_start} onChange={e => setData('shift_start', e.target.value)} />
                            <InputError message={errors.shift_start} />
                        </div>
                        <div>
                            <Label>Shift End</Label>
                            <Input type="time" value={data.shift_end} onChange={e => setData('shift_end', e.target.value)} />
                            <InputError message={errors.shift_end} />
                        </div>
                    </div>
                    <Button type="submit" disabled={processing}>Update Employee</Button>
                </form>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/employees/
git commit -m "feat: employee CRUD frontend"
```

---

## Task 6: Holiday CRUD Backend

**Files:** `HolidayController.php`, `StoreHolidayRequest.php`, `UpdateHolidayRequest.php`, `tests/Feature/HolidayTest.php`

- [ ] **Step 1: Write failing test**

`tests/Feature/HolidayTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_can_list_holidays(): void
    {
        Holiday::factory()->count(2)->create();
        $this->actingAs($this->admin())->get('/holidays')
            ->assertStatus(200)
            ->assertInertia(fn ($p) => $p->component('holidays/index')->has('holidays', 2));
    }

    public function test_can_create_holiday(): void
    {
        $this->actingAs($this->admin())->post('/holidays', [
            'name' => 'Labor Day',
            'date' => '2026-05-01',
            'type' => 'regular',
        ])->assertRedirect('/holidays');
        $this->assertDatabaseHas('holidays', ['name' => 'Labor Day']);
    }

    public function test_can_delete_holiday(): void
    {
        $holiday = Holiday::factory()->create();
        $this->actingAs($this->admin())->delete("/holidays/{$holiday->id}")
            ->assertRedirect('/holidays');
        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }
}
```

- [ ] **Step 2: Add HolidayFactory**

`database/factories/HolidayFactory.php`:

```php
<?php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class HolidayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word() . ' Holiday',
            'date' => $this->faker->dateTimeBetween('2026-01-01', '2026-12-31')->format('Y-m-d'),
            'type' => $this->faker->randomElement(['regular', 'special']),
        ];
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

```bash
php artisan test tests/Feature/HolidayTest.php
```

Expected: FAIL — routes not found.

- [ ] **Step 4: Write form requests**

```bash
php artisan make:request StoreHolidayRequest
php artisan make:request UpdateHolidayRequest
```

`app/Http/Requests/StoreHolidayRequest.php`:

```php
<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'type' => ['required', Rule::in(['regular', 'special'])],
        ];
    }
}
```

`app/Http/Requests/UpdateHolidayRequest.php` — identical to `StoreHolidayRequest`.

- [ ] **Step 5: Write HolidayController**

`app/Http/Controllers/HolidayController.php`:

```php
<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreHolidayRequest;
use App\Http\Requests\UpdateHolidayRequest;
use App\Models\Holiday;
use Inertia\Inertia;

class HolidayController extends Controller
{
    public function index()
    {
        return Inertia::render('holidays/index', [
            'holidays' => Holiday::orderBy('date')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('holidays/create');
    }

    public function store(StoreHolidayRequest $request)
    {
        Holiday::create($request->validated());
        return redirect('/holidays')->with('success', 'Holiday added.');
    }

    public function edit(Holiday $holiday)
    {
        return Inertia::render('holidays/edit', ['holiday' => $holiday]);
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday)
    {
        $holiday->update($request->validated());
        return redirect('/holidays')->with('success', 'Holiday updated.');
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();
        return redirect('/holidays')->with('success', 'Holiday deleted.');
    }
}
```

- [ ] **Step 6: Add route**

In `routes/web.php`:

```php
use App\Http\Controllers\HolidayController;

Route::resource('holidays', HolidayController::class);
```

- [ ] **Step 7: Run tests**

```bash
php artisan test tests/Feature/HolidayTest.php
```

Expected: 3 tests pass.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/HolidayController.php app/Http/Requests/*Holiday* database/factories/HolidayFactory.php routes/web.php tests/Feature/HolidayTest.php
git commit -m "feat: holiday CRUD backend"
```

---

## Task 7: Holiday CRUD Frontend

**Files:** `resources/js/pages/holidays/index.tsx`, `create.tsx`, `edit.tsx`

- [ ] **Step 1: Write holidays index page**

`resources/js/pages/holidays/index.tsx`:

```tsx
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Holiday {
    id: number; name: string; date: string; type: 'regular' | 'special';
}

export default function HolidaysIndex({ holidays }: { holidays: Holiday[] }) {
    function destroy(id: number) {
        if (confirm('Delete this holiday?')) router.delete(`/holidays/${id}`);
    }

    return (
        <AppLayout>
            <Head title="Holidays" />
            <div className="p-6 space-y-4">
                <div className="flex justify-between items-center">
                    <h1 className="text-2xl font-bold">Holidays</h1>
                    <Button asChild><Link href="/holidays/create">Add Holiday</Link></Button>
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {holidays.map((h) => (
                            <TableRow key={h.id}>
                                <TableCell>{h.name}</TableCell>
                                <TableCell>{h.date}</TableCell>
                                <TableCell>
                                    <Badge variant={h.type === 'regular' ? 'default' : 'secondary'}>
                                        {h.type === 'regular' ? 'Regular (2×)' : 'Special (1.3×)'}
                                    </Badge>
                                </TableCell>
                                <TableCell className="space-x-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/holidays/${h.id}/edit`}>Edit</Link>
                                    </Button>
                                    <Button variant="destructive" size="sm" onClick={() => destroy(h.id)}>
                                        Delete
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Write holiday create page**

`resources/js/pages/holidays/create.tsx`:

```tsx
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';

export default function HolidayCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '', date: '', type: 'regular',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/holidays');
    }

    return (
        <AppLayout>
            <Head title="Add Holiday" />
            <div className="p-6 max-w-md space-y-4">
                <h1 className="text-2xl font-bold">Add Holiday</h1>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label>Name</Label>
                        <Input value={data.name} onChange={e => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div>
                        <Label>Date</Label>
                        <Input type="date" value={data.date} onChange={e => setData('date', e.target.value)} />
                        <InputError message={errors.date} />
                    </div>
                    <div>
                        <Label>Type</Label>
                        <Select value={data.type} onValueChange={v => setData('type', v)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="regular">Regular Holiday (2× pay)</SelectItem>
                                <SelectItem value="special">Special Holiday (1.3× pay)</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.type} />
                    </div>
                    <Button type="submit" disabled={processing}>Save Holiday</Button>
                </form>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 3: Write holiday edit page**

`resources/js/pages/holidays/edit.tsx`:

```tsx
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';

interface Holiday { id: number; name: string; date: string; type: string; }

export default function HolidayEdit({ holiday }: { holiday: Holiday }) {
    const { data, setData, put, processing, errors } = useForm({
        name: holiday.name, date: holiday.date, type: holiday.type,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(`/holidays/${holiday.id}`);
    }

    return (
        <AppLayout>
            <Head title="Edit Holiday" />
            <div className="p-6 max-w-md space-y-4">
                <h1 className="text-2xl font-bold">Edit Holiday</h1>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label>Name</Label>
                        <Input value={data.name} onChange={e => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div>
                        <Label>Date</Label>
                        <Input type="date" value={data.date} onChange={e => setData('date', e.target.value)} />
                        <InputError message={errors.date} />
                    </div>
                    <div>
                        <Label>Type</Label>
                        <Select value={data.type} onValueChange={v => setData('type', v)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="regular">Regular Holiday (2× pay)</SelectItem>
                                <SelectItem value="special">Special Holiday (1.3× pay)</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.type} />
                    </div>
                    <Button type="submit" disabled={processing}>Update Holiday</Button>
                </form>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/holidays/
git commit -m "feat: holiday CRUD frontend"
```

---

## Task 8: AttendanceParser Service

**Files:** `app/Services/AttendanceParser.php`, `tests/Unit/AttendanceParserTest.php`

**Excel format assumptions (biometric DTR):**
- Row 3: metadata (ignored)
- Row 4: column headers — A=Employee ID, B=Card No., C=Name, D=Department, then date values starting from col E. Each date spans 2 columns (merged header).
- Row 5: sub-headers — alternating "SW" and "EW" for each date pair
- Row 6+: employee data rows. Each employee occupies 2 rows (merged name/dept cell). The first row of each pair contains SW times; the second row contains EW times.

- [ ] **Step 1: Write the failing unit test**

`tests/Unit/AttendanceParserTest.php`:

```php
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
        // Day 2: absent (row 6 SW is dashes)
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
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/AttendanceParserTest.php
```

Expected: FAIL — `AttendanceParser` class not found.

- [ ] **Step 3: Write AttendanceParser**

`app/Services/AttendanceParser.php`:

```php
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
        // Row 5 has SW/EW sub-headers. Walk from col E onward.
        $columns = [];
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        $col = 5; // E = 5
        while ($col <= $maxCol) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $dateRaw = $sheet->getCell($letter . '4')->getValue();

            if (empty($dateRaw)) {
                $col++;
                continue;
            }

            // Normalize date: "2026/05/01" → "2026-05-01"
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
                // EW may be in the same row or the next row (merged cell variant)
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

        // Absent indicators
        if (preg_match('/^[-x.]+$/i', $str) || $str === '') return null;

        // Already HH:MM format
        if (preg_match('/^\d{1,2}:\d{2}$/', $str)) return $str;

        // Excel numeric time (fraction of 24h)
        if (is_numeric($value)) {
            $totalMinutes = (int) round((float) $value * 24 * 60);
            return sprintf('%02d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60);
        }

        return null;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
php artisan test tests/Unit/AttendanceParserTest.php
```

Expected: 3 tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/AttendanceParser.php tests/Unit/AttendanceParserTest.php
git commit -m "feat: attendance Excel parser service"
```

---

## Task 9: PayrollCalculator Service

**Files:** `app/Services/PayrollCalculator.php`, `tests/Unit/PayrollCalculatorTest.php`

- [ ] **Step 1: Write the failing unit test**

`tests/Unit/PayrollCalculatorTest.php`:

```php
<?php
namespace Tests\Unit;

use App\Models\Employee;
use App\Models\Holiday;
use App\Services\PayrollCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployee(float $rate = 480.00, string $start = '08:00', string $end = '17:00'): Employee
    {
        return Employee::factory()->create([
            'daily_rate' => $rate,
            'shift_start' => $start . ':00',
            'shift_end' => $end . ':00',
        ]);
    }

    public function test_computes_basic_pay_for_full_day(): void
    {
        $employee = $this->makeEmployee(480.00);
        $attendance = [
            '2026-05-01' => ['sw' => '08:00', 'ew' => '17:00'],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect());

        $this->assertEquals(1, $result['days_present']);
        $this->assertEquals(480.00, $result['total_basic_pay']);
        $this->assertEquals(0, $result['late_minutes']);
        $this->assertEquals(0, $result['overtime_minutes']);
    }

    public function test_computes_late_deduction(): void
    {
        $employee = $this->makeEmployee(480.00);
        // Per-minute rate = 480 / 8 / 60 = 1.00/min
        // Late 10 min = 10.00 deduction
        $attendance = [
            '2026-05-01' => ['sw' => '08:10', 'ew' => '17:00'],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect());

        $this->assertEquals(10, $result['late_minutes']);
        $this->assertEquals(10.00, $result['late_deduction']);
    }

    public function test_computes_overtime(): void
    {
        $employee = $this->makeEmployee(480.00);
        // OT 30 min = 30.00
        $attendance = [
            '2026-05-01' => ['sw' => '08:00', 'ew' => '17:30'],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect());

        $this->assertEquals(30, $result['overtime_minutes']);
        $this->assertEquals(30.00, $result['overtime_pay']);
    }

    public function test_computes_undertime_deduction(): void
    {
        $employee = $this->makeEmployee(480.00);
        // Undertime 15 min = 15.00 deduction
        $attendance = [
            '2026-05-01' => ['sw' => '08:00', 'ew' => '16:45'],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect());

        $this->assertEquals(15, $result['undertime_minutes']);
        $this->assertEquals(15.00, $result['undertime_deduction']);
    }

    public function test_absent_day_not_counted(): void
    {
        $employee = $this->makeEmployee(480.00);
        $attendance = [
            '2026-05-01' => ['sw' => null, 'ew' => null],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect());

        $this->assertEquals(0, $result['days_present']);
        $this->assertEquals(0.00, $result['total_basic_pay']);
    }

    public function test_regular_holiday_doubles_daily_pay(): void
    {
        $employee = $this->makeEmployee(480.00);
        $holiday = Holiday::factory()->create([
            'date' => '2026-05-01',
            'type' => 'regular',
        ]);
        $attendance = [
            '2026-05-01' => ['sw' => '08:00', 'ew' => '17:00'],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect([$holiday]));

        // basic pay = 480, holiday adjustment = +480
        $this->assertEquals(480.00, $result['holiday_pay']);
    }

    public function test_special_holiday_adds_30_percent(): void
    {
        $employee = $this->makeEmployee(480.00);
        $holiday = Holiday::factory()->create([
            'date' => '2026-05-01',
            'type' => 'special',
        ]);
        $attendance = [
            '2026-05-01' => ['sw' => '08:00', 'ew' => '17:00'],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect([$holiday]));

        $this->assertEqualsWithDelta(144.00, $result['holiday_pay'], 0.01);
    }

    public function test_absent_on_holiday_earns_no_holiday_pay(): void
    {
        $employee = $this->makeEmployee(480.00);
        $holiday = Holiday::factory()->create([
            'date' => '2026-05-01',
            'type' => 'regular',
        ]);
        $attendance = [
            '2026-05-01' => ['sw' => null, 'ew' => null],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect([$holiday]));

        $this->assertEquals(0.00, $result['holiday_pay']);
    }

    public function test_gross_pay_formula(): void
    {
        $employee = $this->makeEmployee(480.00);
        // Present 2 days, 10 min late day 1
        $attendance = [
            '2026-05-01' => ['sw' => '08:10', 'ew' => '17:00'],
            '2026-05-02' => ['sw' => '08:00', 'ew' => '17:00'],
        ];

        $result = (new PayrollCalculator())->calculate($employee, $attendance, collect());

        // basic = 960, late_deduction = 10, gross = 950
        $this->assertEquals(960.00, $result['total_basic_pay']);
        $this->assertEquals(10.00, $result['late_deduction']);
        $this->assertEquals(950.00, $result['gross_pay']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/PayrollCalculatorTest.php
```

Expected: FAIL — `PayrollCalculator` not found.

- [ ] **Step 3: Write PayrollCalculator**

`app/Services/PayrollCalculator.php`:

```php
<?php
namespace App\Services;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PayrollCalculator
{
    public function calculate(Employee $employee, array $attendanceDays, Collection $holidays): array
    {
        $perMinuteRate = $employee->daily_rate / 8 / 60;

        $daysPresent = 0;
        $totalBasicPay = 0.0;
        $overtimeMinutes = 0;
        $lateMinutes = 0;
        $undertimeMinutes = 0;
        $holidayPay = 0.0;

        // Index holidays by date string for fast lookup
        $holidayMap = $holidays->keyBy(fn($h) => $h->date->format('Y-m-d'));

        foreach ($attendanceDays as $date => $times) {
            if (empty($times['sw']) || empty($times['ew'])) {
                continue; // absent
            }

            $daysPresent++;
            $totalBasicPay += $employee->daily_rate;

            $shiftStart = Carbon::createFromFormat('Y-m-d H:i', "$date {$employee->shift_start}");
            $shiftEnd   = Carbon::createFromFormat('Y-m-d H:i', "$date {$employee->shift_end}");
            $actualStart = Carbon::createFromFormat('Y-m-d H:i', "$date {$times['sw']}");
            $actualEnd   = Carbon::createFromFormat('Y-m-d H:i', "$date {$times['ew']}");

            // Late: actual start is after shift start
            if ($actualStart->gt($shiftStart)) {
                $lateMinutes += $actualStart->diffInMinutes($shiftStart);
            }

            // Undertime: actual end is before shift end
            if ($actualEnd->lt($shiftEnd)) {
                $undertimeMinutes += $shiftEnd->diffInMinutes($actualEnd);
            }

            // Overtime: actual end is after shift end
            if ($actualEnd->gt($shiftEnd)) {
                $overtimeMinutes += $actualEnd->diffInMinutes($shiftEnd);
            }

            // Holiday adjustment
            if (isset($holidayMap[$date])) {
                $multiplier = $holidayMap[$date]->type === 'regular' ? 2.0 : 1.3;
                $holidayPay += $employee->daily_rate * ($multiplier - 1);
            }
        }

        $lateDeduction      = round($lateMinutes * $perMinuteRate, 2);
        $undertimeDeduction = round($undertimeMinutes * $perMinuteRate, 2);
        $overtimePay        = round($overtimeMinutes * $perMinuteRate, 2);
        $grossPay           = round($totalBasicPay + $overtimePay + $holidayPay - $lateDeduction - $undertimeDeduction, 2);

        return [
            'days_present'        => $daysPresent,
            'total_basic_pay'     => round($totalBasicPay, 2),
            'overtime_minutes'    => $overtimeMinutes,
            'overtime_pay'        => $overtimePay,
            'late_minutes'        => $lateMinutes,
            'late_deduction'      => $lateDeduction,
            'undertime_minutes'   => $undertimeMinutes,
            'undertime_deduction' => $undertimeDeduction,
            'holiday_pay'         => round($holidayPay, 2),
            'gross_pay'           => $grossPay,
        ];
    }
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test tests/Unit/PayrollCalculatorTest.php
```

Expected: 9 tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Services/PayrollCalculator.php tests/Unit/PayrollCalculatorTest.php
git commit -m "feat: payroll calculator service"
```

---

## Task 10: Payroll Run Backend

**Files:** `PayrollRunController.php`, `StorePayrollRunRequest.php`, `tests/Feature/PayrollRunTest.php`

- [ ] **Step 1: Write failing feature test**

`tests/Feature/PayrollRunTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        $this->assertDatabaseHas('payroll_runs', ['period_start' => '2026-05-01']);
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
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/PayrollRunTest.php
```

Expected: FAIL — routes not found.

- [ ] **Step 3: Create StorePayrollRunRequest**

`app/Http/Requests/StorePayrollRunRequest.php`:

```php
<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollRunRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after:period_start'],
            'payable_date' => ['required', 'date'],
        ];
    }
}
```

- [ ] **Step 4: Write PayrollRunController**

`app/Http/Controllers/PayrollRunController.php`:

```php
<?php
namespace App\Http\Controllers;

use App\Http\Requests\StorePayrollRunRequest;
use App\Models\AttendanceUpload;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Services\AttendanceParser;
use App\Services\PayrollCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollRunController extends Controller
{
    public function index()
    {
        return Inertia::render('payroll/index', [
            'runs' => PayrollRun::latest()->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('payroll/create');
    }

    public function store(StorePayrollRunRequest $request)
    {
        $run = PayrollRun::create([
            ...$request->validated(),
            'created_by' => auth()->id(),
        ]);

        return redirect("/payroll-runs/{$run->id}");
    }

    public function show(PayrollRun $payrollRun)
    {
        $payrollRun->load('entries.employee', 'uploads');

        return Inertia::render('payroll/show', [
            'run' => $payrollRun,
            'entries' => $payrollRun->entries,
            'uploads' => $payrollRun->uploads,
        ]);
    }

    public function upload(Request $request, PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls']]);

        $file = $request->file('file');
        $path = $file->store('attendance');

        AttendanceUpload::create([
            'payroll_run_id' => $payrollRun->id,
            'filename' => $file->getClientOriginalName(),
            'uploaded_at' => now(),
        ]);

        // Parse and store preview in session for review before compute
        $parsed = (new AttendanceParser())->parse(storage_path("app/private/{$path}"));
        session(["parsed_attendance_{$payrollRun->id}" => $parsed]);

        return redirect("/payroll-runs/{$payrollRun->id}")
            ->with('parsed', $parsed);
    }

    public function compute(Request $request, PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403);

        $parsed = session("parsed_attendance_{$payrollRun->id}", []);

        if (empty($parsed)) {
            return back()->withErrors(['file' => 'Please upload an attendance file first.']);
        }

        $holidays = Holiday::whereBetween('date', [
            $payrollRun->period_start,
            $payrollRun->period_end,
        ])->get();

        $calculator = new PayrollCalculator();

        // Delete existing draft entries before recomputing
        $payrollRun->entries()->delete();

        $unmatched = [];

        foreach ($parsed as $row) {
            $employee = Employee::where('name', $row['name'])
                ->where('department', $row['department'])
                ->first();

            if (!$employee) {
                $unmatched[] = $row['name'];
                continue;
            }

            // Filter attendance to the pay period range
            $periodAttendance = array_filter(
                $row['attendance'],
                fn($date) => $date >= $payrollRun->period_start->format('Y-m-d')
                    && $date <= $payrollRun->period_end->format('Y-m-d'),
                ARRAY_FILTER_USE_KEY
            );

            $computed = $calculator->calculate($employee, $periodAttendance, $holidays);

            PayrollEntry::create([
                'payroll_run_id' => $payrollRun->id,
                'employee_id' => $employee->id,
                'cash_advance' => 0,
                'other_deductions' => 0,
                'total_deductions' => 0,
                'net_pay' => $computed['gross_pay'],
                'first_release' => 0,
                'second_release' => 0,
                ...$computed,
            ]);
        }

        if (!empty($unmatched)) {
            return redirect("/payroll-runs/{$payrollRun->id}")
                ->with('unmatched', $unmatched);
        }

        return redirect("/payroll-runs/{$payrollRun->id}")
            ->with('success', 'Payroll computed.');
    }

    public function lock(PayrollRun $payrollRun)
    {
        abort_if($payrollRun->isLocked(), 403);

        $payrollRun->update(['status' => 'locked']);

        return redirect("/payroll-runs/{$payrollRun->id}")
            ->with('success', 'Payroll run locked.');
    }
}
```

- [ ] **Step 5: Add routes**

In `routes/web.php`:

```php
use App\Http\Controllers\PayrollRunController;

Route::resource('payroll-runs', PayrollRunController::class);
Route::post('payroll-runs/{payrollRun}/upload', [PayrollRunController::class, 'upload'])->name('payroll-runs.upload');
Route::post('payroll-runs/{payrollRun}/compute', [PayrollRunController::class, 'compute'])->name('payroll-runs.compute');
Route::post('payroll-runs/{payrollRun}/lock', [PayrollRunController::class, 'lock'])->name('payroll-runs.lock');
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/PayrollRunTest.php
```

Expected: 4 tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/PayrollRunController.php app/Http/Requests/StorePayrollRunRequest.php routes/web.php tests/Feature/PayrollRunTest.php
git commit -m "feat: payroll run backend (create, upload, compute, lock)"
```

---

## Task 11: Payroll Run Frontend

**Files:** `resources/js/pages/payroll/index.tsx`, `create.tsx`, `show.tsx`, `resources/js/components/payroll/attendance-preview-table.tsx`, `payroll-summary-table.tsx`, `deduction-sheet.tsx`

- [ ] **Step 1: Write payroll index page**

`resources/js/pages/payroll/index.tsx`:

```tsx
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface PayrollRun {
    id: number; period_start: string; period_end: string;
    payable_date: string; status: 'draft' | 'locked';
}

export default function PayrollIndex({ runs }: { runs: PayrollRun[] }) {
    return (
        <AppLayout>
            <Head title="Payroll Runs" />
            <div className="p-6 space-y-4">
                <div className="flex justify-between items-center">
                    <h1 className="text-2xl font-bold">Payroll Runs</h1>
                    <Button asChild><Link href="/payroll-runs/create">New Payroll Run</Link></Button>
                </div>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Period</TableHead>
                            <TableHead>Payable Date</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {runs.map((run) => (
                            <TableRow key={run.id}>
                                <TableCell>{run.period_start} – {run.period_end}</TableCell>
                                <TableCell>{run.payable_date}</TableCell>
                                <TableCell>
                                    <Badge variant={run.status === 'locked' ? 'default' : 'secondary'}>
                                        {run.status}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/payroll-runs/${run.id}`}>View</Link>
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Write payroll create page**

`resources/js/pages/payroll/create.tsx`:

```tsx
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';

export default function PayrollCreate() {
    const { data, setData, post, processing, errors } = useForm({
        period_start: '', period_end: '', payable_date: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/payroll-runs');
    }

    return (
        <AppLayout>
            <Head title="New Payroll Run" />
            <div className="p-6 max-w-md space-y-4">
                <h1 className="text-2xl font-bold">New Payroll Run</h1>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label>Period Start</Label>
                        <Input type="date" value={data.period_start} onChange={e => setData('period_start', e.target.value)} />
                        <InputError message={errors.period_start} />
                    </div>
                    <div>
                        <Label>Period End</Label>
                        <Input type="date" value={data.period_end} onChange={e => setData('period_end', e.target.value)} />
                        <InputError message={errors.period_end} />
                    </div>
                    <div>
                        <Label>Payable Date</Label>
                        <Input type="date" value={data.payable_date} onChange={e => setData('payable_date', e.target.value)} />
                        <InputError message={errors.payable_date} />
                    </div>
                    <Button type="submit" disabled={processing}>Create Run</Button>
                </form>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 3: Write PayrollSummaryTable component**

`resources/js/components/payroll/payroll-summary-table.tsx`:

```tsx
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';

export interface PayrollEntry {
    id: number;
    employee: { name: string; department: string };
    days_present: number;
    total_basic_pay: string;
    overtime_pay: string;
    late_deduction: string;
    undertime_deduction: string;
    holiday_pay: string;
    gross_pay: string;
    cash_advance: string;
    other_deductions: string;
    total_deductions: string;
    net_pay: string;
    first_release: string;
    second_release: string;
}

function fmt(v: string) {
    return `₱${Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;
}

interface Props {
    entries: PayrollEntry[];
    isLocked: boolean;
    onEdit?: (entry: PayrollEntry) => void;
    onDownloadSlip?: (entryId: number) => void;
}

export default function PayrollSummaryTable({ entries, isLocked, onEdit, onDownloadSlip }: Props) {
    return (
        <div className="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Dept</TableHead>
                        <TableHead className="text-right">Days</TableHead>
                        <TableHead className="text-right">Basic Pay</TableHead>
                        <TableHead className="text-right">OT Pay</TableHead>
                        <TableHead className="text-right">Holiday</TableHead>
                        <TableHead className="text-right">Late</TableHead>
                        <TableHead className="text-right">Undertime</TableHead>
                        <TableHead className="text-right">Gross Pay</TableHead>
                        <TableHead className="text-right">Deductions</TableHead>
                        <TableHead className="text-right">Net Pay</TableHead>
                        <TableHead className="text-right">1st Release</TableHead>
                        <TableHead className="text-right">2nd Release</TableHead>
                        <TableHead />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {entries.map((e) => (
                        <TableRow key={e.id}>
                            <TableCell className="font-medium">{e.employee.name}</TableCell>
                            <TableCell>{e.employee.department}</TableCell>
                            <TableCell className="text-right">{e.days_present}</TableCell>
                            <TableCell className="text-right">{fmt(e.total_basic_pay)}</TableCell>
                            <TableCell className="text-right">{fmt(e.overtime_pay)}</TableCell>
                            <TableCell className="text-right">{fmt(e.holiday_pay)}</TableCell>
                            <TableCell className="text-right text-red-600">({fmt(e.late_deduction)})</TableCell>
                            <TableCell className="text-right text-red-600">({fmt(e.undertime_deduction)})</TableCell>
                            <TableCell className="text-right font-semibold">{fmt(e.gross_pay)}</TableCell>
                            <TableCell className="text-right text-red-600">({fmt(e.total_deductions)})</TableCell>
                            <TableCell className="text-right font-bold">{fmt(e.net_pay)}</TableCell>
                            <TableCell className="text-right">{fmt(e.first_release)}</TableCell>
                            <TableCell className="text-right">{fmt(e.second_release)}</TableCell>
                            <TableCell className="space-x-1">
                                {!isLocked && onEdit && (
                                    <Button variant="outline" size="sm" onClick={() => onEdit(e)}>
                                        Edit
                                    </Button>
                                )}
                                {isLocked && onDownloadSlip && (
                                    <Button variant="outline" size="sm" onClick={() => onDownloadSlip(e.id)}>
                                        Payslip
                                    </Button>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
```

- [ ] **Step 4: Write DeductionSheet component**

`resources/js/components/payroll/deduction-sheet.tsx`:

```tsx
import { useForm } from '@inertiajs/react';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import { PayrollEntry } from './payroll-summary-table';

interface Props {
    entry: PayrollEntry | null;
    open: boolean;
    onClose: () => void;
}

export default function DeductionSheet({ entry, open, onClose }: Props) {
    const { data, setData, put, processing, errors, reset } = useForm({
        cash_advance: entry?.cash_advance ?? '0',
        other_deductions: entry?.other_deductions ?? '0',
        first_release: entry?.first_release ?? '0',
        second_release: entry?.second_release ?? '0',
    });

    // Sync form when entry changes
    if (entry && data.cash_advance !== entry.cash_advance) {
        setData({
            cash_advance: entry.cash_advance,
            other_deductions: entry.other_deductions,
            first_release: entry.first_release,
            second_release: entry.second_release,
        });
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!entry) return;
        put(`/payroll-entries/${entry.id}`, {
            onSuccess: () => { reset(); onClose(); },
        });
    }

    return (
        <Sheet open={open} onOpenChange={(o) => { if (!o) onClose(); }}>
            <SheetContent>
                <SheetHeader>
                    <SheetTitle>{entry?.employee.name}</SheetTitle>
                </SheetHeader>
                <form onSubmit={submit} className="mt-6 space-y-4">
                    <div>
                        <Label>Cash Advance (₱)</Label>
                        <Input type="number" step="0.01" min="0" value={data.cash_advance}
                            onChange={e => setData('cash_advance', e.target.value)} />
                        <InputError message={errors.cash_advance} />
                    </div>
                    <div>
                        <Label>Other Deductions (₱)</Label>
                        <Input type="number" step="0.01" min="0" value={data.other_deductions}
                            onChange={e => setData('other_deductions', e.target.value)} />
                        <InputError message={errors.other_deductions} />
                    </div>
                    <div>
                        <Label>1st Release (₱)</Label>
                        <Input type="number" step="0.01" min="0" value={data.first_release}
                            onChange={e => setData('first_release', e.target.value)} />
                        <InputError message={errors.first_release} />
                    </div>
                    <div>
                        <Label>2nd Release (₱)</Label>
                        <Input type="number" step="0.01" min="0" value={data.second_release}
                            onChange={e => setData('second_release', e.target.value)} />
                        <InputError message={errors.second_release} />
                    </div>
                    <Button type="submit" disabled={processing} className="w-full">Save</Button>
                </form>
            </SheetContent>
        </Sheet>
    );
}
```

- [ ] **Step 5: Write payroll show page**

`resources/js/pages/payroll/show.tsx`:

```tsx
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { useState, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import PayrollSummaryTable, { PayrollEntry } from '@/components/payroll/payroll-summary-table';
import DeductionSheet from '@/components/payroll/deduction-sheet';

interface PayrollRun {
    id: number; period_start: string; period_end: string;
    payable_date: string; status: 'draft' | 'locked';
}

interface Props {
    run: PayrollRun;
    entries: PayrollEntry[];
}

export default function PayrollShow({ run, entries }: Props) {
    const [selectedEntry, setSelectedEntry] = useState<PayrollEntry | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);

    const { data, setData, post, processing } = useForm({ file: null as File | null });

    function uploadFile(e: React.FormEvent) {
        e.preventDefault();
        if (!data.file) return;
        const form = new FormData();
        form.append('file', data.file);
        router.post(`/payroll-runs/${run.id}/upload`, form as any);
    }

    function compute() {
        router.post(`/payroll-runs/${run.id}/compute`);
    }

    function lock() {
        if (confirm('Lock this payroll run? This cannot be undone.')) {
            router.post(`/payroll-runs/${run.id}/lock`);
        }
    }

    function downloadAllSlips() {
        window.open(`/payroll-runs/${run.id}/payslips/download-all`, '_blank');
    }

    function downloadSlip(entryId: number) {
        window.open(`/payroll-entries/${entryId}/payslip`, '_blank');
    }

    const isLocked = run.status === 'locked';

    return (
        <AppLayout>
            <Head title={`Payroll Run ${run.period_start} – ${run.period_end}`} />
            <div className="p-6 space-y-6">
                <div className="flex justify-between items-start">
                    <div>
                        <h1 className="text-2xl font-bold">
                            Payroll: {run.period_start} – {run.period_end}
                        </h1>
                        <p className="text-muted-foreground">Payable: {run.payable_date}</p>
                    </div>
                    <div className="flex gap-2 items-center">
                        <Badge variant={isLocked ? 'default' : 'secondary'}>{run.status}</Badge>
                        {!isLocked && entries.length > 0 && (
                            <Button onClick={lock} variant="destructive">Lock Run</Button>
                        )}
                        {isLocked && (
                            <Button onClick={downloadAllSlips}>Download All Payslips</Button>
                        )}
                    </div>
                </div>

                {!isLocked && (
                    <div className="border rounded-lg p-4 space-y-3">
                        <h2 className="font-semibold">1. Upload Attendance File</h2>
                        <form onSubmit={uploadFile} className="flex gap-2">
                            <input
                                ref={fileRef}
                                type="file"
                                accept=".xlsx,.xls"
                                className="flex-1 text-sm"
                                onChange={e => setData('file', e.target.files?.[0] ?? null)}
                            />
                            <Button type="submit" disabled={processing || !data.file}>Upload</Button>
                        </form>
                        <Button onClick={compute} variant="outline">2. Compute Payroll</Button>
                    </div>
                )}

                {entries.length > 0 && (
                    <div className="space-y-2">
                        <h2 className="font-semibold">Payroll Summary</h2>
                        <PayrollSummaryTable
                            entries={entries}
                            isLocked={isLocked}
                            onEdit={setSelectedEntry}
                            onDownloadSlip={downloadSlip}
                        />
                    </div>
                )}
            </div>

            <DeductionSheet
                entry={selectedEntry}
                open={selectedEntry !== null}
                onClose={() => setSelectedEntry(null)}
            />
        </AppLayout>
    );
}
```

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/payroll/ resources/js/components/payroll/
git commit -m "feat: payroll run frontend pages and components"
```

---

## Task 12: PayrollEntry Update (Deductions + Releases)

**Files:** `PayrollEntryController.php`, routes

- [ ] **Step 1: Write failing test**

Add to `tests/Feature/PayrollRunTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/PayrollRunTest.php::test_can_update_entry_deductions
```

Expected: FAIL.

- [ ] **Step 3: Write PayrollEntryController**

`app/Http/Controllers/PayrollEntryController.php`:

```php
<?php
namespace App\Http\Controllers;

use App\Models\PayrollEntry;
use Illuminate\Http\Request;

class PayrollEntryController extends Controller
{
    public function update(Request $request, PayrollEntry $payrollEntry)
    {
        abort_if($payrollEntry->payrollRun->isLocked(), 403);

        $data = $request->validate([
            'cash_advance'     => ['required', 'numeric', 'min:0'],
            'other_deductions' => ['required', 'numeric', 'min:0'],
            'first_release'    => ['required', 'numeric', 'min:0'],
            'second_release'   => ['required', 'numeric', 'min:0'],
        ]);

        $totalDeductions = $data['cash_advance'] + $data['other_deductions'];
        $netPay = round($payrollEntry->gross_pay - $totalDeductions, 2);

        $payrollEntry->update([
            ...$data,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
        ]);

        return back()->with('success', 'Entry updated.');
    }
}
```

- [ ] **Step 4: Add route**

In `routes/web.php`:

```php
use App\Http\Controllers\PayrollEntryController;

Route::put('payroll-entries/{payrollEntry}', [PayrollEntryController::class, 'update'])->name('payroll-entries.update');
```

- [ ] **Step 5: Run test**

```bash
php artisan test tests/Feature/PayrollRunTest.php
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PayrollEntryController.php routes/web.php tests/Feature/PayrollRunTest.php
git commit -m "feat: payroll entry deductions update"
```

---

## Task 13: Payslip PDF Generation

**Files:** `PayslipController.php`, `resources/views/payslip.blade.php`, `tests/Feature/PayslipTest.php`

- [ ] **Step 1: Write failing test**

`tests/Feature/PayslipTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipTest extends TestCase
{
    use RefreshDatabase;

    private function makeLockedEntry(): PayrollEntry
    {
        $user = User::factory()->create();
        $run = PayrollRun::factory()->create([
            'created_by' => $user->id,
            'status' => 'locked',
        ]);
        $employee = Employee::factory()->create();

        return PayrollEntry::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'days_present' => 11,
            'total_basic_pay' => 5280,
            'overtime_minutes' => 120, 'overtime_pay' => 240,
            'late_minutes' => 0, 'late_deduction' => 0,
            'undertime_minutes' => 0, 'undertime_deduction' => 0,
            'holiday_pay' => 0, 'gross_pay' => 5520,
            'cash_advance' => 0, 'other_deductions' => 0,
            'total_deductions' => 0, 'net_pay' => 5520,
            'first_release' => 2760, 'second_release' => 2760,
        ]);
    }

    public function test_can_download_single_payslip_pdf(): void
    {
        $entry = $this->makeLockedEntry();

        $response = $this->actingAs(User::first())
            ->get("/payroll-entries/{$entry->id}/payslip");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_cannot_download_payslip_from_draft_run(): void
    {
        $user = User::factory()->create();
        $run = PayrollRun::factory()->create(['created_by' => $user->id, 'status' => 'draft']);
        $employee = Employee::factory()->create();
        $entry = PayrollEntry::create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id,
            'days_present' => 0, 'total_basic_pay' => 0, 'overtime_minutes' => 0,
            'overtime_pay' => 0, 'late_minutes' => 0, 'late_deduction' => 0,
            'undertime_minutes' => 0, 'undertime_deduction' => 0,
            'holiday_pay' => 0, 'gross_pay' => 0, 'cash_advance' => 0,
            'other_deductions' => 0, 'total_deductions' => 0, 'net_pay' => 0,
            'first_release' => 0, 'second_release' => 0,
        ]);

        $this->actingAs($user)->get("/payroll-entries/{$entry->id}/payslip")
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/PayslipTest.php
```

Expected: FAIL.

- [ ] **Step 3: Write payslip Blade template**

`resources/views/payslip.blade.php`:

```html
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; }
    .header-table { width: 100%; border-collapse: collapse; }
    .logo { font-weight: bold; font-size: 18px; }
    .company { text-align: center; font-weight: bold; font-size: 12px; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.data td, table.data th { border: 1px solid #333; padding: 4px 8px; }
    table.data th { background: #eee; }
    .right { text-align: right; }
    .bold { font-weight: bold; }
    .signatures { margin-top: 40px; }
    .sig-row { display: flex; justify-content: space-between; margin-top: 50px; }
    .sig-line { border-top: 1px solid #333; width: 200px; text-align: center; padding-top: 4px; }
</style>
</head>
<body>
<table class="header-table">
    <tr>
        <td style="width:80px" class="logo">BHAGOH</td>
        <td class="company">
            Beulah Information Technology Services and Business Solutions Inc.<br>
            <strong>Payslip for the month of {{ $run->period_start->format('F') }}
            {{ $run->period_start->format('d') }}-{{ $run->period_end->format('d') }},{{ $run->period_start->format('Y') }}</strong>
        </td>
    </tr>
</table>

<table class="header-table" style="margin-top:8px">
    <tr>
        <td><strong>{{ strtoupper($employee->name) }}</strong></td>
        <td>Paid Days: {{ $run->period_start->format('M d') }} - {{ $run->period_end->format('M d') }}</td>
    </tr>
    <tr>
        <td>Gender:</td>
        <td>Days Present: {{ $entry->days_present }}</td>
    </tr>
    <tr>
        <td></td>
        <td>Rate: {{ number_format($employee->daily_rate, 2) }}</td>
    </tr>
</table>

<table class="data" style="margin-top:10px">
    <tr>
        <th>Earnings</th><th>Amount</th><th>Deductions</th><th>Amount</th>
    </tr>
    <tr>
        <td>Basic Pay:</td>
        <td class="right">{{ number_format($entry->total_basic_pay, 2) }}</td>
        <td>Cash Advance</td>
        <td class="right">{{ $entry->cash_advance > 0 ? number_format($entry->cash_advance, 2) : '' }}</td>
    </tr>
    <tr>
        <td>Overtime</td>
        <td class="right">{{ $entry->overtime_pay > 0 ? number_format($entry->overtime_pay, 2) : '' }}</td>
        <td>Late</td>
        <td class="right">{{ $entry->late_deduction > 0 ? number_format($entry->late_deduction, 2) : '' }}</td>
    </tr>
    <tr>
        <td>Holiday Adjustment</td>
        <td class="right">{{ $entry->holiday_pay > 0 ? number_format($entry->holiday_pay, 2) : '' }}</td>
        <td>Undertime</td>
        <td class="right">{{ $entry->undertime_deduction > 0 ? number_format($entry->undertime_deduction, 2) : '' }}</td>
    </tr>
    <tr>
        <td></td><td></td>
        <td>Others</td>
        <td class="right">{{ $entry->other_deductions > 0 ? number_format($entry->other_deductions, 2) : '' }}</td>
    </tr>
    <tr>
        <td class="bold">Gross Salary:</td>
        <td class="right bold">{{ number_format($entry->gross_pay, 2) }}</td>
        <td class="bold">Total Deductions</td>
        <td class="right bold">{{ number_format($entry->total_deductions, 2) }}</td>
    </tr>
    <tr>
        <td colspan="2" class="bold">Net Pay: <span style="float:right">&#8369;{{ number_format($entry->net_pay, 2) }}</span></td>
        <td colspan="2"></td>
    </tr>
</table>

<p style="margin-top:10px;font-size:10px">
    Note: Full details of your pay for the covered period are given above. Please check carefully and notify HR of any discrepancies.
</p>

<table class="header-table" style="margin-top:50px">
    <tr>
        <td style="text-align:center;border-top:1px solid #333;width:200px">Human Resource</td>
        <td></td>
        <td style="text-align:center;border-top:1px solid #333;width:200px">Manager</td>
    </tr>
</table>
<p style="margin-top:30px">Approved by: ____________________</p>
</body>
</html>
```

- [ ] **Step 4: Write PayslipController**

`app/Http/Controllers/PayslipController.php`:

```php
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
            'entry' => $payrollEntry,
            'employee' => $payrollEntry->employee,
            'run' => $payrollEntry->payrollRun,
        ]);

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
                'entry' => $entry,
                'employee' => $entry->employee,
                'run' => $payrollRun,
            ]);

            $filename = str($entry->employee->name)->slug() . '-payslip.pdf';
            $zip->addFromString($filename, $pdf->output());
        }

        $zip->close();

        return Response::download($zipPath, "payslips-{$payrollRun->period_start}-{$payrollRun->period_end}.zip")
            ->deleteFileAfterSend(true);
    }
}
```

- [ ] **Step 5: Add routes**

In `routes/web.php`:

```php
use App\Http\Controllers\PayslipController;

Route::get('payroll-entries/{payrollEntry}/payslip', [PayslipController::class, 'download'])->name('payslip.download');
Route::get('payroll-runs/{payrollRun}/payslips/download-all', [PayslipController::class, 'downloadAll'])->name('payslip.download-all');
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/PayslipTest.php
```

Expected: 2 tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/PayslipController.php resources/views/payslip.blade.php routes/web.php tests/Feature/PayslipTest.php
git commit -m "feat: payslip PDF generation and download"
```

---

## Task 14: Payroll Summary Excel Export

**Files:** `app/Services/PayrollExportService.php`, export route in `PayrollRunController`

- [ ] **Step 1: Write PayrollExportService**

`app/Services/PayrollExportService.php`:

```php
<?php
namespace App\Services;

use App\Models\PayrollRun;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PayrollExportService
{
    public function export(PayrollRun $run): string
    {
        $run->load('entries.employee');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Title rows
        $sheet->setCellValue('A1', 'PAYROLL SUMMARY');
        $sheet->setCellValue('A2', "PERIOD COVERED: {$run->period_start->format('M.d')}-{$run->period_end->format('d,Y')}");
        $sheet->setCellValue('A3', "Payable Date: {$run->payable_date->format('F d, Y')}");

        // Header row
        $headers = [
            'Employee #', 'Department', 'Employee\'s Name', 'Daily Rate',
            'No. of Working Days', 'Total Pay', 'Holiday', 'Overtime Pay',
            'Absences Late/Undertime', 'GROSS PAY', 'Deductions C/A', 'Others',
            'Total Deduction', '1st Release', '2nd Release', 'BALANCE', 'NET PAY',
        ];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '5', $header);
            $col++;
        }

        // Data rows
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
```

- [ ] **Step 2: Add export method to PayrollRunController**

In `app/Http/Controllers/PayrollRunController.php`, add:

```php
use App\Services\PayrollExportService;
use Illuminate\Support\Facades\Response;

public function export(PayrollRun $payrollRun)
{
    abort_if(!$payrollRun->isLocked(), 403);

    $path = (new PayrollExportService())->export($payrollRun);
    $filename = "payroll-{$payrollRun->period_start}-{$payrollRun->period_end}.xlsx";

    return Response::download($path, $filename)->deleteFileAfterSend(true);
}
```

- [ ] **Step 3: Add route**

In `routes/web.php`:

```php
Route::get('payroll-runs/{payrollRun}/export', [PayrollRunController::class, 'export'])->name('payroll-runs.export');
```

- [ ] **Step 4: Add export button to `resources/js/pages/payroll/show.tsx`**

In the buttons area where `isLocked` is true, add alongside the "Download All Payslips" button:

```tsx
{isLocked && (
    <Button variant="outline"
        onClick={() => window.open(`/payroll-runs/${run.id}/export`, '_blank')}>
        Export Excel
    </Button>
)}
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/PayrollExportService.php app/Http/Controllers/PayrollRunController.php routes/web.php resources/js/pages/payroll/show.tsx
git commit -m "feat: payroll summary Excel export"
```

---

## Task 15: Sidebar Navigation

**Files:** `resources/js/components/app-sidebar.tsx`

- [ ] **Step 1: Read current sidebar to understand the nav structure**

Open `resources/js/components/app-sidebar.tsx` and locate the `navMain` array or equivalent nav items list.

- [ ] **Step 2: Add payroll nav items**

Find the navigation items array in `app-sidebar.tsx` and add:

```tsx
{
    title: 'Employees',
    url: '/employees',
    icon: Users,  // import Users from 'lucide-react'
},
{
    title: 'Holidays',
    url: '/holidays',
    icon: CalendarDays,  // import CalendarDays from 'lucide-react'
},
{
    title: 'Payroll Runs',
    url: '/payroll-runs',
    icon: Banknote,  // import Banknote from 'lucide-react'
},
```

Add the imports at the top of the file:

```tsx
import { Users, CalendarDays, Banknote } from 'lucide-react';
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/app-sidebar.tsx
git commit -m "feat: add payroll nav items to sidebar"
```

---

## Task 16: Final Integration Check

- [ ] **Step 1: Run the full test suite**

```bash
php artisan test
```

Expected: all tests pass, no failures.

- [ ] **Step 2: Build frontend assets**

```bash
npm run build
```

Expected: build completes with no TypeScript errors.

- [ ] **Step 3: Start the dev server and manually verify the flow**

```bash
php artisan serve
npm run dev
```

Verify in browser:
1. Log in → sidebar shows Employees, Holidays, Payroll Runs
2. Create an employee with name, department, rate, shift
3. Add a holiday
4. Create a payroll run
5. Upload a sample attendance Excel → preview shows correctly
6. Click Compute → entries appear in summary table
7. Click Edit on a row → deduction sheet opens, save deductions
8. Lock the run → Edit buttons disappear, Payslip buttons appear
9. Download a payslip PDF → matches the expected format
10. Download all payslips ZIP → extracts correctly
11. Export Excel → opens with correct data

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "feat: complete payroll system implementation"
```

---

## Notes for Implementer

- **Attendance parser flexibility:** The biometric DTR format may vary. If employee rows don't come in pairs (i.e., each employee is only one row), change the `$row += 2` increment in `AttendanceParser::parseEmployees()` to `$row += 1`.
- **Employee name matching:** Names must match exactly between the Excel and the database. If HR reports unmatched employees, they need to either fix the name in the system or in the Excel. The `compute` action returns an `unmatched` flash array that the frontend should display as a warning list.
- **Time format from biometric:** If the biometric exports numeric Excel time values (floats) instead of string times, the `parseTime()` method in `AttendanceParser` handles this via the `is_numeric` branch.
- **ZipArchive:** Requires PHP's `zip` extension. Verify with `php -m | grep zip`.

# Payslip Print & PDF Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add employee_number/gender fields to employees and deliver two payslip outputs — a 4-per-A4 browser-print batch view and an improved full-A4 individual PDF download.

**Architecture:** A shared Blade partial (`payslip._card`) contains the payslip layout; `payslip.blade.php` wraps it for A4 PDF (DomPDF), and a new `payslip-batch.blade.php` renders all entries in a 2×2 CSS grid for browser printing. A new `PayslipController@printAll` route serves the batch view. Employee model, form requests, and React forms gain two new optional fields.

**Tech Stack:** Laravel 11, Inertia/React, DomPDF (`barryvdh/laravel-dompdf`), Pest, TypeScript

---

## File Map

| Action | File |
|--------|------|
| Create | `database/migrations/2026_05_28_000000_add_employee_number_and_gender_to_employees.php` |
| Modify | `app/Models/Employee.php` |
| Modify | `app/Http/Requests/StoreEmployeeRequest.php` |
| Modify | `app/Http/Requests/UpdateEmployeeRequest.php` |
| Modify | `resources/js/pages/employees/create.tsx` |
| Modify | `resources/js/pages/employees/edit.tsx` |
| Create | `resources/views/payslip/_card.blade.php` |
| Modify | `resources/views/payslip.blade.php` |
| Create | `resources/views/payslip-batch.blade.php` |
| Modify | `app/Http/Controllers/PayslipController.php` |
| Modify | `routes/web.php` |
| Modify | `resources/js/pages/payroll/show.tsx` |
| Create | `tests/Feature/PayslipPrintTest.php` |
| Create | `tests/Feature/EmployeeFieldsTest.php` |

---

## Task 1: Database Migration

**Files:**
- Create: `database/migrations/2026_05_28_000000_add_employee_number_and_gender_to_employees.php`

- [ ] **Step 1: Create the migration**

```bash
php artisan make:migration add_employee_number_and_gender_to_employees --table=employees
```

- [ ] **Step 2: Fill in the migration**

Open the newly created file in `database/migrations/` (it will have a timestamp prefix). Replace its entire contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('employee_number')->nullable()->after('name');
            $table->string('gender')->nullable()->after('employee_number');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['employee_number', 'gender']);
        });
    }
};
```

- [ ] **Step 3: Run the migration**

```bash
php artisan migrate
```

Expected output: `Running migrations... DONE`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat: add employee_number and gender columns to employees"
```

---

## Task 2: Update Employee Model and Form Requests

**Files:**
- Modify: `app/Models/Employee.php`
- Modify: `app/Http/Requests/StoreEmployeeRequest.php`
- Modify: `app/Http/Requests/UpdateEmployeeRequest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/EmployeeFieldsTest.php`:

```php
<?php

use App\Models\Employee;
use App\Models\User;

test('employee can be created with employee_number and gender', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/employees', [
            'name' => 'Juan dela Cruz',
            'department' => 'IT',
            'daily_rate' => 700,
            'shift_start' => '08:00',
            'shift_end' => '17:00',
            'employee_number' => 'EMP-001',
            'gender' => 'Male',
        ])
        ->assertRedirect('/employees');

    $employee = Employee::where('name', 'Juan dela Cruz')->first();
    expect($employee->employee_number)->toBe('EMP-001');
    expect($employee->gender)->toBe('Male');
});

test('employee can be created without employee_number and gender', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/employees', [
            'name' => 'Maria Santos',
            'department' => 'HR',
            'daily_rate' => 600,
            'shift_start' => '08:00',
            'shift_end' => '17:00',
        ])
        ->assertRedirect('/employees');

    $employee = Employee::where('name', 'Maria Santos')->first();
    expect($employee->employee_number)->toBeNull();
    expect($employee->gender)->toBeNull();
});

test('gender must be Male or Female if provided', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/employees', [
            'name' => 'Test Person',
            'department' => 'IT',
            'daily_rate' => 700,
            'shift_start' => '08:00',
            'shift_end' => '17:00',
            'gender' => 'Other',
        ])
        ->assertSessionHasErrors('gender');
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test tests/Feature/EmployeeFieldsTest.php
```

Expected: FAIL — `employee_number` and `gender` not in `$fillable` or validation rules yet.

- [ ] **Step 3: Update Employee model `$fillable`**

In `app/Models/Employee.php`, replace the `$fillable` array:

```php
protected $fillable = [
    'name', 'employee_number', 'gender', 'department', 'daily_rate', 'shift_start', 'shift_end', 'is_active',
];
```

- [ ] **Step 4: Update StoreEmployeeRequest**

Replace the contents of `app/Http/Requests/StoreEmployeeRequest.php`:

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
            'name'            => ['required', 'string', 'max:255'],
            'employee_number' => ['nullable', 'string', 'max:50'],
            'gender'          => ['nullable', 'in:Male,Female'],
            'department'      => ['required', 'string', 'max:255'],
            'daily_rate'      => ['required', 'numeric', 'min:0'],
            'shift_start'     => ['required', 'date_format:H:i'],
            'shift_end'       => ['required', 'date_format:H:i'],
        ];
    }
}
```

- [ ] **Step 5: Update UpdateEmployeeRequest**

Replace the contents of `app/Http/Requests/UpdateEmployeeRequest.php`:

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
            'name'            => ['required', 'string', 'max:255'],
            'employee_number' => ['nullable', 'string', 'max:50'],
            'gender'          => ['nullable', 'in:Male,Female'],
            'department'      => ['required', 'string', 'max:255'],
            'daily_rate'      => ['required', 'numeric', 'min:0'],
            'shift_start'     => ['required', 'date_format:H:i'],
            'shift_end'       => ['required', 'date_format:H:i'],
        ];
    }
}
```

- [ ] **Step 6: Run the tests to verify they pass**

```bash
php artisan test tests/Feature/EmployeeFieldsTest.php
```

Expected: All 3 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Employee.php app/Http/Requests/StoreEmployeeRequest.php app/Http/Requests/UpdateEmployeeRequest.php tests/Feature/EmployeeFieldsTest.php
git commit -m "feat: add employee_number and gender to Employee model and validation"
```

---

## Task 3: Update Employee Create & Edit Forms

**Files:**
- Modify: `resources/js/pages/employees/create.tsx`
- Modify: `resources/js/pages/employees/edit.tsx`

- [ ] **Step 1: Update create.tsx**

Replace the entire contents of `resources/js/pages/employees/create.tsx`:

```tsx
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';
import { index, create } from '@/routes/employees';

export default function EmployeeCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        employee_number: '',
        gender: '',
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
        <>
            <Head title="Add Employee" />
            <div className="p-6 max-w-lg space-y-4">
                <h1 className="text-2xl font-bold">Add Employee</h1>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label>Name</Label>
                        <Input value={data.name} onChange={e => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Employee Number</Label>
                            <Input value={data.employee_number} onChange={e => setData('employee_number', e.target.value)} placeholder="e.g. EMP-001" />
                            <InputError message={errors.employee_number} />
                        </div>
                        <div>
                            <Label>Gender</Label>
                            <Select value={data.gender} onValueChange={v => setData('gender', v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select gender" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Male">Male</SelectItem>
                                    <SelectItem value="Female">Female</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.gender} />
                        </div>
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
        </>
    );
}

EmployeeCreate.layout = {
    breadcrumbs: [
        { title: 'Employees', href: index() },
        { title: 'Add Employee', href: create() },
    ],
};
```

- [ ] **Step 2: Update edit.tsx**

Replace the entire contents of `resources/js/pages/employees/edit.tsx`:

```tsx
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';
import { index } from '@/routes/employees';

interface Employee {
    id: number; name: string; employee_number: string | null; gender: string | null;
    department: string; daily_rate: string; shift_start: string; shift_end: string;
}

export default function EmployeeEdit({ employee }: { employee: Employee }) {
    const { data, setData, put, processing, errors } = useForm({
        name: employee.name,
        employee_number: employee.employee_number ?? '',
        gender: employee.gender ?? '',
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
        <>
            <Head title="Edit Employee" />
            <div className="p-6 max-w-lg space-y-4">
                <h1 className="text-2xl font-bold">Edit Employee</h1>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label>Name</Label>
                        <Input value={data.name} onChange={e => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Employee Number</Label>
                            <Input value={data.employee_number} onChange={e => setData('employee_number', e.target.value)} placeholder="e.g. EMP-001" />
                            <InputError message={errors.employee_number} />
                        </div>
                        <div>
                            <Label>Gender</Label>
                            <Select value={data.gender} onValueChange={v => setData('gender', v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select gender" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Male">Male</SelectItem>
                                    <SelectItem value="Female">Female</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.gender} />
                        </div>
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
        </>
    );
}

EmployeeEdit.layout = {
    breadcrumbs: [
        { title: 'Employees', href: index() },
        { title: 'Edit Employee', href: index() },
    ],
};
```

- [ ] **Step 3: Build assets to verify no TypeScript errors**

```bash
npm run build
```

Expected: Builds successfully with no TypeScript errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/employees/create.tsx resources/js/pages/employees/edit.tsx
git commit -m "feat: add employee_number and gender fields to employee forms"
```

---

## Task 4: Create Payslip Blade Partial

**Files:**
- Create: `resources/views/payslip/_card.blade.php`

- [ ] **Step 1: Create the directory**

```bash
mkdir resources/views/payslip
```

- [ ] **Step 2: Create `resources/views/payslip/_card.blade.php`**

```blade
{{-- Variables expected: $entry (PayrollEntry), $employee (Employee), $run (PayrollRun) --}}
<table style="width:100%;border-collapse:collapse;">
    <tr>
        <td style="width:64px;vertical-align:middle;padding-right:6px;">
            <img src="{{ public_path('images/logo.png') }}" alt="Logo" style="width:60px;height:auto;" onerror="this.style.display='none'">
        </td>
        <td style="text-align:center;vertical-align:middle;">
            <div style="font-weight:bold;font-size:1.1em;">Beulah Information Technology Services and Business Solutions Inc.</div>
            <div style="font-weight:bold;">
                Payslip for the month of
                {{ $run->period_start->format('F') }}
                {{ $run->period_start->format('d') }}-{{ $run->period_end->format('d') }},
                {{ $run->period_start->format('Y') }}
            </div>
        </td>
    </tr>
</table>

<table style="width:100%;border-collapse:collapse;margin-top:5px;">
    <tr>
        <td style="width:55%;padding:1px 0;"><strong>Employee Name:</strong> {{ strtoupper($employee->name) }}</td>
        <td style="padding:1px 0;"><strong>Paid Days:</strong> {{ $run->period_start->format('M d') }} - {{ $run->period_end->format('M d') }}</td>
    </tr>
    <tr>
        <td style="padding:1px 0;"><strong>ID Number:</strong> {{ $employee->employee_number ?? '' }}</td>
        <td style="padding:1px 0;"><strong>Days Present:</strong> {{ $entry->days_present }}</td>
    </tr>
    <tr>
        <td style="padding:1px 0;"><strong>Gender:</strong> {{ $employee->gender ?? '' }}</td>
        <td style="padding:1px 0;"><strong>Rate:</strong> {{ number_format($employee->daily_rate, 2) }}</td>
    </tr>
</table>

<table style="width:100%;border-collapse:collapse;margin-top:6px;">
    <thead>
        <tr style="background:#eeeeee;">
            <th style="border:1px solid #333;padding:3px 5px;text-align:left;">Earnings</th>
            <th style="border:1px solid #333;padding:3px 5px;text-align:right;">Amount</th>
            <th style="border:1px solid #333;padding:3px 5px;text-align:left;">Deductions</th>
            <th style="border:1px solid #333;padding:3px 5px;text-align:right;">Amount</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="border:1px solid #333;padding:3px 5px;">Basic Pay:</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;">{{ number_format($entry->total_basic_pay, 2) }}</td>
            <td style="border:1px solid #333;padding:3px 5px;">Cash Advance</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;">{{ $entry->cash_advance > 0 ? number_format($entry->cash_advance, 2) : '' }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #333;padding:3px 5px;">Overtime</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;">{{ $entry->overtime_pay > 0 ? number_format($entry->overtime_pay, 2) : '' }}</td>
            <td style="border:1px solid #333;padding:3px 5px;">Late</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;">{{ $entry->late_deduction > 0 ? number_format($entry->late_deduction, 2) : '' }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #333;padding:3px 5px;">Holiday Adjustment</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;">{{ $entry->holiday_pay > 0 ? number_format($entry->holiday_pay, 2) : '' }}</td>
            <td style="border:1px solid #333;padding:3px 5px;">Undertime</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;">{{ $entry->undertime_deduction > 0 ? number_format($entry->undertime_deduction, 2) : '' }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #333;padding:3px 5px;"></td>
            <td style="border:1px solid #333;padding:3px 5px;"></td>
            <td style="border:1px solid #333;padding:3px 5px;">Others</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;">{{ $entry->other_deductions > 0 ? number_format($entry->other_deductions, 2) : '' }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #333;padding:3px 5px;font-weight:bold;">Gross Salary:</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;font-weight:bold;">{{ number_format($entry->gross_pay, 2) }}</td>
            <td style="border:1px solid #333;padding:3px 5px;font-weight:bold;">Total Deductions</td>
            <td style="border:1px solid #333;padding:3px 5px;text-align:right;font-weight:bold;">{{ number_format($entry->total_deductions, 2) }}</td>
        </tr>
        <tr>
            <td colspan="2" style="border:1px solid #333;padding:3px 5px;font-weight:bold;">
                Net Pay:
                <span style="float:right;">&#8369;{{ number_format($entry->net_pay, 2) }}</span>
            </td>
            <td colspan="2" style="border:1px solid #333;padding:3px 5px;"></td>
        </tr>
    </tbody>
</table>

<p style="margin-top:6px;font-size:0.85em;">
    Note: Full details of your pay for the covered period are given above. Please check carefully and notify HR of any discrepancies.
</p>

<table style="width:100%;margin-top:24px;">
    <tr>
        <td style="width:42%;text-align:center;">
            Prepared by:
            <div style="border-top:1px solid #333;margin-top:22px;padding-top:2px;">Human Resource</div>
        </td>
        <td style="width:16%;"></td>
        <td style="width:42%;text-align:center;">
            Approved by:
            <div style="border-top:1px solid #333;margin-top:22px;padding-top:2px;">Manager</div>
        </td>
    </tr>
</table>
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/payslip/
git commit -m "feat: add shared payslip card partial"
```

---

## Task 5: Update Individual PDF Template (payslip.blade.php)

**Files:**
- Modify: `resources/views/payslip.blade.php`

- [ ] **Step 1: Replace `resources/views/payslip.blade.php`**

```blade
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; }
    body {
        font-family: Arial, sans-serif;
        font-size: 11px;
        margin: 0;
        padding: 15mm;
        color: #111;
    }
</style>
</head>
<body>
    @include('payslip._card', [
        'entry'    => $entry,
        'employee' => $employee,
        'run'      => $run,
    ])
</body>
</html>
```

- [ ] **Step 2: Update PayslipController download to set A4 paper**

In `app/Http/Controllers/PayslipController.php`, update the `download` method's PDF load call:

```php
$pdf = Pdf::loadView('payslip', [
    'entry'    => $payrollEntry,
    'employee' => $payrollEntry->employee,
    'run'      => $payrollEntry->payrollRun,
])->setPaper('a4', 'portrait');
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/payslip.blade.php app/Http/Controllers/PayslipController.php
git commit -m "feat: redesign individual payslip PDF template using shared card partial"
```

---

## Task 6: Create Batch Print Template (payslip-batch.blade.php)

**Files:**
- Create: `resources/views/payslip-batch.blade.php`

- [ ] **Step 1: Create `resources/views/payslip-batch.blade.php`**

```blade
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Payslips — {{ $run->period_start->format('M d') }}–{{ $run->period_end->format('M d, Y') }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: Arial, sans-serif;
        font-size: 9px;
        background: #f5f5f5;
        color: #111;
    }

    /* --- Screen: print button bar --- */
    #print-bar {
        position: fixed;
        top: 0; left: 0; right: 0;
        background: #1e293b;
        color: #fff;
        padding: 10px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        z-index: 100;
        font-size: 13px;
    }
    #print-bar button {
        background: #3b82f6;
        color: #fff;
        border: none;
        padding: 8px 20px;
        border-radius: 4px;
        font-size: 13px;
        cursor: pointer;
    }
    #print-bar button:hover { background: #2563eb; }

    /* --- Screen: page wrapper --- */
    .page-wrapper {
        margin-top: 52px;
        padding: 10px;
    }

    /* --- A4 page simulation on screen --- */
    .a4-page {
        width: 210mm;
        min-height: 297mm;
        background: #fff;
        margin: 0 auto 16px auto;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-template-rows: 1fr 1fr;
        gap: 0;
    }

    /* --- Each payslip cell --- */
    .payslip-cell {
        width: 105mm;
        height: 148.5mm;
        padding: 6mm;
        border: 1px dashed #ccc;
        overflow: hidden;
        page-break-inside: avoid;
    }

    /* --- Print styles --- */
    @media print {
        @page { size: A4 portrait; margin: 0; }

        body { background: #fff; font-size: 9px; }

        #print-bar { display: none; }

        .page-wrapper { margin-top: 0; padding: 0; }

        .a4-page {
            width: 210mm;
            height: 297mm;
            margin: 0;
            box-shadow: none;
            page-break-after: always;
        }

        .a4-page:last-child { page-break-after: auto; }

        .payslip-cell {
            border: 0.5pt solid #999;
        }
    }
</style>
</head>
<body>

<div id="print-bar">
    <span>
        Payslips &mdash; {{ $run->period_start->format('M d') }}–{{ $run->period_end->format('M d, Y') }}
        ({{ $entries->count() }} {{ Str::plural('employee', $entries->count()) }})
    </span>
    <button onclick="window.print()">&#128438; Print All</button>
</div>

<div class="page-wrapper">
    @foreach ($entries->chunk(4) as $pageEntries)
        <div class="a4-page">
            @foreach ($pageEntries as $entry)
                <div class="payslip-cell">
                    @include('payslip._card', [
                        'entry'    => $entry,
                        'employee' => $entry->employee,
                        'run'      => $run,
                    ])
                </div>
            @endforeach
            {{-- Fill empty cells so grid stays 2×2 --}}
            @for ($i = $pageEntries->count(); $i < 4; $i++)
                <div class="payslip-cell"></div>
            @endfor
        </div>
    @endforeach
</div>

</body>
</html>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/payslip-batch.blade.php
git commit -m "feat: add batch print template (4 payslips per A4)"
```

---

## Task 7: Add printAll to PayslipController and Register Route

**Files:**
- Modify: `app/Http/Controllers/PayslipController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/PayslipPrintTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PayslipPrintTest.php`:

```php
<?php

use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\User;

test('print all payslips returns 200 for locked run', function () {
    $user = User::factory()->create();
    $run = PayrollRun::factory()->locked()->create();
    $employee = Employee::factory()->create();
    PayrollEntry::factory()->for($run)->for($employee)->create();

    $this->actingAs($user)
        ->get("/payroll-runs/{$run->id}/payslips/print")
        ->assertOk()
        ->assertSee('Print All');
});

test('print all payslips returns 403 for draft run', function () {
    $user = User::factory()->create();
    $run = PayrollRun::factory()->draft()->create();

    $this->actingAs($user)
        ->get("/payroll-runs/{$run->id}/payslips/print")
        ->assertForbidden();
});

test('print all payslips requires authentication', function () {
    $run = PayrollRun::factory()->locked()->create();

    $this->get("/payroll-runs/{$run->id}/payslips/print")
        ->assertRedirect('/login');
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test tests/Feature/PayslipPrintTest.php
```

Expected: FAIL — route does not exist yet.

- [ ] **Step 3: Add `printAll` method to PayslipController**

Replace the entire contents of `app/Http/Controllers/PayslipController.php`:

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
            'entry'    => $payrollEntry,
            'employee' => $payrollEntry->employee,
            'run'      => $payrollEntry->payrollRun,
        ])->setPaper('a4', 'portrait');

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
                'entry'    => $entry,
                'employee' => $entry->employee,
                'run'      => $payrollRun,
            ])->setPaper('a4', 'portrait');

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

        return view('payslip-batch', [
            'run'     => $payrollRun,
            'entries' => $payrollRun->entries,
        ]);
    }
}
```

- [ ] **Step 4: Register the new route in `routes/web.php`**

Add the following line after the existing `payslip.download-all` route:

```php
Route::get('payroll-runs/{payrollRun}/payslips/print', [PayslipController::class, 'printAll'])->name('payslip.print-all');
```

The routes block should now include:

```php
Route::get('payroll-entries/{payrollEntry}/payslip', [PayslipController::class, 'download'])->name('payslip.download');
Route::get('payroll-runs/{payrollRun}/payslips/download-all', [PayslipController::class, 'downloadAll'])->name('payslip.download-all');
Route::get('payroll-runs/{payrollRun}/payslips/print', [PayslipController::class, 'printAll'])->name('payslip.print-all');
```

- [ ] **Step 5: Check if factories exist, create stubs if needed**

```bash
php artisan test tests/Feature/PayslipPrintTest.php
```

If the test fails with "Call to undefined method PayrollRun::factory()", run:

```bash
php artisan make:factory PayrollRunFactory --model=PayrollRun
php artisan make:factory PayrollEntryFactory --model=PayrollEntry
php artisan make:factory EmployeeFactory --model=Employee
```

Then fill in `database/factories/PayrollRunFactory.php`:

```php
<?php
namespace Database\Factories;

use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollRunFactory extends Factory
{
    protected $model = PayrollRun::class;

    public function definition(): array
    {
        return [
            'period_start'  => '2026-05-01',
            'period_end'    => '2026-05-15',
            'payable_date'  => '2026-05-16',
            'status'        => 'draft',
        ];
    }

    public function locked(): static
    {
        return $this->state(['status' => 'locked']);
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }
}
```

Fill in `database/factories/EmployeeFactory.php`:

```php
<?php
namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'name'            => $this->faker->name(),
            'employee_number' => 'EMP-' . $this->faker->numerify('###'),
            'gender'          => $this->faker->randomElement(['Male', 'Female']),
            'department'      => $this->faker->randomElement(['IT', 'HR', 'Finance']),
            'daily_rate'      => 700.00,
            'shift_start'     => '08:00:00',
            'shift_end'       => '17:00:00',
            'is_active'       => true,
        ];
    }
}
```

Fill in `database/factories/PayrollEntryFactory.php`:

```php
<?php
namespace Database\Factories;

use App\Models\PayrollEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollEntryFactory extends Factory
{
    protected $model = PayrollEntry::class;

    public function definition(): array
    {
        return [
            'days_present'        => 13,
            'total_basic_pay'     => 9100.00,
            'overtime_minutes'    => 0,
            'overtime_pay'        => 0.00,
            'late_minutes'        => 0,
            'late_deduction'      => 0.00,
            'undertime_minutes'   => 0,
            'undertime_deduction' => 0.00,
            'holiday_pay'         => 0.00,
            'gross_pay'           => 9100.00,
            'cash_advance'        => 0.00,
            'other_deductions'    => 0.00,
            'total_deductions'    => 0.00,
            'net_pay'             => 9100.00,
            'first_release'       => 0.00,
            'second_release'      => 0.00,
        ];
    }
}
```

- [ ] **Step 6: Run the tests to verify they pass**

```bash
php artisan test tests/Feature/PayslipPrintTest.php
```

Expected: All 3 tests PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/PayslipController.php routes/web.php tests/Feature/PayslipPrintTest.php database/factories/
git commit -m "feat: add printAll batch view route and update PayslipController"
```

---

## Task 8: Add Print Button to Payroll Show Page

**Files:**
- Modify: `resources/js/pages/payroll/show.tsx`

- [ ] **Step 1: Add the "Print All Payslips" button**

In `resources/js/pages/payroll/show.tsx`, locate the locked buttons block (lines 89–96) and add the new button after the existing "Download All Payslips" button:

```tsx
{isLocked && (
    <>
        <Button onClick={downloadAllSlips}>Download All Payslips</Button>
        <Button
            variant="outline"
            onClick={() => window.open(`/payroll-runs/${run.id}/payslips/print`, '_blank')}
        >
            Print All Payslips
        </Button>
        <Button variant="outline"
            onClick={() => window.open(`/payroll-runs/${run.id}/export`, '_blank')}>
            Export Excel
        </Button>
    </>
)}
```

- [ ] **Step 2: Build assets**

```bash
npm run build
```

Expected: Builds successfully with no errors.

- [ ] **Step 3: Run all tests**

```bash
php artisan test
```

Expected: All tests PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/payroll/show.tsx
git commit -m "feat: add Print All Payslips button to payroll show page"
```

---

## Task 9: Smoke Test the Full Flow

This is a manual verification task — no automated test needed.

- [ ] **Step 1: Start the dev server**

```bash
composer run dev
```

- [ ] **Step 2: Create an employee with new fields**
  - Navigate to `/employees/create`
  - Fill in Name, Employee Number (e.g. "EMP-001"), Gender (Male), Department, Daily Rate, Shift times
  - Save — verify the employee appears in the list

- [ ] **Step 3: Edit the employee and verify fields pre-fill**
  - Click edit on the employee
  - Verify Employee Number and Gender are pre-filled correctly

- [ ] **Step 4: Test the print batch view**
  - Navigate to a locked payroll run
  - Click "Print All Payslips"
  - Verify a new tab opens showing the payslips in a 2×2 grid
  - Verify each payslip shows: employee name, ID number, gender, paid period, days present, rate, earnings/deductions table, net pay, signature lines
  - Open browser print dialog (Ctrl+P) — verify "Print All" button disappears and payslips fit 4-per-A4

- [ ] **Step 5: Test the individual PDF download**
  - From the payroll summary table, click an individual employee's payslip download
  - Verify the PDF opens as full A4 with the updated layout including ID number and gender

- [ ] **Step 6: Commit any fixes found during smoke test, then tag complete**

```bash
git add -p
git commit -m "fix: smoke test corrections"
```

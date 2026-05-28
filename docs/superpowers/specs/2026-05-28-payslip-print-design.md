# Payslip Print & PDF Design

**Date:** 2026-05-28  
**Status:** Approved

---

## Overview

Enhance the payslip system to support two output modes:

1. **Batch browser-print view** — a single page that renders all payslips for a payroll run in a 2×2 A6 grid per A4 sheet, printable via `Ctrl+P` / browser print dialog.
2. **Individual full-A4 PDF download** — the existing DomPDF-based download, with a redesigned template that matches the company layout and includes newly added employee fields.

The batch print view is the primary way management prints the whole payroll run. Individual PDFs are used for archiving or emailing a single employee's payslip.

---

## Database Changes

### Migration: `add_employee_number_and_gender_to_employees`

Add two nullable columns to the `employees` table:

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `employee_number` | `string` | yes | e.g. "EMP-001" |
| `gender` | `string` | yes | "Male" or "Female" |

### Model update: `App\Models\Employee`

Add `employee_number` and `gender` to `$fillable`.

---

## Routes

| Method | URI | Controller | Name | Notes |
|--------|-----|------------|------|-------|
| GET | `/payroll-runs/{run}/payslips/print` | `PayslipController@printAll` | `payslips.print-all` | New — batch browser-print view |
| GET | `/payroll-entries/{entry}/payslip` | `PayslipController@download` | `payslips.download` | Existing — individual PDF (A4) |
| GET | `/payroll-runs/{run}/payslips/download-all` | `PayslipController@downloadAll` | `payslips.download-all` | Existing — ZIP of all PDFs |

Both existing routes are unchanged in behavior; only the PDF template is redesigned.

---

## Blade Templates

### `resources/views/payslip/_card.blade.php` (new partial)

Receives variables: `$entry` (PayrollEntry), `$employee` (Employee), `$run` (PayrollRun).

Content layout:
```
┌─────────────────────────────────────────────────────────┐
│  [LOGO]  Beulah Information Technology Services...       │
│          Payslip for the month of [Period]               │
├──────────────────────────┬──────────────────────────────┤
│ Employee Name: [NAME]    │ Paid Days: [start] - [end]   │
│ ID Number:    [NUMBER]   │ Days Present: [N]            │
│ Gender:       [GENDER]   │ Rate: [daily_rate]           │
├──────────────────────────┴──────────────────────────────┤
│ Earnings          Amount │ Deductions        Amount     │
│ Basic Pay:       9,100   │ Cash Advance                 │
│ Overtime:          111   │ Late:               53.65    │
│ Holiday Adj:             │ Undertime:               0   │
│                          │ Others:                      │
│ Gross Salary:    9,211   │ Total Deductions:   53.65    │
│ Net Pay:        ₱9,158   │                              │
├─────────────────────────────────────────────────────────┤
│ Note: Full details of your pay...                        │
├──────────────────────────┬──────────────────────────────┤
│ Prepared by:             │ Approved by:                 │
│ ________________________ │ ________________________     │
│      Human Resource      │        Manager               │
└─────────────────────────────────────────────────────────┘
```

### `resources/views/payslip.blade.php` (updated — individual PDF)

- Includes `_card` partial once.
- DomPDF paper: A4, portrait.
- Margins: 15mm all sides.
- Font size: 10px base, 12px for net pay.

### `resources/views/payslip-batch.blade.php` (new — batch browser print)

- Full standalone HTML document (no Laravel layout).
- Top bar with "Print All" button (`onclick="window.print()"`) — hidden by `@media print`.
- CSS grid: 2 columns × N rows, each cell is A6 size (105mm × 148.5mm).
- Border between cells for visual separation on screen; light dashed border on print.
- `page-break-inside: avoid` on each card.
- After every 4th card, `page-break-after: always` forces a new A4 sheet.
- Font size: 9px base to fit A6 footprint.

---

## Controller Changes

### `PayslipController`

**New method: `printAll(PayrollRun $payrollRun)`**
- Aborts with 403 if run is not locked.
- Eager-loads `entries.employee` and `payrollRun`.
- Returns `view('payslip-batch', ['run' => $payrollRun, 'entries' => $payrollRun->entries])`.

**Existing `download` method**
- Unchanged in logic.
- The Blade template it points to (`payslip`) is redesigned.

**Existing `downloadAll` method**
- Unchanged.

---

## Frontend Changes

### `resources/js/pages/payroll/show.tsx`

When the run is locked, add a **"Print All Payslips"** button that opens `/payroll-runs/{id}/payslips/print` in a new tab:

```tsx
<Button variant="outline" onClick={() => window.open(`/payroll-runs/${run.id}/payslips/print`, '_blank')}>
    Print All Payslips
</Button>
```

Placed next to the existing "Download All Payslips" button.

---

## Employee Form Changes

### `resources/js/pages/employees/create.tsx` and `edit.tsx`

Add two new fields to the form:

| Field | Type | Validation |
|-------|------|-----------|
| Employee Number | Text input | Optional |
| Gender | Select (Male / Female / blank) | Optional |

### `EmployeeController`

`store()` and `update()` validation rules gain:
- `employee_number` — `nullable|string|max:50`
- `gender` — `nullable|in:Male,Female`

---

## Acceptance Criteria

- [ ] Migration runs cleanly; `employees` table has `employee_number` and `gender` columns.
- [ ] Employee create/edit forms show the new fields and save correctly.
- [ ] Locked payroll run page shows "Print All Payslips" button.
- [ ] Clicking "Print All Payslips" opens a page with all employee payslips in a 2×2 A6 grid.
- [ ] Browser print dialog prints 4 payslips per A4 sheet with correct layout.
- [ ] "Print" button is hidden when printing.
- [ ] Individual PDF download produces a full A4 payslip with ID number and gender shown.
- [ ] Payslip shows correct: name, employee number, gender, paid period, days present, daily rate, basic pay, overtime, holiday pay, cash advance, late, undertime, other deductions, gross pay, total deductions, net pay.
- [ ] Blank HR and Manager signature lines appear at the bottom of every payslip.

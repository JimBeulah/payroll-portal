# Payroll System Design

**Date:** 2026-05-23
**Project:** payroll-portal
**Stack:** Laravel 12 + Inertia.js + React/TypeScript + shadcn/ui + SQLite

---

## Overview

A web-based payroll system for HR/Admin use only. HR uploads a semi-monthly attendance Excel file (exported from a biometric DTR), the system maps records to employee profiles, computes pay automatically, HR enters manual deductions, then locks the payroll run and generates downloadable PDF payslips.

---

## Modules

1. **Employee Management** — CRUD for employee profiles
2. **Holiday Management** — Maintain a list of public holidays with pay type
3. **Payroll Run** — Create pay period, upload attendance, compute, review, lock
4. **Payslip Generation** — PDF per employee, individual or bulk ZIP download
5. **Payroll Summary** — Table view of a locked run, exportable to Excel

---

## Data Models

### employees
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | Must match name in attendance Excel |
| department | string | |
| daily_rate | decimal(10,2) | |
| shift_start | time | e.g. 08:00 |
| shift_end | time | e.g. 17:00 |
| is_active | boolean | default true |
| timestamps | | |

### holidays
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | e.g. "Labor Day" |
| date | date | |
| type | enum | `regular` (2x pay) or `special` (1.3x pay) |
| timestamps | | |

### payroll_runs
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| period_start | date | e.g. 2026-05-01 |
| period_end | date | e.g. 2026-05-15 |
| payable_date | date | |
| status | enum | `draft` or `locked` |
| created_by | bigint FK → users | |
| timestamps | | |

### payroll_entries
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| payroll_run_id | bigint FK | |
| employee_id | bigint FK | |
| days_present | integer | |
| total_basic_pay | decimal(10,2) | days_present × daily_rate |
| overtime_minutes | integer | |
| overtime_pay | decimal(10,2) | |
| late_minutes | integer | |
| late_deduction | decimal(10,2) | |
| undertime_minutes | integer | |
| undertime_deduction | decimal(10,2) | |
| holiday_pay | decimal(10,2) | additional pay from holidays |
| gross_pay | decimal(10,2) | |
| cash_advance | decimal(10,2) | manually entered by HR |
| other_deductions | decimal(10,2) | manually entered by HR |
| total_deductions | decimal(10,2) | |
| net_pay | decimal(10,2) | |
| first_release | decimal(10,2) | manually entered by HR |
| second_release | decimal(10,2) | manually entered by HR |
| timestamps | | |

### attendance_uploads
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| payroll_run_id | bigint FK | |
| filename | string | original filename |
| uploaded_at | timestamp | |

---

## Payroll Computation Logic

### Per-minute rate
```
per_minute_rate = daily_rate / 8 / 60
```

### Per attendance day
For each date column in the uploaded Excel:
- **Absent** — SW/EW cell is `----`: no pay for that day
- **Present** — SW and EW times are recorded:
  - `late_minutes = max(0, actual_start - shift_start)` in minutes
  - `undertime_minutes = max(0, shift_end - actual_end)` in minutes
  - `overtime_minutes = max(0, actual_end - shift_end)` in minutes
  - All computed to the exact minute

### Holiday pay
- Check each present day against the holidays table
- **Regular holiday** (`regular`): day pay = daily_rate × 2 (so additional = daily_rate × 1)
- **Special holiday** (`special`): day pay = daily_rate × 1.3 (so additional = daily_rate × 0.3)
- `holiday_pay` = sum of all holiday adjustments across the period

### Totals
```
total_basic_pay  = days_present × daily_rate
overtime_pay     = total_overtime_minutes × per_minute_rate
late_deduction   = total_late_minutes × per_minute_rate
undertime_ded    = total_undertime_minutes × per_minute_rate
gross_pay        = total_basic_pay + overtime_pay + holiday_pay
                   - late_deduction - undertime_deduction
total_deductions = cash_advance + other_deductions
net_pay          = gross_pay - total_deductions
balance          = net_pay - first_release - second_release
```

---

## Attendance Excel Format

Fixed format exported from biometric DTR:
- Row 3: `Create Time: YYYY-MM-DD  SW:Start-Work|EW:End-`
- Row 4–5: Header rows with date columns (each date has a `SW - EW` sub-column)
- Row 6+: Employee rows — columns: Employee ID, Card No., Name, Department, then daily SW/EW pairs
- Absent days show `---- ----` pattern
- Name and Department columns are used to match to the `employees` table

Unmatched employees (name not found in system) are flagged for HR to resolve before computing.

---

## UI Flow

### Sidebar Navigation
- Dashboard
- Employees
- Holidays
- Payroll Runs
- Settings

### Payroll Run Workflow
1. **New Run** — HR enters `period_start`, `period_end`, `payable_date`
2. **Upload Attendance** — HR uploads Excel; system parses and shows preview table; unmatched employees are highlighted in red with a warning
3. **Compute** — HR clicks Compute; system calculates all payroll_entries and saves to DB as `draft`
4. **Review & Adjust** — HR sees payroll summary table; clicks each row to enter `cash_advance`, `other_deductions`, `first_release`, `second_release`
5. **Lock Run** — HR locks the run; status becomes `locked`; no further edits allowed
6. **Payslip Actions** (from locked run):
   - Download individual PDF payslip per employee
   - Download all payslips as ZIP
   - Export payroll summary as Excel

### Payslip PDF Layout
Matches the sample format:
- Company header (Beulah Information Technology Services and Business Solutions Inc.)
- Employee name, pay period, days present, daily rate
- Earnings: Basic Pay, Overtime, Holiday Adjustment
- Deductions: Cash Advance, Late, Undertime, Others
- Gross Salary, Net Pay
- Signature lines: Human Resource, Manager, Approved by

---

## Key Constraints

- Overtime, late, and undertime are computed to the exact minute — no minimum threshold
- Working hours base is always 8 hours regardless of employee schedule
- Employee matching uses the **Name** column from the Excel; names must match exactly
- A payroll run can only be locked once; locked runs are read-only
- Payslips can be regenerated any time from a locked run
- **Absent on a holiday** → no holiday premium (employee must be present to earn holiday pay)
- `balance` (net_pay - first_release - second_release) is computed on the fly, not stored in DB
- **Incentives (POS)** shown in the payslip sample is out of scope for the initial build

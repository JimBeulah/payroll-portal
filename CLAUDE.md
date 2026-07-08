# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Payroll Portal** is a Laravel 13 + React 19 web application for managing employee payroll, including payroll runs, employee management, attendance tracking, and payslip generation. It uses Inertia.js to seamlessly integrate a React frontend with a Laravel backend.

**Tech Stack:**
- Backend: Laravel 13, PHP 8.3
- Frontend: React 19, TypeScript, Tailwind CSS 4
- Build: Vite 8
- Database: SQLite (default, supports MySQL/PostgreSQL via .env)
- UI Components: Radix UI (accessible primitives), custom shadcn/ui-style components
- Charts: Recharts
- PDF Generation: DomPDF
- Excel Export: PHPSpreadsheet
- Authentication: Laravel Fortify (built-in user management, passkeys, 2FA)
- Authorization: Role-based access control (admin, hr, employee)

## Development Commands

### Setup & Installation
```bash
# One-time setup
composer run-script setup

# Or manually:
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

### Running the Application
```bash
# Full development stack (Laravel server, queue, Vite HMR)
composer run dev

# Or individually:
php artisan serve --host=localhost  # Laravel server (http://localhost:8000)
npm run dev                          # Vite dev server (auto HMR)
php artisan queue:listen             # Background job processor
```

### Code Quality
```bash
# Check code issues (lint, format, types, tests)
composer run ci:check

# Format/fix code issues
npm run format                       # Format JS/TS files
npm run lint                         # Fix linting issues
php artisan pint                     # Fix PHP style issues

# Type checking
npm run types:check                  # TypeScript checking
```

### Testing
```bash
# Run all tests
composer run test

# Run tests only (without lint/format checks)
php artisan test

# Run specific test
php artisan test tests/Feature/PayrollComputeTest.php
```

### Building for Production
```bash
npm run build                        # Build frontend (outputs to public/build/)
# No additional backend build needed - Laravel uses pre-built assets
```

## Architecture Overview

### Backend (Laravel)

**Directory Structure:**
- `app/Http/Controllers/` - Request handlers (payroll, employees, holidays, requests, approvals)
  - Admin/HR routes: Payroll, employees, holidays, attendance, request approvals
  - Employee routes: Cash advance & leave requests
- `app/Http/Middleware/` - Middleware (auth guards, role checks, Inertia setup)
  - `EnsureUserHasRole` - Role-based access control via `middleware('role:admin,hr')`
- `app/Models/` - Eloquent models (User, Employee, PayrollRun, PayrollEntry, CashAdvanceRequest, LeaveRequest, AttendanceUpload, Holiday, AppSetting)
- `app/Actions/` - Reusable business logic (separated from controllers)
- `app/Services/` - Domain services (PayrollCalculator, AttendanceParser, PayrollExportService)
- `app/Concerns/` - Shared traits for models/classes
- `routes/web.php` - All web routes (protected by `auth` + `verified` middleware)
- `database/migrations/` - Schema definitions
- `database/factories/` - Model factories for testing/seeding
- `database/seeders/` - Database seeders

**Key Models:**
- **User** - Authentication via Fortify (supports passkeys, 2FA); has `role` field (admin, hr, employee)
- **Employee** - Store employee info, rates, deductions; linked to User via `hasOne` relationship
- **PayrollRun** - Group of payroll entries for a specific pay period
- **PayrollEntry** - Individual employee payroll calculation (linked to PayrollRun)
- **AttendanceUpload** - CSV/Excel uploads for attendance data
- **PayrollManualAttendance** - Manual attendance overrides for specific dates
- **Holiday** - Public holidays for payroll calculations
- **CashAdvanceRequest** - Employee cash advance requests (pending/approved/rejected)
- **LeaveRequest** - Employee leave requests (pending/approved/rejected)
- **AppSetting** - Application configuration (company logo, name, payslip settings)

**Key Routes (all protected by auth):**

*Employee Self-Service (any authenticated user):*
- `GET /my-requests` - Employee request portal (cash advance & leave)
- `POST /my-requests/cash-advance` - Submit cash advance request
- `POST /my-requests/leave` - Submit leave request

*Admin/HR Only (requires `role:admin,hr`):*
- `POST /payroll-runs/{id}/compute` - Calculate payroll entries
- `POST /payroll-runs/{id}/lock` - Lock payroll run (prevent edits)
- `POST /payroll-runs/{id}/unlock` - Unlock payroll run
- `GET /payroll-entries/{id}/payslip` - Download individual PDF payslip
- `GET /payroll-runs/{id}/payslips/download-all` - Zip of all payslips
- `GET /payroll-runs/{id}/payslips/print` - Batch print view (4 payslips per A4)
- `GET /payroll-runs/{id}/export` - Export payroll data to Excel
- `GET /approvals` - View & manage pending cash advance & leave requests
- `POST /approvals/cash-advance/{id}/approve` - Approve cash advance
- `POST /approvals/cash-advance/{id}/reject` - Reject cash advance
- `POST /approvals/leave/{id}/approve` - Approve leave request
- `POST /approvals/leave/{id}/reject` - Reject leave request

### Frontend (React + Inertia.js)

**Inertia.js Pattern:** React pages (in `resources/js/pages/`) receive server-side props from Laravel controllers via `Inertia::render()`. No separate API — routing and data happen via traditional form submissions and HTTP requests.

**Directory Structure:**
- `resources/js/pages/` - Inertia page components (one per route, auto-discovered by Wayfinder)
  - `auth/` - Login, register, 2FA, password reset
  - `dashboard.tsx` - Landing page (redirects employees to `/my-requests`)
  - `requests/` - Employee self-service (cash advance & leave requests)
  - `approvals/` - Admin/HR request approval dashboard
  - `payroll/`, `employees/`, `holidays/` - Admin/HR management pages
  - `settings/` - Profile, security, company, appearance settings
- `resources/js/components/` - Reusable UI components (forms, modals, tables, etc.)
- `resources/js/layouts/` - Page layouts (app layout with navigation, auth layout)
- `resources/js/hooks/` - Custom React hooks
- `resources/js/actions/` - Server actions / form handlers
- `resources/js/types/` - TypeScript type definitions
- `resources/js/lib/` - Utility functions and helpers
- `resources/js/wayfinder/` - Route definitions (auto-generated by Wayfinder)
- `resources/css/app.css` - Global Tailwind CSS

**Key Libraries:**
- **@inertiajs/react** - Inertia.js React adapter
- **Radix UI** - Accessible UI primitives (@radix-ui/*)
- **Tailwind CSS 4** - Utility-first CSS
- **Recharts** - Charts (dashboard graphs)
- **Sonner** - Toast notifications
- **Lucide React** - Icon library
- **React Compiler** - Automatic React optimization (via babel plugin)

**Component Patterns:**
- Modal components (Dialog, Form inputs, etc.) from Radix UI + custom styling
- Data tables with sorting, pagination, and filtering (recharts for charts)
- Form components using Inertia form helpers (InertiaForm)
- Page transitions via Inertia (no loading spinners needed by default)

## Role-Based Access Control

**User Roles:**
- **admin** - Full system access (payroll, employees, holidays, request approvals)
- **hr** - HR operations (same as admin for payroll & request management)
- **employee** - Self-service only (submit cash advance & leave requests, view own requests)

**Implementation:**
- User model has `role` column (stored in DB, not via separate `roles` table)
- `App\Http\Middleware\EnsureUserHasRole` guards admin/HR routes
- Usage: `Route::middleware('role:admin,hr')->group(function () { ... })`
- Employees redirected to `/my-requests` from dashboard if they lack payroll access

**Dashboard Behavior:**
- All authenticated users land on `/dashboard` (generic landing page)
- Controller redirects employees → `/my-requests` (self-service portal)
- Admin/HR stay on dashboard (see overview/stats)

## Employee Request Management

Employees can submit and track requests; admins/HR approve/reject them.

**Cash Advance Requests:**
- Employee submits via `/my-requests` (amount, reason)
- Status: pending → approved/rejected
- Admin/HR view & action via `/approvals`

**Leave Requests:**
- Employee submits via `/my-requests` (start date, end date, reason)
- Status: pending → approved/rejected
- Admin/HR view & action via `/approvals`

**Models:**
- `CashAdvanceRequest` - has employee_id, amount, reason, status, approval_notes
- `LeaveRequest` - has employee_id, start_date, end_date, reason, status, approval_notes

**Routes (request handling):**
- `POST /my-requests/cash-advance` - Create cash advance (employee)
- `POST /my-requests/leave` - Create leave request (employee)
- `POST /approvals/cash-advance/{id}/approve` - Approve (admin/HR)
- `POST /approvals/cash-advance/{id}/reject` - Reject (admin/HR)
- `POST /approvals/leave/{id}/approve` - Approve (admin/HR)
- `POST /approvals/leave/{id}/reject` - Reject (admin/HR)

## Database

**Connection:** SQLite by default (see `.env` — `DB_CONNECTION=sqlite`)

**Key Tables:**
- `users` - User accounts
- `employees` - Employee records with rates, deductions, etc.
- `payroll_runs` - Payroll periods (e.g., "May 2025")
- `payroll_entries` - Calculated payroll for each employee in a run
- `attendance_uploads` - Uploaded attendance CSV/Excel files
- `holidays` - Public holidays for deduction calculations
- `app_settings` - App config (company logo, payslip footer, etc.)

**Running Migrations:**
```bash
php artisan migrate              # Run all pending migrations
php artisan migrate:rollback     # Rollback last batch
php artisan migrate:fresh --seed # Reset DB and run seeders
```

## Common Workflows

### Adding a New Page/Feature
1. Create page component in `resources/js/pages/` (auto-discovered by Wayfinder)
2. Add controller method in `app/Http/Controllers/` that calls `Inertia::render('PageName', $props)`
3. Add route in `routes/web.php`
4. TypeScript types for props go in `resources/js/types/`

### Modifying Payroll Calculation
- Logic lives in `app/Services/` or `app/Actions/`
- Payroll entries are computed and stored in DB (not real-time)
- Edit `PayrollEntryController@update` or computation service to change how fields are calculated

### PDF/Excel Generation
- Payslips: `app/Http/Controllers/PayslipController` (uses DomPDF + shared `payslip-card.blade.php` partial)
- Exports: `app/Http/Controllers/PayrollExportController` (uses PHPSpreadsheet)
- Templates in `resources/views/` (Blade templates)

### Attendance Upload & Processing
- `AttendanceUploadController@store` handles CSV/Excel upload
- Parsed data linked to `PayrollEntry` records
- Queue job processes uploads if async handling needed

## Important Notes

- **Inertia Refresh:** After CRUD operations, use `redirect()` or `Inertia::location()` to reload page — don't manually refetch
- **Auth:** All routes in `web.php` require `auth` + `verified` middleware
- **Roles:** Admin/HR routes guarded by `middleware('role:admin,hr')` — employees are denied access and stay on employee portal
- **Employee Portal:** Employees with a linked `Employee` record can submit cash advance & leave requests; view their request status in `/my-requests`
- **Dashboard:** Redirects employees away from payroll dashboard to `/my-requests` (self-service only)
- **Queue:** Background jobs use database driver by default (not production-ready) — switch to Redis/Beanstalkd for production
- **CSV Parsing:** Attendance uploads are parsed with configurable column mappings (stored in `AppSetting`)
- **Payslip Lock:** Once a payroll run is locked, payroll entries cannot be edited — unlock feature available
- **React Compiler:** Enabled via `babel-plugin-react-compiler` for automatic optimization; no code changes needed

## Git Workflow

**Branch Strategy:** Main branch is `main`. Feature branches follow `feature/*` or `fix/*` naming.

**Recent Work:** Role-based access control (admin/hr/employee), employee request management (cash advance & leave), request approvals, and employee self-service portal.

## Type Definitions

TypeScript strict mode enabled. Key types:
- `PageProps` - Props passed from controller to React page
- `FormData` - Form submission data (Inertia form helpers)
- `Employee`, `PayrollRun`, `PayrollEntry`, etc. - Model types in `resources/js/types/`

## Vite Configuration

- **Input:** `resources/css/app.css` + `resources/js/app.tsx`
- **Output:** `public/build/` (gitignored)
- **Plugins:** Inertia, React, Tailwind CSS, Wayfinder (auto-discovers pages)
- **HMR:** Enabled in dev mode for fast refresh

## Environment Variables

See `.env.example`. Key variables:
- `APP_NAME` - Application name (Payroll Portal)
- `DB_CONNECTION` - Database type (sqlite, mysql, pgsql)
- `SESSION_DRIVER` - Session storage (database)
- `QUEUE_CONNECTION` - Queue driver (database)
- `MAIL_MAILER` - Mail driver (log for dev)

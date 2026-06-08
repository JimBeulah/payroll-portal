<div align="center">

```
██████╗  █████╗ ██╗   ██╗██████╗  ██████╗ ██╗     ██╗
██╔══██╗██╔══██╗╚██╗ ██╔╝██╔══██╗██╔═══██╗██║     ██║
██████╔╝███████║ ╚████╔╝ ██████╔╝██║   ██║██║     ██║
██╔═══╝ ██╔══██║  ╚██╔╝  ██╔══██╗██║   ██║██║     ██║
██║     ██║  ██║   ██║   ██║  ██║╚██████╔╝███████╗███████╗
╚═╝     ╚═╝  ╚═╝   ╚═╝   ╚═╝  ╚═╝ ╚═════╝ ╚══════╝╚══════╝

██████╗  ██████╗ ██████╗ ████████╗ █████╗ ██╗
██╔══██╗██╔═══██╗██╔══██╗╚══██╔══╝██╔══██╗██║
██████╔╝██║   ██║██████╔╝   ██║   ███████║██║
██╔═══╝ ██║   ██║██╔══██╗   ██║   ██╔══██║██║
██║     ╚██████╔╝██║  ██║   ██║   ██║  ██║███████╗
╚═╝      ╚═════╝ ╚═╝  ╚═╝   ╚═╝   ╚═╝  ╚═╝╚══════╝
```

**Employee payroll, done right.**

[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![React](https://img.shields.io/badge/React-19-61DAFB?style=for-the-badge&logo=react&logoColor=black)](https://react.dev)
[![TypeScript](https://img.shields.io/badge/TypeScript-5.7-3178C6?style=for-the-badge&logo=typescript&logoColor=white)](https://typescriptlang.org)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Vite](https://img.shields.io/badge/Vite-8-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vitejs.dev)
[![License: MIT](https://img.shields.io/badge/License-MIT-22C55E?style=for-the-badge)](LICENSE)

</div>

---

## ✨ What is Payroll Portal?

A full-featured web application for managing employee payroll — from attendance uploads to locked payslip PDFs. Built with **Laravel 13** and **React 19**, bridged by Inertia.js for a seamless SPA experience without a separate API layer.

### 🚀 Features at a Glance

| Feature | Description |
|---|---|
| 👥 **Employee Management** | Daily rates, shift schedules, departments, soft delete |
| 📅 **Payroll Runs** | Group entries by pay period; lock when final |
| 🕐 **Attendance Tracking** | Upload CSV/Excel files or enter records manually |
| 🧮 **Auto-Computation** | Basic pay, overtime, late/undertime, holiday pay |
| 🧾 **Payslip Generation** | Individual PDF, batch ZIP, or 4-per-page print layout |
| 📊 **Excel Export** | Full payroll run data to `.xlsx` |
| 🗓️ **Holiday Calendar** | Regular (2×) and special (1.3×) public holidays |
| ⚙️ **App Settings** | Company name, logo, payslip footer |
| 🔑 **Passkeys** | WebAuthn passwordless auth via Laravel Passkeys |
| 🔐 **Two-Factor Auth** | TOTP-based 2FA via Laravel Fortify |

---

## 📋 Table of Contents

- [Tech Stack](#-tech-stack)
- [Prerequisites](#-prerequisites)
- [Getting Started](#-getting-started)
- [Architecture Overview](#-architecture-overview)
- [Environment Variables](#-environment-variables)
- [Available Scripts](#-available-scripts)
- [Testing](#-testing)
- [Payroll Calculation Logic](#-payroll-calculation-logic)
- [Deployment](#-deployment)
- [Troubleshooting](#-troubleshooting)

---

## 🛠 Tech Stack

<div align="center">

| Layer | Technology | Version |
|:---:|:---:|:---:|
| **Backend** | Laravel | 13 |
| **Language** | PHP | 8.3+ |
| **Frontend** | React | 19 |
| **Types** | TypeScript | 5.7 |
| **Bridge** | Inertia.js | 3 |
| **Routing** | Laravel Wayfinder | 0.1 |
| **Styling** | Tailwind CSS | 4 |
| **UI Primitives** | Radix UI | latest |
| **Charts** | Recharts | 3 |
| **Icons** | Lucide React | latest |
| **Toasts** | Sonner | 2 |
| **Database** | SQLite / MySQL / PostgreSQL | — |
| **PDF** | DomPDF | 3.1 |
| **Excel** | PHPSpreadsheet | 5.7 |
| **Auth** | Laravel Fortify + Passkeys | — |
| **Build** | Vite | 8 |
| **Compiler** | React Compiler | 1.0 |

</div>

---

## 📦 Prerequisites

Ensure these are installed before you begin:

- **PHP 8.3+** with extensions: `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `gd`
- **Composer** — PHP package manager
- **Node.js 20+** and **npm**
- **Git**

> **Optional:** SQLite CLI (for inspecting the DB), Mailpit (for catching dev emails)

---

## 🚀 Getting Started

### Clone

```bash
git clone <repository-url>
cd payroll-portal
```

### ⚡ One-Command Setup

```bash
composer run-script setup
```

> This handles everything: `composer install` → `.env` copy → `key:generate` → `migrate` → `npm install` → `npm run build`

---

### 🔧 Manual Setup (step by step)

```bash
# 1. PHP dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. SQLite database file (default DB — no server needed)
touch database/database.sqlite

# 4. Schema
php artisan migrate

# 5. Frontend
npm install
npm run build
```

### ▶️ Start Dev Server

```bash
composer run dev
```

This launches three concurrent processes:

```
┌─────────────┬──────────────────────────────┬──────────────────────────────┐
│  Process    │  Address                     │  Purpose                     │
├─────────────┼──────────────────────────────┼──────────────────────────────┤
│  server     │  http://localhost:8000       │  Laravel HTTP server         │
│  vite       │  (auto HMR)                  │  Live-reloads frontend       │
│  queue      │  —                           │  Background job processor    │
└─────────────┴──────────────────────────────┴──────────────────────────────┘
```

Open **[http://localhost:8000](http://localhost:8000)** and register your first account.

> In dev, emails are logged to `storage/logs/laravel.log` — check there for the verification link.

---

## 🏗 Architecture Overview

### Directory Structure

```
payroll-portal/
│
├── app/
│   ├── Http/Controllers/         ← Request handlers
│   │   ├── DashboardController.php
│   │   ├── EmployeeController.php
│   │   ├── HolidayController.php
│   │   ├── PayrollRunController.php
│   │   ├── PayrollComputeController.php
│   │   ├── PayrollEntryController.php
│   │   ├── PayrollLockController.php
│   │   ├── PayrollExportController.php
│   │   ├── PayrollManualAttendanceController.php
│   │   ├── PayslipController.php
│   │   ├── AttendanceUploadController.php
│   │   └── Settings/             ← Profile, Company, Security
│   │
│   ├── Models/                   ← Eloquent models
│   │   ├── User.php
│   │   ├── Employee.php
│   │   ├── PayrollRun.php
│   │   ├── PayrollEntry.php
│   │   ├── PayrollManualAttendance.php
│   │   ├── AttendanceUpload.php
│   │   ├── Holiday.php
│   │   └── AppSetting.php
│   │
│   └── Services/                 ← Domain logic
│       ├── PayrollCalculator.php   ← Core payroll math
│       ├── AttendanceParser.php    ← CSV/Excel parsing
│       └── PayrollExportService.php
│
├── database/
│   ├── migrations/               ← Schema (chronological)
│   ├── factories/                ← Model factories for testing
│   └── database.sqlite           ← Default DB (gitignored)
│
├── resources/
│   ├── js/
│   │   ├── pages/               ← Inertia page components
│   │   │   ├── auth/            ← Login, register, 2FA, reset
│   │   │   ├── dashboard.tsx
│   │   │   ├── employees/       ← index, create, edit
│   │   │   ├── holidays/        ← index, create, edit
│   │   │   ├── payroll/         ← index, create, show
│   │   │   └── settings/        ← profile, security, company, appearance
│   │   ├── components/          ← Reusable UI components
│   │   ├── layouts/             ← App layout, auth layout
│   │   ├── hooks/               ← Custom React hooks
│   │   ├── types/               ← TypeScript definitions
│   │   ├── lib/                 ← Utilities / helpers
│   │   └── wayfinder/           ← Auto-generated typed routes
│   │
│   ├── css/app.css              ← Global Tailwind styles
│   └── views/
│       ├── app.blade.php        ← Inertia root template
│       └── payslip/             ← Blade PDF/print templates
│
├── routes/
│   ├── web.php                  ← All authenticated routes
│   └── settings.php             ← Profile / company / security
│
└── tests/
    ├── Feature/                 ← HTTP / integration tests
    └── Unit/                    ← PayrollCalculator, AttendanceParser
```

### Request Lifecycle

```
  Browser
    │
    │  HTTP Request
    ▼
┌─────────────────────────────────────────┐
│            routes/web.php               │
│   auth + verified middleware guard      │
└──────────────────┬──────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────┐
│           Controller                    │
│  - Queries Eloquent models              │
│  - Calls Services (PayrollCalculator)   │
│  - Returns Inertia::render(...)         │
└──────────────────┬──────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────┐
│         React Component                 │
│  Receives PHP data as typed TS props    │
│  Renders UI / handles form submissions  │
└─────────────────────────────────────────┘
```

### Model Relationships

```
Employee ──────────────────────────────────────────────────────────────┐
  │  name, employee_number, gender, department                         │
  │  daily_rate, shift_start, shift_end, is_active                     │
  │                                                                    │
  └──< PayrollEntry >──────────────────────────────────────── PayrollRun
         days_present          period_start, period_end
         total_basic_pay       payable_date
         overtime_pay          status: 'draft' | 'locked'
         late_deduction        │
         undertime_deduction   ├──< AttendanceUpload
         holiday_pay                  original_filename, storage_path
         gross_pay             │
                               └──< PayrollManualAttendance
                                      date, sw, ew
                                      shift_start, shift_end (override)
                                      is_override

Holiday ────────────────────────────────────────────────────────────────
  date, name, type: 'regular' (2×) | 'special' (1.3×)

AppSetting ─────────────────────────────────────────────────────────────
  key/value store: company_name, company_logo, payslip_footer, ...
```

### Inertia.js — No Separate API

There is **no REST API**. Controllers render React pages directly:

```php
// PHP (Controller)
return Inertia::render('payroll/show', [
    'payrollRun' => $run,
    'entries'    => $entries,
]);
```

```tsx
// TypeScript (React page receives props directly)
export default function Show({ payrollRun, entries }: PageProps) {
    const form = useForm({ /* ... */ });

    return <button onClick={() => form.post(route('payroll-runs.compute', payrollRun.id))}>
        Compute
    </button>;
}
```

### Wayfinder — Typed Routes

Wayfinder auto-generates TypeScript route helpers from `routes/web.php`:

```tsx
import { route } from '@/wayfinder';

// Fully typed — compile error if route or params are wrong
<a href={route('payslip.download', { payrollEntry: entry.id })}>
    Download
</a>
```

---

## 🔐 Environment Variables

Copy `.env.example` → `.env` and configure:

### Core

| Variable | Description | Default |
|---|---|---|
| `APP_NAME` | Displayed in browser tab + emails | `"Payroll Portal"` |
| `APP_ENV` | `local` / `staging` / `production` | `local` |
| `APP_KEY` | 32-byte encryption key | *(generate with `php artisan key:generate`)* |
| `APP_DEBUG` | Show detailed error pages | `true` |
| `APP_URL` | Base URL — must match in production | `http://localhost` |

### Database

**SQLite (default — no server needed):**
```dotenv
DB_CONNECTION=sqlite
```

**MySQL:**
```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=payroll_portal
DB_USERNAME=root
DB_PASSWORD=secret
```

**PostgreSQL:**
```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=payroll_portal
DB_USERNAME=postgres
DB_PASSWORD=secret
```

### Queue, Cache, Session

| Variable | Description | Default |
|---|---|---|
| `SESSION_DRIVER` | Session storage | `database` |
| `CACHE_STORE` | Cache backend | `database` |
| `QUEUE_CONNECTION` | Job queue driver | `database` |

> ⚠️ Switch `QUEUE_CONNECTION` to `redis` for production — the `database` driver is for convenience, not throughput.

### Mail

```dotenv
# Development — logs to storage/logs/laravel.log
MAIL_MAILER=log

# Production SMTP
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourhost.com
MAIL_PORT=587
MAIL_USERNAME=you@example.com
MAIL_PASSWORD=secret
MAIL_FROM_ADDRESS=payroll@yourcompany.com
```

---

## 📜 Available Scripts

### PHP / Laravel

| Command | What it does |
|---|---|
| `composer run-script setup` | 🏁 Full first-time setup |
| `composer run dev` | 🚀 Start dev server (Laravel + Vite + Queue) |
| `composer run lint` | 🧹 Fix PHP style with Laravel Pint |
| `composer run lint:check` | 🔍 Check PHP style (no changes) |
| `composer run ci:check` | ✅ Run all checks: lint + format + types + tests |
| `composer run test` | 🧪 Clear config + lint check + PHPUnit |
| `php artisan migrate` | 📦 Run pending migrations |
| `php artisan migrate:fresh --seed` | 💣 Drop + migrate + seed |
| `php artisan queue:listen` | ⚙️ Start queue worker |
| `php artisan tinker` | 🐚 Interactive REPL |
| `php artisan wayfinder:generate` | 🗺️ Regenerate typed route helpers |

### JavaScript / Node

| Command | What it does |
|---|---|
| `npm run dev` | 🔥 Vite dev server with HMR |
| `npm run build` | 📦 Production bundle |
| `npm run format` | 💅 Format with Prettier |
| `npm run lint` | 🔧 Fix ESLint issues |
| `npm run types:check` | 🔬 TypeScript compiler check |

---

## 🧪 Testing

### Running Tests

```bash
# Full suite (lint check + PHPUnit)
composer run test

# PHPUnit only (skips lint)
php artisan test

# Specific file
php artisan test tests/Feature/PayrollRunTest.php

# Specific method
php artisan test --filter=test_can_compute_payroll

# Verbose
php artisan test --verbose
```

### Test Structure

```
tests/
├── Feature/
│   ├── Auth/                    ← Login, register, 2FA, password reset
│   ├── Settings/                ← Profile update, security settings
│   ├── DashboardTest.php
│   ├── EmployeeTest.php
│   ├── EmployeeFieldsTest.php
│   ├── HolidayTest.php
│   ├── PayrollRunTest.php
│   ├── PayslipTest.php
│   └── PayslipPrintTest.php
│
└── Unit/
    ├── PayrollCalculatorTest.php  ← Core payroll math in isolation
    └── AttendanceParserTest.php   ← CSV/Excel parsing
```

### Example Tests

**Feature test (HTTP level):**

```php
public function test_can_create_employee(): void
{
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/employees', [
            'name'        => 'Jane Doe',
            'daily_rate'  => 600.00,
            'shift_start' => '08:00:00',
            'shift_end'   => '17:00:00',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('employees', ['name' => 'Jane Doe']);
}
```

**Unit test (service logic):**

```php
public function test_calculates_late_deduction(): void
{
    $employee = Employee::factory()->make([
        'daily_rate'  => 480,
        'shift_start' => '08:00:00',
        'shift_end'   => '17:00:00',
    ]);

    $result = (new PayrollCalculator())->calculate(
        $employee,
        ['2026-06-01' => ['sw' => '08:30', 'ew' => '17:00']],
        collect([])
    );

    // 30 min late × (480 / 8hrs / 60min) = ₱30.00
    $this->assertEquals(30.0, $result['late_deduction']);
}
```

---

## 🧮 Payroll Calculation Logic

Core engine: [`app/Services/PayrollCalculator.php`](app/Services/PayrollCalculator.php)

### The Formula

```
per_minute_rate = daily_rate ÷ 8 hours ÷ 60 minutes

gross_pay = Σ(basic_pay per day)
          + overtime_pay
          + holiday_pay
          − late_deduction
          − undertime_deduction
```

### How Each Component Works

```
┌──────────────────────┬──────────────────────────────────────────────────┐
│ Component            │ Calculation                                      │
├──────────────────────┼──────────────────────────────────────────────────┤
│ Basic Pay            │ daily_rate × days_present                        │
│ Late Deduction       │ (actual_start − shift_start) × per_minute_rate  │
│ Undertime Deduction  │ (shift_end − actual_end) × per_minute_rate      │
│ Overtime Pay         │ (actual_end − shift_end) × per_minute_rate      │
│ Holiday Pay (reg.)   │ daily_rate × 1.0 extra (employee paid 2×)       │
│ Holiday Pay (spec.)  │ daily_rate × 0.3 extra (employee paid 1.3×)     │
└──────────────────────┴──────────────────────────────────────────────────┘
```

### Night Shift Support

When `shift_end ≤ shift_start` (e.g. 10 PM → 6 AM), the calculator adds one day to the end time before computing differences — correctly handling shifts that cross midnight.

### Manual Attendance Overrides

Manual entries with `is_override = true` replace CSV-sourced records for the same employee/date. Corrections are applied without re-uploading the entire attendance file.

---

## 🚢 Deployment

No platform-specific config is bundled. Here are the most common paths:

### 🐳 Docker (Laravel Sail — dev/staging)

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```

### 🖥️ VPS / Shared Hosting

```bash
# Pull code
git pull origin main

# PHP dependencies (no dev)
composer install --optimize-autoloader --no-dev

# Frontend
npm ci && npm run build

# Environment
php artisan key:generate
php artisan migrate --force

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

**Nginx config:**

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/payroll-portal/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### ☁️ PaaS (Railway / Render / Fly.io)

Set env vars in the platform dashboard, then:

- **Build command:** `composer install --no-dev --optimize-autoloader && npm ci && npm run build`
- **Start command:** `php artisan serve --host=0.0.0.0 --port=$PORT`
- **Post-deploy:** `php artisan migrate --force`

### ⚙️ Queue Worker (Production)

Run as a persistent Supervisor process:

```ini
# /etc/supervisor/conf.d/payroll-queue.conf
[program:payroll-queue]
command=php /var/www/payroll-portal/artisan queue:work --tries=3
autostart=true
autorestart=true
user=www-data
stdout_logfile=/var/log/payroll-queue.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start payroll-queue
```

---

## 🔧 Troubleshooting

<details>
<summary><strong>SQLite database file not found</strong></summary>

```bash
touch database/database.sqlite
php artisan migrate
```

</details>

<details>
<summary><strong>Vite assets 404 or mixed content in production</strong></summary>

Ensure `APP_URL` in `.env` matches the actual URL exactly (including `https://`):

```dotenv
APP_URL=https://payroll.yourcompany.com
```

Then:

```bash
php artisan config:cache
php artisan view:cache
```

</details>

<details>
<summary><strong>PDF payslips render without styles</strong></summary>

DomPDF requires CSS to be inlined or referenced via absolute file paths. If styles are missing, link the storage disk:

```bash
php artisan storage:link
```

</details>

<details>
<summary><strong>Queue jobs not processing</strong></summary>

Check that the queue worker is running:

```bash
php artisan queue:listen --tries=1
```

Retry failed jobs:

```bash
php artisan queue:failed
php artisan queue:retry all
```

</details>

<details>
<summary><strong>TypeScript errors — missing route types</strong></summary>

Regenerate Wayfinder route definitions:

```bash
php artisan wayfinder:generate
# or
npm run build
```

</details>

<details>
<summary><strong>Composer install fails — PHP extension missing</strong></summary>

```bash
# Ubuntu/Debian
sudo apt-get install php8.3-sqlite3 php8.3-gd php8.3-mbstring php8.3-xml php8.3-zip

# macOS (Homebrew)
brew install php   # extensions are bundled
```

</details>

<details>
<summary><strong>APP_KEY already exists warning</strong></summary>

```bash
php artisan key:generate --force
```

</details>

---

<div align="center">

Made with ❤️ using **Laravel** + **React** + **Inertia.js**

[![MIT License](https://img.shields.io/badge/License-MIT-green.svg?style=flat-square)](LICENSE)

</div>

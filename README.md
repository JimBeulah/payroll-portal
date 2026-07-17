# 💰 PAYRO

**Employee payroll management, simplified.**

*Developed by Nelmarjim Luna*

[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=flat-square)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square)](https://laravel.com)
[![React](https://img.shields.io/badge/React-19-61DAFB?style=flat-square)](https://react.dev)
[![License: MIT](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)

## What is it?

A web app for managing employee payroll: attendance tracking, automatic calculation, payslip generation, and request approvals. Built with **Laravel 13** + **React 19** + **Inertia.js** (no separate API).

## Features

- 👥 Employee management (rates, schedules, departments)
- 📅 Payroll runs (group by period, lock when final)
- 🕐 Attendance (CSV/Excel upload or manual entry)
- 🧮 Auto-compute pay (basic + OT + deductions + holiday)
- 🧾 Payslips (PDF, ZIP, print layout)
- 📊 Excel exports
- 🗓️ Holiday calendar
- 💰 Cash advance & leave request approvals
- 🔑 Passkeys & 2FA
- 👮 Role-based access (admin, HR, employee)

## Quick Start

### Prerequisites
- PHP 8.3+ (with pdo_sqlite, mbstring, openssl, fileinfo, gd)
- Composer
- Node.js 20+

### One-Command Setup
```bash
composer run-script setup
```

Or manually:
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

### Run Dev Server
```bash
composer run dev
```

Open **http://localhost:8000** and register.

## Commands

| Task | Command |
|------|---------|
| Dev server | `composer run dev` |
| Format code | `npm run format` && `php artisan pint` |
| Type check | `npm run types:check` |
| Run tests | `composer run test` |
| Build frontend | `npm run build` |
| Reset DB | `php artisan migrate:fresh --seed` |

## Database

**Default:** SQLite (no server needed). Switch to MySQL/PostgreSQL in `.env`:

```env
# SQLite (default)
DB_CONNECTION=sqlite

# MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=payroll_portal
DB_USERNAME=root
DB_PASSWORD=secret

# PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=payroll_portal
```

## Architecture

**Backend:** Laravel controllers → Inertia props → React pages (no REST API)

**Key Structure:**
- `app/Models/` - Database models
- `app/Services/` - Business logic (PayrollCalculator, etc.)
- `app/Http/Controllers/` - Request handlers
- `routes/web.php` - All routes (admin/HR only guarded by `role:admin,hr`)
- `resources/js/pages/` - React pages (auto-discovered)
  - `payroll/` - Admin/HR payroll management
  - `requests/` - Employee self-service
  - `approvals/` - Admin/HR request approvals

**Key Models:**
- User (with role: admin, hr, employee)
- Employee
- PayrollRun, PayrollEntry
- CashAdvanceRequest, LeaveRequest
- AttendanceUpload, Holiday, AppSetting

## Testing

```bash
# Run all tests
composer run test

# Run specific test
php artisan test tests/Feature/PayrollRunTest.php

# Filter by name
php artisan test --filter=test_can_compute_payroll
```

## Payroll Calculation

Formula: `gross_pay = basic_pay + overtime + holiday_pay − late_deduction − undertime_deduction`

Per-minute rate: `daily_rate ÷ 8 hours ÷ 60 minutes`

Late/undertime deducted at per-minute rate. Overtime paid at same rate.

Holiday pay: Regular holidays (2×) or special (1.3×).

See `app/Services/PayrollCalculator.php` for details.

## Deployment

### VPS / Shared Hosting
```bash
git pull origin main
composer install --no-dev
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

Nginx config:
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

### PaaS (Railway, Render, Fly.io)
Set env vars in dashboard:
- **Build:** `composer install --no-dev && npm ci && npm run build`
- **Start:** `php artisan serve --host=0.0.0.0 --port=$PORT`
- **Post-deploy:** `php artisan migrate --force`

### Queue Worker (Production)
Run as Supervisor process:
```ini
[program:payroll-queue]
command=php /var/www/payroll-portal/artisan queue:work --tries=3
autostart=true
autorestart=true
user=www-data
```

## Troubleshooting

**404 on assets?** Set `APP_URL` in `.env` correctly, then:
```bash
php artisan config:cache
```

**PDF styles missing?**
```bash
php artisan storage:link
```

**TypeScript errors (missing types)?**
```bash
npm run build
```

**Composer install fails?**
```bash
# Ubuntu/Debian
sudo apt-get install php8.3-sqlite3 php8.3-gd php8.3-mbstring
```

**Queue jobs not processing?**
```bash
php artisan queue:listen --tries=1
```

## Docs

See [CLAUDE.md](CLAUDE.md) for developer guide (architecture, common workflows, environment setup).

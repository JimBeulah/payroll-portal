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

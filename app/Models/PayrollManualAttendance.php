<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollManualAttendance extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'date', 'sw', 'ew',
        'shift_start', 'shift_end', 'note', 'is_override',
    ];

    protected $casts = [
        'date'        => 'date:Y-m-d',
        'is_override' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
    }
}

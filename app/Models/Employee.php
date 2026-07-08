<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'name', 'employee_number', 'gender', 'department', 'daily_rate', 'shift_start', 'shift_end', 'is_active',
    ];

    protected $casts = [
        'daily_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function payrollEntries()
    {
        return $this->hasMany(PayrollEntry::class);
    }

    /**
     * The login account linked to this employee (if any).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cashAdvanceRequests()
    {
        return $this->hasMany(CashAdvanceRequest::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
}

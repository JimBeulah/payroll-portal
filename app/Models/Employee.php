<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'employee_number', 'gender', 'department', 'daily_rate', 'shift_start', 'shift_end', 'is_active',
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

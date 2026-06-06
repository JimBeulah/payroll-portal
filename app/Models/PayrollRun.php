<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollRun extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['period_start', 'period_end', 'payable_date', 'status'];

    protected $casts = [
        'period_start' => 'date:Y-m-d',
        'period_end' => 'date:Y-m-d',
        'payable_date' => 'date:Y-m-d',
    ];

    public function entries()
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function uploads()
    {
        return $this->hasMany(AttendanceUpload::class);
    }

    public function manualAttendances()
    {
        return $this->hasMany(PayrollManualAttendance::class);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }
}

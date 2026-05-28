<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    use HasFactory;

    protected $fillable = ['period_start', 'period_end', 'payable_date', 'status', 'created_by'];

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

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }
}

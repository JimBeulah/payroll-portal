<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceUpload extends Model
{
    protected $fillable = ['payroll_run_id', 'filename', 'uploaded_at'];

    protected $casts = ['uploaded_at' => 'datetime'];

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class);
    }
}

<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceUpload extends Model
{
    protected $fillable = ['payroll_run_id', 'filename', 'uploaded_at'];

    protected $casts = ['uploaded_at' => 'datetime'];
}

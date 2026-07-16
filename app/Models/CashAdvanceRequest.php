<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashAdvanceRequest extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'employee_id', 'amount', 'reason', 'needed_date', 'status',
        'reviewed_by', 'reviewed_at', 'review_note', 'applied_payroll_run_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'needed_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function appliedPayrollRun()
    {
        return $this->belongsTo(PayrollRun::class, 'applied_payroll_run_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Advances due on a given payroll run: approved, payable in time (needed_date
     * on or before this run's payable_date), not already claimed by another run,
     * and with no earlier still-open run that could also cover them — so an
     * advance is always swept into the soonest payday that can pay it back,
     * never a later one.
     */
    public function scopeDueForPayrollRun(Builder $query, PayrollRun $payrollRun): Builder
    {
        return $query->approved()
            ->where('needed_date', '<=', $payrollRun->payable_date)
            ->where(function (Builder $q) use ($payrollRun) {
                $q->whereNull('applied_payroll_run_id')
                    ->orWhere('applied_payroll_run_id', $payrollRun->id);
            })
            ->whereNotExists(function ($q) use ($payrollRun) {
                $q->selectRaw(1)
                    ->from('payroll_runs as earlier_runs')
                    ->where('earlier_runs.status', '!=', 'locked')
                    ->where('earlier_runs.id', '!=', $payrollRun->id)
                    ->where('earlier_runs.payable_date', '<', $payrollRun->payable_date)
                    ->whereColumn('earlier_runs.payable_date', '>=', 'cash_advance_requests.needed_date');
            });
    }
}

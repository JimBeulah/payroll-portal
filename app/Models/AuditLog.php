<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'causer_name',
        'causer_email',
        'action',
        'auditable_type',
        'auditable_id',
        'subject_label',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeAction(Builder $query, ?string $action): Builder
    {
        return $action ? $query->where('action', $action) : $query;
    }

    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        return $userId ? $query->where('user_id', $userId) : $query;
    }
}

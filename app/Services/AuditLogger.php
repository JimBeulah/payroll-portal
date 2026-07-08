<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Record an audit log entry for the currently authenticated user.
     */
    public static function record(string $action, ?Model $subject = null, ?string $subjectLabel = null, array $metadata = []): AuditLog
    {
        $user = Auth::user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'causer_name' => $user?->name,
            'causer_email' => $user?->email,
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel,
            'metadata' => $metadata ?: null,
        ]);
    }
}

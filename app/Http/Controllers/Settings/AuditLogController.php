<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AuditLog::query()
            ->with('user:id,name')
            ->latest('created_at');

        if ($request->filled('user')) {
            $query->where('user_id', $request->integer('user'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('subject_label', 'like', "%{$search}%")
                    ->orWhere('causer_name', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(25)->withQueryString();

        return Inertia::render('settings/audit-logs/index', [
            'logs' => $logs,
            'filters' => $request->only(['user', 'action', 'from', 'to', 'search']),
            'actionOptions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'userOptions' => User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_HR])->orderBy('name')->get(['id', 'name']),
        ]);
    }
}

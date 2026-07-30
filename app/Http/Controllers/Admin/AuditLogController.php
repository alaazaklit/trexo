<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLog\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogService $service)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['user_id', 'action', 'auditable_type', 'from', 'to']);

        return view('admin.audit-logs', [
            'pageTitle' => 'Audit Logs',
            'logs' => $this->service->filtered($filters),
            'auditableTypes' => $this->service->auditableTypes(),
            'users' => User::orderBy('name')->get(['id', 'name', 'phone']),
            'filters' => $filters,
        ]);
    }
}

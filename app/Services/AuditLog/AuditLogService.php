<?php

namespace App\Services\AuditLog;

use App\Models\AuditLog;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditLogService
{
    /**
     * @param array{user_id?: string, action?: string, auditable_type?: string, from?: string, to?: string} $filters
     */
    public function filtered(array $filters): LengthAwarePaginator
    {
        $query = AuditLog::query()->with('user')->latest('created_at');

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        if (!empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->paginate(25)->withQueryString();
    }

    /**
     * Distinct fully-qualified class names present in the log. Callers
     * display class_basename() as the label but must filter on the full
     * value, since that's what's stored on each row.
     */
    public function auditableTypes(): array
    {
        return AuditLog::query()
            ->select('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type')
            ->all();
    }
}

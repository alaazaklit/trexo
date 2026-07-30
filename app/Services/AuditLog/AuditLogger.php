<?php

namespace App\Services\AuditLog;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Record an audited action. Usable both by the global model-event
     * listener and directly by controllers for non-model admin actions.
     */
    public static function record(string $action, Model $model, array $oldValues = [], array $newValues = []): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()?->ip(),
            'user_agent' => app()->runningInConsole() ? null : request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}

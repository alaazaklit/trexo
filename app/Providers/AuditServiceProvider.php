<?php

namespace App\Providers;

use App\Models\Driver;
use App\Models\User;
use App\Order;
use App\Reservation;
use App\Services\AuditLog\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    /**
     * Models audited in full — every changed field is logged.
     */
    private const FULL_AUDIT = [
        Order::class,
        Reservation::class,
    ];

    /**
     * Models audited only for a specific field set. GPS/heartbeat fields
     * (latitude, longitude, heading, speed_kmh, last_seen_at) are excluded
     * for User/Driver since they update continuously from live tracking
     * and would otherwise dominate the log within days.
     */
    private const FIELD_RESTRICTED_AUDIT = [
        User::class => ['role_id', 'phone', 'email', 'type', 'is_verified', 'account_status', 'status_reason'],
        Driver::class => ['status', 'is_online', 'license_number', 'vehicle_id', 'approval_status'],
    ];

    public function boot(): void
    {
        // Model::saved()/deleted() use late static binding internally
        // (registerModelEvent() listens on `static::class`), so calling
        // them on the abstract base Model would register a listener for
        // the literal string "Illuminate\Database\Eloquent\Model" — which
        // no real model ever matches. Each audited class must be
        // registered individually instead.
        foreach ($this->auditedClasses() as $class) {
            /** @var class-string<Model> $class */
            $class::saved(function (Model $model) {
                $this->handleSaved($model);
            });

            $class::deleted(function (Model $model) {
                $this->handleDeleted($model);
            });
        }
    }

    private function auditedClasses(): array
    {
        return array_merge(self::FULL_AUDIT, array_keys(self::FIELD_RESTRICTED_AUDIT));
    }

    private function handleSaved(Model $model): void
    {
        $class = get_class($model);
        $watchedFields = $this->watchedFieldsFor($class);

        if ($watchedFields === null) {
            return;
        }

        $changes = $watchedFields === []
            ? $model->getChanges()
            : array_intersect_key($model->getChanges(), array_flip($watchedFields));

        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        $old = array_intersect_key($model->getOriginal(), $changes);

        AuditLogger::record($model->wasRecentlyCreated ? 'created' : 'updated', $model, $old, $changes);
    }

    private function handleDeleted(Model $model): void
    {
        $class = get_class($model);

        if ($this->watchedFieldsFor($class) === null) {
            return;
        }

        AuditLogger::record('deleted', $model, $model->getOriginal(), []);
    }

    /**
     * Null if the model isn't audited at all. Empty array means "audit
     * every field"; otherwise the specific field allow-list to watch.
     */
    private function watchedFieldsFor(string $class): ?array
    {
        if (in_array($class, self::FULL_AUDIT, true)) {
            return [];
        }

        return self::FIELD_RESTRICTED_AUDIT[$class] ?? null;
    }
}

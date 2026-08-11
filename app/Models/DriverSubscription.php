<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverSubscription extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = [
        'driver_id',
        'plan_id',
        'commission_percentage_snapshot',
        'start_date',
        'end_date',
        'renewal_date',
        'payment_status',
        'payment_method',
        'receipt_path',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'commission_percentage_snapshot' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'renewal_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function isCurrentlyValid(): bool
    {
        return $this->payment_status === 'approved'
            && ($this->end_date === null || $this->end_date->isFuture() || $this->end_date->isToday());
    }
}

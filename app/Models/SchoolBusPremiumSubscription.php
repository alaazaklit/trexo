<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolBusPremiumSubscription extends Model
{
    // No real payment gateway exists in this app — a parent uploads a
    // receipt photo/PDF instead (see SchoolBusPremiumService::subscribe),
    // same manual-review pattern as the driver Basic/Plus plans
    // (DriverSubscription/SubscriptionService::approve). price/months here
    // are the terms applied once an admin approves it.
    public const PLANS = [
        'monthly' => ['price' => 1.99, 'label' => 'Monthly', 'months' => 1, 'badge' => null],
        'yearly' => ['price' => 14.99, 'label' => 'Yearly', 'months' => 12, 'badge' => 'Best Value'],
    ];

    public const STATUSES = ['pending', 'active', 'rejected', 'expired', 'cancelled'];

    protected $fillable = [
        'school_bus_subscription_id',
        'parent_user_id',
        'plan',
        'price',
        'receipt_path',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'started_at',
        'expires_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SchoolBusSubscription::class, 'school_bus_subscription_id');
    }

    public function parentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isCurrentlyActive(): bool
    {
        return $this->status === 'active' && $this->expires_at !== null && $this->expires_at->isFuture();
    }
}

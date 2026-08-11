<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolBusSubscription extends Model
{
    public const STATUSES = ['pending', 'active', 'rejected'];

    protected $fillable = [
        'route_id',
        'driver_id',
        'parent_user_id',
        'student_name',
        'parent_name',
        'phone',
        'address',
        'latitude',
        'longitude',
        'notes',
        'status',
        'rejection_reason',
        'accepted_at',
        'last_proximity_notified_at',
        'children_count',
        'base_price',
        'discount_percent',
        'total_price',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'last_proximity_notified_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'children_count' => 'integer',
        'base_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(SchoolBusRoute::class, 'route_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function parentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function premiumSubscriptions(): HasMany
    {
        return $this->hasMany(SchoolBusPremiumSubscription::class, 'school_bus_subscription_id');
    }
}

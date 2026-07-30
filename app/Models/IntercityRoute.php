<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntercityRoute extends Model
{
    protected $fillable = [
        'from_zone_id',
        'to_zone_id',
        'fixed_fare_taxi',
        'fixed_fare_delivery',
        'is_active',
    ];

    protected $casts = [
        'fixed_fare_taxi' => 'decimal:2',
        'fixed_fare_delivery' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // There's no real `name` column — this only exists so Voyager's
    // relationship field (on driver_intercity_route_overrides' admin BREAD)
    // has something readable to display instead of a bare route id.
    protected $appends = ['name'];

    public function fromZone(): BelongsTo
    {
        return $this->belongsTo(PricingZone::class, 'from_zone_id');
    }

    public function toZone(): BelongsTo
    {
        return $this->belongsTo(PricingZone::class, 'to_zone_id');
    }

    public function getNameAttribute(): string
    {
        return ($this->fromZone?->name ?? '?') . ' ↔ ' . ($this->toZone?->name ?? '?');
    }
}

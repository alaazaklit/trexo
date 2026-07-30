<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'license_number',
        'national_id_number',
        'vehicle_id',
        'vehicle_category_id',
        'rating',
        'base_fare_override',
        'price_per_km_override',
        'detour_surcharge_override',
        'reservation_multiplier_override',
        'pricing_zone_id',
        'is_online',
        'status',
        'approval_status',
        'vehicle_type',
        'vehicle_make',
        'vehicle_model',
        'vehicle_color',
        'vehicle_plate',
        'vehicle_year',
        'transmission',
        'speed_kmh',
        'latitude',
        'longitude',
        'heading',
        'last_seen_at',
        'ride_response_mode',
        'workflow_state',
        'assigned_order_id',
        'assigned_trip_id',
        'pickup_latitude',
        'pickup_longitude',
        'dropoff_latitude',
        'dropoff_longitude',
        'route_points',
        'route_cursor',
        'spawn_center_latitude',
        'spawn_center_longitude',
        'spawn_radius_km',
        'is_simulated',
        'simulation_notes',
    ];

    protected $casts = [
        'rating' => 'decimal:2',
        'base_fare_override' => 'decimal:2',
        'price_per_km_override' => 'decimal:2',
        'detour_surcharge_override' => 'decimal:2',
        'reservation_multiplier_override' => 'decimal:2',
        'is_online' => 'boolean',
        'vehicle_year' => 'integer',
        'speed_kmh' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'heading' => 'decimal:2',
        'last_seen_at' => 'datetime',
        'route_points' => 'array',
        'route_cursor' => 'integer',
        'spawn_center_latitude' => 'decimal:7',
        'spawn_center_longitude' => 'decimal:7',
        'spawn_radius_km' => 'decimal:2',
        'is_simulated' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function simulatorRides(): HasMany
    {
        return $this->hasMany(DriverSimulatorRide::class, 'driver_id');
    }

    public function vehicleCategory(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class);
    }

    public function pricingZone(): BelongsTo
    {
        return $this->belongsTo(PricingZone::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class);
    }
}

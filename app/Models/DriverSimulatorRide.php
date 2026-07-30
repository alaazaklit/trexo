<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverSimulatorRide extends Model
{
    use HasFactory;

    protected $fillable = [
        'passenger_name',
        'passenger_phone',
        'pickup_latitude',
        'pickup_longitude',
        'dropoff_latitude',
        'dropoff_longitude',
        'pickup_label',
        'dropoff_label',
        'status',
        'auto_response_mode',
        'driver_id',
        'order_id',
        'matched_driver_ids',
        'route_points',
        'route_cursor',
        'response_time_seconds',
        'accepted_at',
        'rejected_at',
        'started_at',
        'finished_at',
        'metadata',
    ];

    protected $casts = [
        'pickup_latitude' => 'decimal:7',
        'pickup_longitude' => 'decimal:7',
        'dropoff_latitude' => 'decimal:7',
        'dropoff_longitude' => 'decimal:7',
        'matched_driver_ids' => 'array',
        'route_points' => 'array',
        'route_cursor' => 'integer',
        'response_time_seconds' => 'integer',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}

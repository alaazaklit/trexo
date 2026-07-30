<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverIntercityRouteOverride extends Model
{
    protected $fillable = [
        'user_id',
        'intercity_route_id',
        'fixed_fare_taxi_override',
        'fixed_fare_delivery_override',
    ];

    protected $casts = [
        'fixed_fare_taxi_override' => 'decimal:2',
        'fixed_fare_delivery_override' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function intercityRoute(): BelongsTo
    {
        return $this->belongsTo(IntercityRoute::class);
    }
}

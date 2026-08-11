<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolBusRoute extends Model
{
    protected $fillable = [
        'driver_id',
        'school_id',
        'pickup_area',
        'monthly_price',
        'is_active',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SchoolBusSubscription::class, 'route_id');
    }
}

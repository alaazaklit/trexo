<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'price_multiplier',
        'capacity',
        'icon',
        'supports_taxi',
        'supports_delivery',
        'is_active',
    ];

    protected $casts = [
        'price_multiplier' => 'decimal:2',
        'capacity' => 'integer',
        'supports_taxi' => 'boolean',
        'supports_delivery' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'monthly_price',
        'commission_percentage',
        'is_active',
        'features',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'features' => 'array',
    ];

    public function driverSubscriptions(): HasMany
    {
        return $this->hasMany(DriverSubscription::class, 'plan_id');
    }
}

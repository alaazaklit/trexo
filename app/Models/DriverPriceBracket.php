<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverPriceBracket extends Model
{
    protected $fillable = [
        'user_id',
        'lower_km',
        'upper_km',
        'anchor_distance_km',
        'reference_text',
        'tier_total_price',
        'price_per_km',
    ];

    protected $casts = [
        'lower_km' => 'decimal:2',
        'upper_km' => 'decimal:2',
        'anchor_distance_km' => 'decimal:2',
        'tier_total_price' => 'decimal:2',
        'price_per_km' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

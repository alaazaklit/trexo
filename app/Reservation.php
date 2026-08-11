<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Models\Transaction;
use App\Models\User;


class Reservation extends Model
{
    protected $fillable = [
        'seller_id',
        'driver_id',
        'pickup',
        'destination',
        'route_points',
        'route_distance_km',
        'start_date_time',
        'end_date_time',
        'status',
        'tracking_id',
        'order_kind',
        'price',
    ];

    protected $casts = [
        'pickup' => 'array',
        'destination' => 'array',
        'route_points' => 'array',
        'route_distance_km' => 'decimal:3',
        'start_date_time' => 'datetime',
        'end_date_time' => 'datetime',
        'price' => 'decimal:2',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }
}

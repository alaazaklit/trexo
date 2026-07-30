<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class Schedule extends Model
{
   // Add fields to the fillable property
   protected $fillable = [
    'user_id',
    'type',
    'day', // Ensure this field exists or is part of your request data
    'date',
    'time_from',
    'time_to',
    'start_address',
    'order_id',
    'destination_address',
    'route_points',
    'route_distance_km'
];

   protected $casts = [
    'route_points' => 'array',
    'route_distance_km' => 'decimal:3',
];
}

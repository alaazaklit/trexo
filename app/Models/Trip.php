<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    // Specify the table if it doesn't follow Laravel's naming convention (optional)
    // protected $table = 'trips';

    // Specify the attributes that are mass assignable
    protected $fillable = [
        'customer_id',
        'driver_id',
        'start_address_id',
        'end_address_id',
        'start_time',
        'end_time',
        'fare',
        'status'
    ];

    // Define the relationships

    // A trip belongs to a customer (user)
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    // A trip belongs to a driver (user)
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    // A trip has a start address
    public function startAddress()
    {
        return $this->belongsTo(Address::class, 'start_address_id');
    }

    // A trip has an end address
    public function endAddress()
    {
        return $this->belongsTo(Address::class, 'end_address_id');
    }

    // Optionally, you can define any additional methods or logic specific to the Trip model
}

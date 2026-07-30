<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverApplicationNote extends Model
{
    protected $fillable = [
        'driver_application_id',
        'user_id',
        'note',
    ];

    public function driverApplication(): BelongsTo
    {
        return $this->belongsTo(DriverApplication::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

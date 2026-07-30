<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ReservationMessage extends Model
{
    protected $fillable = [
        'reservation_id',
        'sender_id',
        'message',
        'is_read',
    ];

    public function sender()
    {
        return $this->belongsTo(\App\Models\User::class, 'sender_id');
    }
}

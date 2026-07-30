<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class TripRating extends Model
{
    protected $fillable = [
        'order_id',
        'reservation_id',
        'rater_user_id',
        'rated_user_id',
        'rating',
        'comment',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    public function rater()
    {
        return $this->belongsTo(User::class, 'rater_user_id');
    }

    public function ratedUser()
    {
        return $this->belongsTo(User::class, 'rated_user_id');
    }
}

<?php

namespace App\Models;

use App\Order;
use App\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestDispatchLog extends Model
{
    protected $fillable = [
        'request_type',
        'order_id',
        'reservation_id',
        'seller_id',
        'driver_id',
        'order_kind',
        'price',
        'outcome',
        'sent_at',
        'responded_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (!empty($filters['seller_id'])) {
            $query->where('seller_id', $filters['seller_id']);
        }
        if (!empty($filters['driver_id'])) {
            $query->where('driver_id', $filters['driver_id']);
        }
        if (!empty($filters['request_type'])) {
            $query->where('request_type', $filters['request_type']);
        }
        if (!empty($filters['outcome'])) {
            $query->where('outcome', $filters['outcome']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('sent_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('sent_at', '<=', $filters['date_to']);
        }

        return $query;
    }
}

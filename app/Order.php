<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Models\Transaction;
use App\Models\User;


class Order extends Model
{
    protected $fillable = [
        'user_id',
        'driver_id',
        'description',
        'status',
        'order_kind',
        'route_points',
        'route_distance_km',
        'tracking_id',
        'price',
        'arrival_confirmation_count',
    ];

    protected $casts = [
        'route_points' => 'array',
        'route_distance_km' => 'decimal:3',
        'price' => 'decimal:2',
        'arrival_confirmation_count' => 'integer',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function startAddress()
    {
        return $this->hasOne(Address::class, 'order_id')->where('direction', 'start_address');
    }

    public function endAddress()
    {
        return $this->hasOne(Address::class, 'order_id')->where('direction', 'destination_address');
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }

    /**
     * Admin dashboard smart search — wired up via this BREAD resource's
     * `data_types.scope` column (see the
     * 2026_07_16_000002_enable_orders_smart_search_scope migration), so it
     * only ever applies to the `/admin/orders` browse query, not every
     * `Order` query app-wide. Matches order number, customer/driver name,
     * phone number, or pickup/dropoff address in one free-text box.
     */
    public function scopeSmartSearch($query)
    {
        $term = trim((string) request()->query('q', ''));
        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like) {
            $q->where('orders.tracking_id', 'like', $like)
              ->orWhereHas('seller', function ($sq) use ($like) {
                  $sq->where('name', 'like', $like)->orWhere('phone', 'like', $like);
              })
              ->orWhereHas('driver', function ($dq) use ($like) {
                  $dq->where('name', 'like', $like)->orWhere('phone', 'like', $like);
              })
              ->orWhereHas('startAddress', function ($aq) use ($like) {
                  $aq->where('address_line1', 'like', $like);
              })
              ->orWhereHas('endAddress', function ($aq) use ($like) {
                  $aq->where('address_line1', 'like', $like);
              });
        });
    }
}

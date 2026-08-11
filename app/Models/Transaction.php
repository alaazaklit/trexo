<?php

namespace App\Models;

use App\Order;
use App\Reservation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Immutable ledger row — created once by TransactionService, never updated
// afterward. See the create_transactions_table migration for why.
class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'reservation_id',
        'driver_id',
        'customer_id',
        'service_type',
        'total_amount',
        'commission_percentage',
        'commission_amount',
        'driver_earnings',
        'driver_subscription_id',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'driver_earnings' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function driverSubscription(): BelongsTo
    {
        return $this->belongsTo(DriverSubscription::class);
    }
}

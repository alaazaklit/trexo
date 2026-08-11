<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// This app is cash-to-driver: the customer pays the driver directly, Trexo
// has no payment gateway. `commission_owed` is what the driver owes Trexo
// out of the cash they've already collected — never money Trexo owes the
// driver. It only ever increases (a completed trip) or decreases (an
// approved CommissionPayment); there is no "withdrawal" concept here.
class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'commission_owed',
    ];

    protected $casts = [
        'commission_owed' => 'decimal:2',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A driver's report of having paid Trexo their owed commission (via Wish
// Money, outside the app), with a receipt as proof — see the wallet-rename
// migration for why this replaced the old PayoutRequest/"withdrawal" model.
class CommissionPayment extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = [
        'driver_id',
        'amount',
        'status',
        'payment_method',
        'receipt_path',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Broadcast extends Model
{
    public const ACCOUNT_TYPES = ['driver', 'seller'];
    public const SERVICE_TYPES = ['taxi', 'delivery', 'bus'];

    protected $fillable = [
        'title',
        'message',
        'account_type',
        'service_type',
        'recipient_count',
        'sent_by',
    ];

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}

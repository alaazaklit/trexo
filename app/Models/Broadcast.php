<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Broadcast extends Model
{
    public const ACCOUNT_TYPES = ['driver', 'seller'];

    public const SERVICE_TYPES = ['taxi', 'delivery', 'bus'];

    public const SOURCE_FILTRATION = 'filtration';

    public const SOURCE_EXCEL = 'excel';

    public const SOURCES = [self::SOURCE_FILTRATION, self::SOURCE_EXCEL];

    protected $fillable = [
        'title',
        'message',
        'account_type',
        'service_type',
        'recipient_count',
        'sent_by',
        'source',
        'source_file_name',
    ];

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}

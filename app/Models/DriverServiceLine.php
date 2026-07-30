<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverServiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'service_type',
        'line_type',
        'client_line_id',
        'from_label',
        'to_label',
        'discount_rules',
        'schedule_mode',
        'weekly_start_times',
        'weekly_end_times',
        'specific_dates',
    ];

    protected $casts = [
        'discount_rules' => 'array',
        'weekly_start_times' => 'array',
        'weekly_end_times' => 'array',
        'specific_dates' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

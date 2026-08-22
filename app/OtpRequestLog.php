<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit trail + rate-limit source of truth for every OTP
 * request attempt. See App\Services\OtpService and the
 * create_otp_request_logs_table migration.
 */
class OtpRequestLog extends Model
{
    public const STATUS_SENT = 'sent';
    public const STATUS_SEND_FAILED = 'send_failed';
    public const STATUS_BLOCKED_COOLDOWN = 'blocked_cooldown';
    public const STATUS_BLOCKED_PHONE_10MIN = 'blocked_phone_10min';
    public const STATUS_BLOCKED_PHONE_DAILY = 'blocked_phone_daily';
    public const STATUS_BLOCKED_IP_HOURLY = 'blocked_ip_hourly';
    public const STATUS_BLOCKED_IP_SUSPICIOUS = 'blocked_ip_suspicious';
    public const STATUS_BLOCKED_DEVICE_SUSPICIOUS = 'blocked_device_suspicious';
    public const STATUS_BLOCKED_GLOBAL_RATE = 'blocked_global_rate';

    protected $fillable = [
        'phone',
        'user_id',
        'ip_address',
        'device_id',
        'type',
        'status',
        'provider_mode',
        'request_id',
        'provider_response',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}

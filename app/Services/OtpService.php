<?php

namespace App\Services;

use App\Models\User;
use App\OtpRequestLog;
use App\VerificationCode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Single choke point every WhatsApp-OTP send goes through (login OTP,
 * password reset, account deletion). Enforces, in order: per-phone
 * cooldown, per-phone rolling-window caps, per-IP/device caps and abuse
 * signals, then a global send-rate throttle — before ever calling
 * UnlimitedMessagingService. See config/otp.php for every tunable.
 *
 * Every check-then-write in requestOtp() runs inside a single named MySQL
 * lock (see withMutex()), so two or more concurrent requests — for the
 * same phone, the same IP, or different phones entirely — are strictly
 * serialized while limits are evaluated. Nothing here is decided from a
 * read that a concurrent request could invalidate before the write lands.
 *
 * Whether a "send" actually reaches the real WhatsApp provider is decided
 * by resolveProviderMode(): the testing environment is always mocked with
 * no override, local/other non-production environments default to mocked
 * unless OTP_PROVIDER_MODE=live is explicitly set, and production sends
 * for real by default. This exists because an earlier version of this
 * service had no such gate and a local test run ended up firing real
 * WhatsApp messages at fabricated numbers through the live provider
 * account — never again.
 */
class OtpService
{
    public const REASON_SENT = 'sent';
    public const REASON_COOLDOWN = 'cooldown';
    public const REASON_LIMIT_10MIN = 'limit_10min';
    public const REASON_LIMIT_DAILY = 'limit_daily';
    public const REASON_LIMIT_IP = 'limit_ip';
    public const REASON_SUSPICIOUS = 'suspicious';
    public const REASON_GLOBAL_BUSY = 'global_busy';
    public const REASON_SEND_FAILED = 'send_failed';

    public const MODE_MOCK = 'mock';
    public const MODE_LIVE = 'live';

    /**
     * Test-only escape hatch for exercising the 'live' dispatch branch
     * (success/failure mapping) against a mocked UnlimitedMessagingService
     * — never against a real network call, since that dependency is
     * itself swapped for a test double wherever this is used. The
     * testing-environment guard in resolveProviderMode() otherwise always
     * wins with no way around it; this is the one deliberate, explicit
     * opt-in, and forceProviderModeForTesting() refuses to run outside
     * the testing environment so it can't leak into any real deployment.
     */
    private static ?string $forcedModeForTesting = null;

    public static function forceProviderModeForTesting(?string $mode): void
    {
        if (!app()->environment('testing')) {
            throw new \RuntimeException('forceProviderModeForTesting() may only be used in the testing environment.');
        }

        self::$forcedModeForTesting = $mode;
    }

    public function __construct(private readonly UnlimitedMessagingService $whatsAppService)
    {
    }

    /**
     * @param \Closure(string $code): string $buildMessage builds the WhatsApp message body from the generated 6-digit code
     * @return array{reason:string, message:string, http_status:int, code:?VerificationCode, wait_seconds?:int}
     */
    public function requestOtp(User $user, string $phone, string $type, Request $request, \Closure $buildMessage): array
    {
        $ip = (string) $request->ip();
        $deviceId = $request->input('device_id') ?: $request->header('X-Device-Id');
        $deviceId = $deviceId !== null && $deviceId !== '' ? (string) $deviceId : null;
        $requestId = (string) Str::uuid();

        // Every limit check plus the "reserve a slot" write happens inside
        // one named lock — not per-phone or per-IP, but a single global
        // critical section. That's deliberately simple: this endpoint's
        // whole traffic is a handful of DB queries per call with the
        // actual network send happening after the lock is released, so
        // serializing all of it costs a few milliseconds of queueing even
        // under load, in exchange for every limit (phone, IP, device,
        // global) being genuinely race-proof without having to reason
        // about lock-ordering between several separately-keyed locks.
        $outcome = $this->withMutex('otp-request', function () use ($user, $phone, $type, $ip, $deviceId, $requestId) {
            if ($blocked = $this->checkLimits($user, $phone, $type, $ip, $deviceId, $requestId)) {
                return $blocked;
            }

            if (!$this->hasGlobalCapacity()) {
                $this->logBlocked($phone, $user->id, $ip, $deviceId, $type, OtpRequestLog::STATUS_BLOCKED_GLOBAL_RATE, $requestId);

                return [
                    'reason' => self::REASON_GLOBAL_BUSY,
                    'message' => 'الخدمة مشغولة حالياً، يرجى المحاولة خلال لحظات',
                    'http_status' => 503,
                    'code' => null,
                ];
            }

            try {
                return DB::transaction(function () use ($user, $phone, $type, $ip, $deviceId, $requestId) {
                    // Only the latest requested code of this $type should
                    // ever be acceptable — an older still-unexpired one
                    // must not verify.
                    VerificationCode::where('user_id', $user->id)
                        ->where('type', $type)
                        ->where('used', false)
                        ->update(['used' => true]);

                    $verificationCode = VerificationCode::create([
                        'user_id' => $user->id,
                        'code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                        'expires_at' => Carbon::now()->addMinutes((int) config('otp.expiration_minutes')),
                        'type' => $type,
                        'used' => 0,
                    ]);

                    // Logged as 'sent' before the WhatsApp call even
                    // happens — reserving this slot is what the mutex
                    // above is protecting. If the send actually fails,
                    // this row is downgraded to send_failed afterwards,
                    // which removes it from every rolling-window count
                    // again, so a provider hiccup doesn't cost the user
                    // part of their budget.
                    $log = OtpRequestLog::create([
                        'phone' => $phone,
                        'user_id' => $user->id,
                        'ip_address' => $ip,
                        'device_id' => $deviceId,
                        'type' => $type,
                        'status' => OtpRequestLog::STATUS_SENT,
                        'request_id' => $requestId,
                    ]);

                    return ['proceed' => true, 'code' => $verificationCode, 'log_id' => $log->id];
                });
            } catch (\Throwable $e) {
                // A raw DB\QueryException's own message interpolates its
                // bound parameters — including the OTP code just
                // generated above — and Laravel's default handler would
                // write that straight to storage/logs/laravel.log if this
                // propagated uncaught. Caught here and logged with only
                // the exception class, never $e->getMessage().
                Log::error('OtpService: failed to persist OTP code', [
                    'request_id' => $requestId,
                    'exception_class' => get_class($e),
                ]);

                return [
                    'reason' => self::REASON_SEND_FAILED,
                    'message' => 'تعذر إرسال رمز التحقق عبر واتساب، يرجى المحاولة لاحقاً',
                    'http_status' => 502,
                    'code' => null,
                ];
            }
        });

        if (!($outcome['proceed'] ?? false)) {
            return $outcome;
        }

        // The actual send — mocked or real per resolveProviderMode() —
        // happens outside the lock, so a slow/hanging network call never
        // blocks other OTP requests from being evaluated.
        $verificationCode = $outcome['code'];
        $sent = $this->dispatchSend($phone, $buildMessage($verificationCode->code), $type, $outcome['log_id'], $requestId);

        Log::info('OTP request processed', [
            'request_id' => $requestId,
            'phone' => $this->maskPhone($phone),
            'user_id' => $user->id,
            'ip' => $ip,
            'device_id' => $deviceId,
            'type' => $type,
            'result' => $sent ? 'sent' : 'send_failed',
        ]);

        if (!$sent) {
            OtpRequestLog::where('id', $outcome['log_id'])->update(['status' => OtpRequestLog::STATUS_SEND_FAILED]);

            // Never retried automatically here — a failed send returns a
            // clean error to the caller and stops. Any retry is a
            // deliberate new request from the user (subject to the same
            // cooldown/limits all over again), never an automatic loop.
            if (!config('app.debug')) {
                return [
                    'reason' => self::REASON_SEND_FAILED,
                    'message' => 'تعذر إرسال رمز التحقق عبر واتساب، يرجى المحاولة لاحقاً',
                    'http_status' => 502,
                    'code' => null,
                ];
            }
        }

        return [
            'reason' => self::REASON_SENT,
            'message' => 'تم إرسال رمز التحقق عبر واتساب',
            'http_status' => 200,
            'code' => $verificationCode,
        ];
    }

    /**
     * Call once a code for $user has just been verified successfully —
     * gives that phone a fresh rolling-day budget instead of leaving it
     * penalized for the rest of the day by requests that led to this
     * success.
     */
    public function markVerified(User $user): void
    {
        $user->otp_counter_reset_at = Carbon::now();
        $user->save();
    }

    /**
     * Decides whether a send actually reaches the real WhatsApp provider.
     * Testing is hard-coded to mock with no possible override — see the
     * class docblock for why. Everywhere else, an explicit
     * OTP_PROVIDER_MODE env value wins; failing that, production sends
     * for real (preserving this app's existing live behavior with no .env
     * change required) and every other environment defaults to mock.
     */
    public function resolveProviderMode(): string
    {
        if (app()->environment('testing')) {
            return self::$forcedModeForTesting ?? self::MODE_MOCK;
        }

        $explicit = strtolower((string) config('otp.provider_mode'));
        if (in_array($explicit, [self::MODE_MOCK, self::MODE_LIVE], true)) {
            return $explicit;
        }

        return app()->environment('production') ? self::MODE_LIVE : self::MODE_MOCK;
    }

    /**
     * In mock mode, no HTTP call is made at all — the send is logged and
     * reported as successful (the generated code is still recoverable via
     * the existing debug_otp response field when app.debug is on, which
     * is how local/mocked testing reads the code without WhatsApp).
     */
    private function dispatchSend(string $phone, string $message, string $type, int $logId, string $requestId): bool
    {
        $mode = $this->resolveProviderMode();

        OtpRequestLog::where('id', $logId)->update(['provider_mode' => $mode]);

        if ($mode === self::MODE_MOCK) {
            Log::info('OTP send mocked — no real WhatsApp message sent', [
                'request_id' => $requestId,
                'phone' => $this->maskPhone($phone),
                'type' => $type,
            ]);

            return true;
        }

        return $this->whatsAppService->sendWhatsAppMessage($phone, $message);
    }

    /**
     * Runs $callback while holding a MySQL named lock (GET_LOCK) — a real
     * cross-connection, cross-process mutex, so it serializes concurrent
     * requests regardless of which PHP-FPM worker or CLI process handles
     * them, unlike an in-process lock or a cache-backed one whose
     * atomicity depends on CACHE_DRIVER. Falls through unlocked on a
     * non-MySQL connection (e.g. an isolated unit test on sqlite) rather
     * than erroring, since GET_LOCK is MySQL-specific.
     */
    private function withMutex(string $key, \Closure $callback)
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return $callback();
        }

        $lockName = 'otp:' . md5($key);
        $acquired = (int) (DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$lockName, 5])->acquired ?? 0);

        if ($acquired !== 1) {
            Log::warning('OtpService: lock contention, request rejected', ['key' => $key]);

            return [
                'reason' => self::REASON_GLOBAL_BUSY,
                'message' => 'الخدمة مشغولة حالياً، يرجى المحاولة خلال لحظات',
                'http_status' => 503,
                'code' => null,
            ];
        }

        try {
            return $callback();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }
    }

    /**
     * @return array{reason:string,message:string,http_status:int,code:null,wait_seconds?:int}|null null when nothing blocks the request
     */
    private function checkLimits(User $user, string $phone, string $type, string $ip, ?string $deviceId, string $requestId): ?array
    {
        $cooldownSeconds = (int) config('otp.resend_cooldown_seconds');
        $lastSentAt = $this->lastSentAt($phone);

        if ($lastSentAt && $lastSentAt->gt(Carbon::now()->subSeconds($cooldownSeconds))) {
            $wait = max(1, $cooldownSeconds - Carbon::now()->diffInSeconds($lastSentAt));
            $this->logBlocked($phone, $user->id, $ip, $deviceId, $type, OtpRequestLog::STATUS_BLOCKED_COOLDOWN, $requestId);

            return [
                'reason' => self::REASON_COOLDOWN,
                'message' => "يرجى الانتظار {$wait} ثانية قبل إعادة الإرسال",
                'http_status' => 429,
                'code' => null,
                'wait_seconds' => $wait,
            ];
        }

        if ($this->countPhoneSentSince($phone, Carbon::now()->subMinutes(10)) >= (int) config('otp.max_per_phone_10_minutes')) {
            $this->logBlocked($phone, $user->id, $ip, $deviceId, $type, OtpRequestLog::STATUS_BLOCKED_PHONE_10MIN, $requestId);

            return [
                'reason' => self::REASON_LIMIT_10MIN,
                'message' => 'لقد طلبت رمز التحقق عدة مرات، يرجى المحاولة لاحقاً',
                'http_status' => 429,
                'code' => null,
            ];
        }

        if ($this->countPhoneSentSince($phone, $this->dailyWindowStart($user)) >= (int) config('otp.max_per_phone_per_day')) {
            $this->logBlocked($phone, $user->id, $ip, $deviceId, $type, OtpRequestLog::STATUS_BLOCKED_PHONE_DAILY, $requestId);

            return [
                'reason' => self::REASON_LIMIT_DAILY,
                'message' => 'تجاوزت الحد المسموح به من محاولات التحقق اليوم، يرجى المحاولة لاحقاً',
                'http_status' => 429,
                'code' => null,
            ];
        }

        if ($this->countIpSentSince($ip, Carbon::now()->subHour()) >= (int) config('otp.max_per_ip_per_hour')) {
            $this->logBlocked($phone, $user->id, $ip, $deviceId, $type, OtpRequestLog::STATUS_BLOCKED_IP_HOURLY, $requestId);

            return [
                'reason' => self::REASON_LIMIT_IP,
                'message' => 'تم إيقاف الطلبات مؤقتاً من هذا الاتصال، يرجى المحاولة لاحقاً',
                'http_status' => 429,
                'code' => null,
            ];
        }

        if ($deviceId && $this->countDeviceSentSince($deviceId, Carbon::now()->subHour()) >= (int) config('otp.max_per_device_per_hour')) {
            $this->logBlocked($phone, $user->id, $ip, $deviceId, $type, OtpRequestLog::STATUS_BLOCKED_DEVICE_SUSPICIOUS, $requestId);

            return [
                'reason' => self::REASON_SUSPICIOUS,
                'message' => 'تم إيقاف الطلبات مؤقتاً من هذا الجهاز، يرجى المحاولة لاحقاً',
                'http_status' => 429,
                'code' => null,
            ];
        }

        // Distinct-phone abuse signals: many different numbers requested
        // from the same IP/device within an hour is spam-shaped even when
        // no single number is individually over its own limit yet — this
        // is what stops an attacker from bypassing the per-phone limits
        // simply by rotating through numbers. A phone the IP/device has
        // already messaged this hour is exempt, so a legitimate user
        // retrying their own number never trips this.
        if (!$this->hasRecentEntryFor('ip_address', $ip, $phone)
            && $this->countDistinctPhonesSince('ip_address', $ip, Carbon::now()->subHour()) >= (int) config('otp.max_distinct_phones_per_ip_per_hour')) {
            $this->logBlocked($phone, $user->id, $ip, $deviceId, $type, OtpRequestLog::STATUS_BLOCKED_IP_SUSPICIOUS, $requestId);

            return [
                'reason' => self::REASON_SUSPICIOUS,
                'message' => 'تم إيقاف الطلبات مؤقتاً من هذا الاتصال، يرجى المحاولة لاحقاً',
                'http_status' => 429,
                'code' => null,
            ];
        }

        if ($deviceId
            && !$this->hasRecentEntryFor('device_id', $deviceId, $phone)
            && $this->countDistinctPhonesSince('device_id', $deviceId, Carbon::now()->subHour()) >= (int) config('otp.max_distinct_phones_per_device_per_hour')) {
            $this->logBlocked($phone, $user->id, $ip, $deviceId, $type, OtpRequestLog::STATUS_BLOCKED_DEVICE_SUSPICIOUS, $requestId);

            return [
                'reason' => self::REASON_SUSPICIOUS,
                'message' => 'تم إيقاف الطلبات مؤقتاً من هذا الجهاز، يرجى المحاولة لاحقاً',
                'http_status' => 429,
                'code' => null,
            ];
        }

        return null;
    }

    /**
     * Never pass the OTP code itself here — only masked/safe metadata.
     */
    private function logBlocked(string $phone, int $userId, string $ip, ?string $deviceId, string $type, string $status, string $requestId): void
    {
        OtpRequestLog::create([
            'phone' => $phone,
            'user_id' => $userId,
            'ip_address' => $ip,
            'device_id' => $deviceId,
            'type' => $type,
            'status' => $status,
            'request_id' => $requestId,
        ]);

        Log::info('OTP request blocked', [
            'request_id' => $requestId,
            'phone' => $this->maskPhone($phone),
            'user_id' => $userId,
            'ip' => $ip,
            'device_id' => $deviceId,
            'type' => $type,
            'status' => $status,
        ]);
    }

    private function lastSentAt(string $phone): ?Carbon
    {
        return OtpRequestLog::where('phone', $phone)
            ->where('status', OtpRequestLog::STATUS_SENT)
            ->orderByDesc('id')
            ->value('created_at');
    }

    private function countPhoneSentSince(string $phone, Carbon $since): int
    {
        return OtpRequestLog::where('phone', $phone)
            ->where('status', OtpRequestLog::STATUS_SENT)
            ->where('created_at', '>=', $since)
            ->count();
    }

    private function countIpSentSince(string $ip, Carbon $since): int
    {
        return OtpRequestLog::where('ip_address', $ip)
            ->where('status', OtpRequestLog::STATUS_SENT)
            ->where('created_at', '>=', $since)
            ->count();
    }

    private function countDeviceSentSince(string $deviceId, Carbon $since): int
    {
        return OtpRequestLog::where('device_id', $deviceId)
            ->where('status', OtpRequestLog::STATUS_SENT)
            ->where('created_at', '>=', $since)
            ->count();
    }

    private function countDistinctPhonesSince(string $column, string $value, Carbon $since): int
    {
        return OtpRequestLog::where($column, $value)
            ->where('created_at', '>=', $since)
            ->distinct()
            ->count('phone');
    }

    private function hasRecentEntryFor(string $column, string $value, string $phone): bool
    {
        return OtpRequestLog::where($column, $value)
            ->where('phone', $phone)
            ->where('created_at', '>=', Carbon::now()->subHour())
            ->exists();
    }

    private function dailyWindowStart(User $user): Carbon
    {
        $since = Carbon::now()->subDay();

        if ($user->otp_counter_reset_at && $user->otp_counter_reset_at->gt($since)) {
            return $user->otp_counter_reset_at;
        }

        return $since;
    }

    /**
     * Ramps from initial_per_minute up to max_per_minute in steps of
     * initial_per_minute, once per increase_interval_hours, counted from
     * the first time this is ever evaluated (persisted in cache) unless
     * an explicit ramp_started_at is configured. This seed timestamp is
     * a convenience default only — not itself a source of race risk,
     * since the actual capacity check/reservation below runs under
     * withMutex().
     */
    private function currentGlobalRateLimit(): int
    {
        $config = config('otp.global_rate');
        $initial = max(1, (int) $config['initial_per_minute']);
        $max = max($initial, (int) $config['max_per_minute']);
        $intervalHours = max(1, (int) $config['increase_interval_hours']);

        $rampStart = $config['ramp_started_at']
            ? Carbon::parse($config['ramp_started_at'])
            : Cache::rememberForever('otp:global_rate:ramp_started_at', fn () => Carbon::now());

        $elapsedIntervals = (int) floor(Carbon::now()->diffInHours($rampStart) / $intervalHours);

        return (int) min($max, $initial + ($initial * $elapsedIntervals));
    }

    /**
     * Read-only capacity check — must be called from inside withMutex()
     * to be race-safe, since the "reservation" is just the OtpRequestLog
     * row the caller inserts right after this returns true.
     */
    private function hasGlobalCapacity(): bool
    {
        $burst = max(1, (int) config('otp.global_rate.burst_limit'));

        if ($this->countGlobalSentSince(Carbon::now()->subSeconds(10)) >= $burst) {
            return false;
        }

        return $this->countGlobalSentSince(Carbon::now()->subMinute()) < $this->currentGlobalRateLimit();
    }

    private function countGlobalSentSince(Carbon $since): int
    {
        return OtpRequestLog::where('status', OtpRequestLog::STATUS_SENT)
            ->where('created_at', '>=', $since)
            ->count();
    }

    private function maskPhone(string $phone): string
    {
        $length = strlen($phone);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . substr($phone, -4);
    }
}

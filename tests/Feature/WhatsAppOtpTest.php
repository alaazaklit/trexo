<?php

namespace Tests\Feature;

use App\Models\User;
use App\OtpRequestLog;
use App\Services\OtpService;
use App\Services\UnlimitedMessagingService;
use App\VerificationCode;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppOtpTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.unlimited_messaging.api_url' => 'https://api.unlimitedmessaging.app',
            'services.unlimited_messaging.api_token' => 'test-token-123',
        ]);

        // Hard backstop: the testing environment is already hard-locked to
        // mock mode inside OtpService::resolveProviderMode() with no
        // config override able to escape it, but this makes the guarantee
        // doubly explicit at the HTTP-client level too — any code path
        // that tried to make a real call Laravel hasn't been told to fake
        // would throw immediately instead of silently reaching the
        // network. Every test in this class relies on this being true
        // rather than on remembering to call Http::fake().
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        // Static test-only override — must never leak into the next test.
        OtpService::forceProviderModeForTesting(null);

        parent::tearDown();
    }

    public function test_request_otp_creates_a_six_digit_code_expiring_in_five_minutes_and_is_mocked_not_really_sent(): void
    {
        $response = $this->postJson('/api/requestOtp', ['phone' => '71234567']);

        $response->assertStatus(200)->assertJson(['result' => true]);

        $user = User::where('phone', '71234567')->firstOrFail();
        $code = VerificationCode::where('user_id', $user->id)->latest('id')->firstOrFail();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code->code);
        $this->assertFalse((bool) $code->used);
        $expiresAt = Carbon::parse($code->expires_at);
        $this->assertTrue($expiresAt->between(Carbon::now()->addMinutes(4), Carbon::now()->addMinutes(6)));

        // Nothing was actually sent — Http::preventStrayRequests() would
        // have thrown if OtpService had tried to reach the real provider.
        Http::assertNothingSent();

        $log = OtpRequestLog::where('phone', '71234567')->where('status', 'sent')->latest('id')->firstOrFail();
        $this->assertSame('mock', $log->provider_mode);
    }

    public function test_request_otp_resend_cooldown_blocks_immediate_second_request(): void
    {
        $this->postJson('/api/requestOtp', ['phone' => '71234568'])->assertStatus(200);

        $response = $this->postJson('/api/requestOtp', ['phone' => '71234568']);

        $response->assertStatus(429)->assertJson(['result' => false]);
        Http::assertNothingSent();
    }

    public function test_request_otp_invalidates_previous_code_by_issuing_a_new_one_after_cooldown(): void
    {
        $this->postJson('/api/requestOtp', ['phone' => '71234569'])->assertStatus(200);

        $user = User::where('phone', '71234569')->firstOrFail();
        $firstCode = VerificationCode::where('user_id', $user->id)->latest('id')->firstOrFail();

        // Move the cooldown clock (tracked in otp_request_logs, not on the
        // VerificationCode row) past the 60s window.
        OtpRequestLog::where('phone', '71234569')->where('status', 'sent')
            ->update(['created_at' => Carbon::now()->subSeconds(61)]);

        $this->postJson('/api/requestOtp', ['phone' => '71234569'])->assertStatus(200);

        $secondCode = VerificationCode::where('user_id', $user->id)->latest('id')->firstOrFail();

        $this->assertNotEquals($firstCode->id, $secondCode->id);

        // Only the newest unused/unexpired code should verify successfully.
        $verify = $this->postJson('/api/verifyOtp', ['phone' => '71234569', 'otp' => $secondCode->code]);
        $verify->assertStatus(200)->assertJson(['result' => true]);
    }

    public function test_verify_otp_succeeds_with_correct_code_and_marks_it_used(): void
    {
        $this->postJson('/api/requestOtp', ['phone' => '71234570'])->assertStatus(200);

        $user = User::where('phone', '71234570')->firstOrFail();
        $code = VerificationCode::where('user_id', $user->id)->latest('id')->firstOrFail();

        $response = $this->postJson('/api/verifyOtp', ['phone' => '71234570', 'otp' => $code->code]);

        $response->assertStatus(200)->assertJson(['result' => true]);
        $this->assertTrue((bool) $code->fresh()->used);
    }

    public function test_verify_otp_rejects_reuse_of_an_already_used_code(): void
    {
        $this->postJson('/api/requestOtp', ['phone' => '71234571'])->assertStatus(200);

        $user = User::where('phone', '71234571')->firstOrFail();
        $code = VerificationCode::where('user_id', $user->id)->latest('id')->firstOrFail();

        $this->postJson('/api/verifyOtp', ['phone' => '71234571', 'otp' => $code->code])
            ->assertStatus(200)->assertJson(['result' => true]);

        $response = $this->postJson('/api/verifyOtp', ['phone' => '71234571', 'otp' => $code->code]);

        $response->assertStatus(400)->assertJson(['result' => false]);
    }

    public function test_verify_otp_rejects_expired_code(): void
    {
        $this->postJson('/api/requestOtp', ['phone' => '71234572'])->assertStatus(200);

        $user = User::where('phone', '71234572')->firstOrFail();
        $code = VerificationCode::where('user_id', $user->id)->latest('id')->firstOrFail();
        $code->expires_at = Carbon::now()->subMinute();
        $code->save();

        $response = $this->postJson('/api/verifyOtp', ['phone' => '71234572', 'otp' => $code->code]);

        $response->assertStatus(400)->assertJson(['result' => false]);
    }

    public function test_verify_otp_rejects_incorrect_code(): void
    {
        $this->postJson('/api/requestOtp', ['phone' => '71234573'])->assertStatus(200);

        $response = $this->postJson('/api/verifyOtp', ['phone' => '71234573', 'otp' => '000000']);

        $response->assertStatus(400)->assertJson(['result' => false]);
    }

    public function test_verify_otp_resets_the_daily_counter(): void
    {
        $phone = '71234576';
        config(['otp.max_per_phone_per_day' => 1]);

        $this->postJson('/api/requestOtp', ['phone' => $phone])->assertStatus(200);

        $user = User::where('phone', $phone)->firstOrFail();
        $code = VerificationCode::where('user_id', $user->id)->latest('id')->firstOrFail();
        OtpRequestLog::where('phone', $phone)->where('status', 'sent')
            ->update(['created_at' => Carbon::now()->subSeconds(61)]);

        // Daily cap of 1 already used — a second request is blocked.
        $this->postJson('/api/requestOtp', ['phone' => $phone])->assertStatus(429);

        // Verifying the still-valid first code resets the daily counter.
        $this->postJson('/api/verifyOtp', ['phone' => $phone, 'otp' => $code->code])->assertStatus(200);

        OtpRequestLog::where('phone', $phone)->where('status', 'sent')
            ->update(['created_at' => Carbon::now()->subSeconds(61)]);

        $this->postJson('/api/requestOtp', ['phone' => $phone])->assertStatus(200);
    }

    /**
     * Exercises the 'live' dispatch branch's failure handling — without
     * ever making a real network call. UnlimitedMessagingService itself is
     * swapped for a pure Mockery double (see tests/Unit/UnlimitedMessagingServiceTest.php
     * for that class's own real-HTTP-layer coverage), and
     * forceProviderModeForTesting() is the one explicit, testing-only way
     * to make OtpService take the 'live' code path at all — it refuses to
     * run outside the testing environment, so this can never affect a
     * real deployment.
     */
    public function test_request_otp_returns_error_when_provider_fails_outside_debug_mode(): void
    {
        config(['app.debug' => false]);
        OtpService::forceProviderModeForTesting(OtpService::MODE_LIVE);

        $this->mock(UnlimitedMessagingService::class, function ($mock) {
            $mock->shouldReceive('sendWhatsAppMessage')->once()->andReturn(false);
        });

        $response = $this->postJson('/api/requestOtp', ['phone' => '71234574']);

        $response->assertStatus(502)->assertJson(['result' => false]);
        Http::assertNothingSent();

        $log = OtpRequestLog::where('phone', '71234574')->latest('id')->firstOrFail();
        $this->assertSame('send_failed', $log->status);
        $this->assertSame('live', $log->provider_mode);
    }

    public function test_request_otp_does_not_expose_debug_otp_when_not_in_debug_mode(): void
    {
        config(['app.debug' => false]);

        $response = $this->postJson('/api/requestOtp', ['phone' => '71234575']);

        $response->assertStatus(200)->assertJsonMissing(['debug_otp']);
    }

    public function test_provider_mode_is_hard_locked_to_mock_in_testing_even_if_config_says_live(): void
    {
        // An explicit attempt to force live mode via config must still
        // lose to the testing-environment guard — there is no config-only
        // way to make a test run hit the real provider.
        config(['otp.provider_mode' => 'live']);

        $service = app(OtpService::class);
        $this->assertSame(OtpService::MODE_MOCK, $service->resolveProviderMode());

        $this->postJson('/api/requestOtp', ['phone' => '71234577'])->assertStatus(200);
        Http::assertNothingSent();
    }
}

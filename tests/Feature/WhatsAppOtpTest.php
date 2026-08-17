<?php

namespace Tests\Feature;

use App\Models\User;
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
    }

    private function fakeSuccessfulSend(): void
    {
        Http::fake([
            'api.unlimitedmessaging.app/message' => Http::response(['id' => 'abc', 'status' => 'SENT'], 201),
        ]);
    }

    private function fakeFailedSend(): void
    {
        Http::fake([
            'api.unlimitedmessaging.app/message' => Http::response(['message' => 'server error'], 500),
        ]);
    }

    public function test_request_otp_creates_a_six_digit_code_expiring_in_ten_minutes_and_sends_via_whatsapp(): void
    {
        $this->fakeSuccessfulSend();

        $response = $this->postJson('/api/requestOtp', ['phone' => '71234567']);

        $response->assertStatus(200)->assertJson(['result' => true]);

        $user = User::where('phone', '71234567')->firstOrFail();
        $code = VerificationCode::where('user_id', $user->id)->latest('id')->firstOrFail();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code->code);
        $this->assertFalse((bool) $code->used);
        $expiresAt = Carbon::parse($code->expires_at);
        $this->assertTrue($expiresAt->between(Carbon::now()->addMinutes(9), Carbon::now()->addMinutes(11)));

        Http::assertSent(fn ($request) => $request['recipient'] === '+96171234567'
            && str_contains($request['text'], $code->code));
    }

    public function test_request_otp_resend_cooldown_blocks_immediate_second_request(): void
    {
        $this->fakeSuccessfulSend();

        $this->postJson('/api/requestOtp', ['phone' => '71234568'])->assertStatus(200);

        $response = $this->postJson('/api/requestOtp', ['phone' => '71234568']);

        $response->assertStatus(429)->assertJson(['result' => false]);
    }

    public function test_request_otp_invalidates_previous_code_by_issuing_a_new_one_after_cooldown(): void
    {
        $this->fakeSuccessfulSend();

        $this->postJson('/api/requestOtp', ['phone' => '71234569'])->assertStatus(200);

        $user = User::where('phone', '71234569')->firstOrFail();
        $firstCode = VerificationCode::where('user_id', $user->id)->latest('id')->firstOrFail();

        // Move past the 60s cooldown.
        $firstCode->created_at = Carbon::now()->subSeconds(61);
        $firstCode->save();

        $this->postJson('/api/requestOtp', ['phone' => '71234569'])->assertStatus(200);

        $secondCode = VerificationCode::where('user_id', $user->id)->latest('id')->firstOrFail();

        $this->assertNotEquals($firstCode->id, $secondCode->id);

        // Only the newest unused/unexpired code should verify successfully.
        $verify = $this->postJson('/api/verifyOtp', ['phone' => '71234569', 'otp' => $secondCode->code]);
        $verify->assertStatus(200)->assertJson(['result' => true]);
    }

    public function test_verify_otp_succeeds_with_correct_code_and_marks_it_used(): void
    {
        $this->fakeSuccessfulSend();

        $this->postJson('/api/requestOtp', ['phone' => '71234570'])->assertStatus(200);

        $user = User::where('phone', '71234570')->firstOrFail();
        $code = VerificationCode::where('user_id', $user->id)->latest('id')->firstOrFail();

        $response = $this->postJson('/api/verifyOtp', ['phone' => '71234570', 'otp' => $code->code]);

        $response->assertStatus(200)->assertJson(['result' => true]);
        $this->assertTrue((bool) $code->fresh()->used);
    }

    public function test_verify_otp_rejects_reuse_of_an_already_used_code(): void
    {
        $this->fakeSuccessfulSend();

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
        $this->fakeSuccessfulSend();

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
        $this->fakeSuccessfulSend();

        $this->postJson('/api/requestOtp', ['phone' => '71234573'])->assertStatus(200);

        $response = $this->postJson('/api/verifyOtp', ['phone' => '71234573', 'otp' => '000000']);

        $response->assertStatus(400)->assertJson(['result' => false]);
    }

    public function test_request_otp_returns_error_when_provider_fails_outside_debug_mode(): void
    {
        config(['app.debug' => false]);
        $this->fakeFailedSend();

        $response = $this->postJson('/api/requestOtp', ['phone' => '71234574']);

        $response->assertStatus(502)->assertJson(['result' => false]);
    }

    public function test_request_otp_does_not_expose_debug_otp_when_not_in_debug_mode(): void
    {
        config(['app.debug' => false]);
        $this->fakeSuccessfulSend();

        $response = $this->postJson('/api/requestOtp', ['phone' => '71234575']);

        $response->assertStatus(200)->assertJsonMissing(['debug_otp']);
    }
}

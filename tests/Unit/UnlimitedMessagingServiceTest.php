<?php

namespace Tests\Unit;

use App\Services\UnlimitedMessagingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use TCG\Voyager\Models\Setting;
use Tests\TestCase;

class UnlimitedMessagingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function configureProvider(): void
    {
        config([
            'services.unlimited_messaging.api_url' => 'https://api.unlimitedmessaging.app',
            'services.unlimited_messaging.api_token' => 'test-token-123',
        ]);
    }

    private function setSimSettings(?string $primary, ?string $backup): void
    {
        Setting::updateOrCreate(['key' => 'messaging.whatsapp_sim_id'], ['value' => $primary ?? '']);
        Setting::updateOrCreate(['key' => 'messaging.whatsapp_backup_sim_id'], ['value' => $backup ?? '']);
    }

    public function test_sends_message_with_correct_request_shape_and_returns_true_on_success(): void
    {
        $this->configureProvider();

        Http::fake([
            'api.unlimitedmessaging.app/message' => Http::response(['id' => 'abc', 'status' => 'SENT'], 201),
        ]);

        $service = new UnlimitedMessagingService();
        $result = $service->sendWhatsAppMessage('71234567', 'Your Trexo verification code is: 123456');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.unlimitedmessaging.app/message'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer test-token-123')
                && $request['recipient'] === '+96171234567'
                && $request['text'] === 'Your Trexo verification code is: 123456';
        });
    }

    public function test_already_international_number_is_not_double_prefixed(): void
    {
        $this->configureProvider();

        Http::fake([
            'api.unlimitedmessaging.app/message' => Http::response(['id' => 'abc', 'status' => 'SENT'], 201),
        ]);

        $service = new UnlimitedMessagingService();
        $service->sendWhatsAppMessage('+96171234567', 'hello');

        Http::assertSent(fn ($request) => $request['recipient'] === '+96171234567');
    }

    public function test_returns_false_on_provider_http_error(): void
    {
        $this->configureProvider();

        Http::fake([
            'api.unlimitedmessaging.app/message' => Http::response(['message' => 'server error'], 500),
        ]);

        $service = new UnlimitedMessagingService();
        $result = $service->sendWhatsAppMessage('71234567', 'code 123456');

        $this->assertFalse($result);
    }

    public function test_returns_false_on_invalid_credentials(): void
    {
        $this->configureProvider();

        Http::fake([
            'api.unlimitedmessaging.app/message' => Http::response(['message' => 'unauthorized'], 401),
        ]);

        $service = new UnlimitedMessagingService();
        $result = $service->sendWhatsAppMessage('71234567', 'code 123456');

        $this->assertFalse($result);
    }

    public function test_returns_false_on_provider_rate_limit_429(): void
    {
        $this->configureProvider();

        Http::fake([
            'api.unlimitedmessaging.app/message' => Http::response(['message' => 'too many requests'], 429),
        ]);

        $service = new UnlimitedMessagingService();
        $result = $service->sendWhatsAppMessage('71234567', 'code 123456');

        $this->assertFalse($result);
    }

    public function test_returns_false_on_connection_timeout(): void
    {
        $this->configureProvider();

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        });

        $service = new UnlimitedMessagingService();
        $result = $service->sendWhatsAppMessage('71234567', 'code 123456');

        $this->assertFalse($result);
    }

    public function test_returns_false_and_does_not_call_http_when_not_configured(): void
    {
        config([
            'services.unlimited_messaging.api_url' => null,
            'services.unlimited_messaging.api_token' => null,
        ]);

        Http::fake();

        $service = new UnlimitedMessagingService();
        $result = $service->sendWhatsAppMessage('71234567', 'code 123456');

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_returns_false_for_invalid_phone_number(): void
    {
        $this->configureProvider();

        Http::fake();

        $service = new UnlimitedMessagingService();
        // Normalizes to an empty/too-short number, must fail validation before any HTTP call.
        $result = $service->sendWhatsAppMessage('abc', 'code 123456');

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_never_logs_the_otp_code_or_api_token(): void
    {
        $this->configureProvider();

        Http::fake([
            'api.unlimitedmessaging.app/message' => Http::response(['message' => 'server error'], 500),
        ]);

        Log::spy();

        $service = new UnlimitedMessagingService();
        $service->sendWhatsAppMessage('71234567', 'Your Trexo verification code is: 654321');

        Log::shouldHaveReceived('error')
            ->withArgs(function ($message, $context = []) {
                $haystack = $message . json_encode($context);

                return !str_contains($haystack, '654321') && !str_contains($haystack, 'test-token-123');
            });
    }

    // --- primary/backup sim failover --------------------------------------

    public function test_uses_the_admin_configured_primary_sim_id_when_set(): void
    {
        $this->configureProvider();
        $this->setSimSettings(primary: 'sim_primary_123', backup: null);

        Http::fake(['api.unlimitedmessaging.app/message' => Http::response(['status' => 'SENT'], 201)]);

        $result = (new UnlimitedMessagingService())->sendWhatsAppMessage('71234567', 'code');

        $this->assertTrue($result);
        Http::assertSent(fn ($request) => $request['simId'] === 'sim_primary_123');
        Http::assertSentCount(1);
    }

    public function test_falls_back_to_env_sim_id_when_no_setting_is_configured(): void
    {
        $this->configureProvider();
        config(['services.unlimited_messaging.sim_id' => 'sim_from_env']);
        $this->setSimSettings(primary: null, backup: null);

        Http::fake(['api.unlimitedmessaging.app/message' => Http::response(['status' => 'SENT'], 201)]);

        (new UnlimitedMessagingService())->sendWhatsAppMessage('71234567', 'code');

        Http::assertSent(fn ($request) => $request['simId'] === 'sim_from_env');
    }

    public function test_retries_on_backup_sim_when_primary_send_fails(): void
    {
        $this->configureProvider();
        $this->setSimSettings(primary: 'sim_blocked', backup: 'sim_backup');

        $attempt = 0;
        Http::fake(function ($request) use (&$attempt) {
            $attempt++;
            // First attempt (primary, blocked) fails; second (backup) succeeds.
            return $attempt === 1
                ? Http::response(['message' => 'number blocked'], 403)
                : Http::response(['status' => 'SENT'], 201);
        });

        $result = (new UnlimitedMessagingService())->sendWhatsAppMessage('71234567', 'code');

        $this->assertTrue($result);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['simId'] === 'sim_blocked');
        Http::assertSent(fn ($request) => $request['simId'] === 'sim_backup');
    }

    public function test_returns_false_when_both_primary_and_backup_sims_fail(): void
    {
        $this->configureProvider();
        $this->setSimSettings(primary: 'sim_blocked', backup: 'sim_also_blocked');

        Http::fake(['api.unlimitedmessaging.app/message' => Http::response(['message' => 'blocked'], 403)]);

        $result = (new UnlimitedMessagingService())->sendWhatsAppMessage('71234567', 'code');

        $this->assertFalse($result);
        Http::assertSentCount(2);
    }

    public function test_does_not_retry_when_no_backup_sim_is_configured(): void
    {
        $this->configureProvider();
        $this->setSimSettings(primary: 'sim_blocked', backup: null);

        Http::fake(['api.unlimitedmessaging.app/message' => Http::response(['message' => 'blocked'], 403)]);

        $result = (new UnlimitedMessagingService())->sendWhatsAppMessage('71234567', 'code');

        $this->assertFalse($result);
        Http::assertSentCount(1);
    }
}

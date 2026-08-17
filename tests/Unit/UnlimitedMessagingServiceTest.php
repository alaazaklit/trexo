<?php

namespace Tests\Unit;

use App\Services\UnlimitedMessagingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class UnlimitedMessagingServiceTest extends TestCase
{
    private function configureProvider(): void
    {
        config([
            'services.unlimited_messaging.api_url' => 'https://api.unlimitedmessaging.app',
            'services.unlimited_messaging.api_token' => 'test-token-123',
        ]);
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
}

<?php

namespace Tests\Unit;

use App\Services\OtpService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * OtpService::resolveProviderMode() is the single switch deciding whether
 * an OTP send reaches the real WhatsApp provider. These tests pin down
 * its behavior in all three environments this app actually runs in.
 */
class OtpProviderModeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        // Both the static test-only override and the container's 'env'
        // binding are global state — never let either leak into another
        // test. Restore 'env' to 'testing' FIRST: forceProviderModeForTesting()
        // itself refuses to run unless the environment is already
        // 'testing', so clearing the override has to happen after.
        $this->app->instance('env', 'testing');
        OtpService::forceProviderModeForTesting(null);

        parent::tearDown();
    }

    public function test_testing_environment_is_always_mock_with_no_config_override(): void
    {
        config(['otp.provider_mode' => 'live']);

        $service = app(OtpService::class);

        $this->assertSame(OtpService::MODE_MOCK, $service->resolveProviderMode());
    }

    public function test_testing_environment_ignores_even_an_uppercase_or_malformed_override(): void
    {
        config(['otp.provider_mode' => 'LIVE']);
        $service = app(OtpService::class);
        $this->assertSame(OtpService::MODE_MOCK, $service->resolveProviderMode());

        config(['otp.provider_mode' => 'not-a-real-mode']);
        $this->assertSame(OtpService::MODE_MOCK, $service->resolveProviderMode());
    }

    public function test_force_provider_mode_for_testing_refuses_to_run_outside_testing_environment(): void
    {
        $this->app->instance('env', 'production');

        $this->expectException(\RuntimeException::class);
        OtpService::forceProviderModeForTesting(OtpService::MODE_LIVE);
    }

    public function test_local_environment_defaults_to_mock(): void
    {
        $this->app->instance('env', 'local');
        config(['otp.provider_mode' => null]);

        $service = app(OtpService::class);

        $this->assertSame(OtpService::MODE_MOCK, $service->resolveProviderMode());
    }

    public function test_local_environment_goes_live_only_with_explicit_config(): void
    {
        $this->app->instance('env', 'local');
        config(['otp.provider_mode' => 'live']);

        $service = app(OtpService::class);

        $this->assertSame(OtpService::MODE_LIVE, $service->resolveProviderMode());
    }

    public function test_production_environment_defaults_to_live_with_no_config_change(): void
    {
        $this->app->instance('env', 'production');
        config(['otp.provider_mode' => null]);

        $service = app(OtpService::class);

        $this->assertSame(OtpService::MODE_LIVE, $service->resolveProviderMode());
    }

    public function test_production_environment_can_be_forced_to_mock_explicitly(): void
    {
        $this->app->instance('env', 'production');
        config(['otp.provider_mode' => 'mock']);

        $service = app(OtpService::class);

        $this->assertSame(OtpService::MODE_MOCK, $service->resolveProviderMode());
    }

    public function test_staging_like_environment_defaults_to_mock_same_as_local(): void
    {
        $this->app->instance('env', 'staging');
        config(['otp.provider_mode' => null]);

        $service = app(OtpService::class);

        $this->assertSame(OtpService::MODE_MOCK, $service->resolveProviderMode());
    }
}

<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // 60/min was Laravel's untouched scaffold default — never actually
        // sized for this app. A single active user can easily have several
        // independent pollers running at once (dashboard pending-counts
        // every 20s, the driver-request popup every 8s, location pings
        // every 15s, order tracking every 8s, order details every 6s,
        // notifications every 25s), which sits close to 60/min on its own
        // before counting retries or a second screen. Once any small burst
        // tips a user over the old cap, every one of those pollers gets
        // rejected for the rest of that rolling minute, not just the one
        // that caused it. 300/min gives real headroom for that legitimate
        // load while still bounding a genuinely runaway client.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(300)->by($request->user()?->id ?: $request->ip());
        });

        // Tighter than the general 'api' limiter: /refresh is unauthenticated
        // (it's how you get authenticated) and a plaintext token guess is
        // the only thing standing between an attacker and a live session,
        // so it's rate-limited by IP specifically to slow down brute-force
        // guessing of refresh tokens.
        RateLimiter::for('refresh', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}

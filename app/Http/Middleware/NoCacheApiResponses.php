<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Every /api/* response is personalized per-request (driver/seller-specific
 * data behind a bearer token), so it must never be cached by any
 * intermediate layer — a reverse proxy/CDN that caches by URL alone,
 * ignoring the Authorization header, would otherwise serve one user's
 * response to a completely different user hitting the same route. This
 * is exactly what was observed live: a stale cached /api/profile response
 * served regardless of which valid token was presented. Explicit no-store
 * is the standard signal telling a compliant cache to never keep this.
 */
class NoCacheApiResponses
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}

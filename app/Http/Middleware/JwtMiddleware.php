<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    // public function handle(Request $request, Closure $next)
    // {
    //     // Check if the token is valid
    //     if (!JWTAuth::parseToken()->authenticate()) {
    //         return response()->json(['error' => 'Unauthorized'], 401);
    //     }

    //     return $next($request);
    // }


    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken(); // Get the token from the request
    
        if (!$this->validateTokenFormat($token)) {
            return response()->json(['error' => 'Invalid token format'], 400);
        }
    
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            return response()->json(['error' => 'Token expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Token invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Token absent'], 401);
        }
    
        return $next($request);
    }

    protected function handleInvalidTokenFormat()
{
    return response()->json(['error' => 'Invalid token format'], 400);
}

public function validateTokenFormat($token)
{
    $segments = explode('.', $token);

    if (count($segments) !== 3) {
        return false; // Invalid token format
    }

    return true; // Valid token format
}
}

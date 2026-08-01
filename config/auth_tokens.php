<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Refresh token lifetime (days)
    |--------------------------------------------------------------------------
    |
    | How long a refresh token remains valid before the user must log in
    | again from scratch (a fresh OTP verification). Access tokens are
    | short-lived (see JWT_TTL in config/jwt.php) and get silently renewed
    | using this refresh token in the meantime — see
    | UsersController::refresh(). Rotated on every use (the old refresh
    | token is revoked as soon as a new one is issued), so an active user
    | never actually hits this expiry as long as they keep using the app at
    | least once every N days.
    |
    */

    'refresh_ttl_days' => env('REFRESH_TOKEN_TTL_DAYS', 30),

];

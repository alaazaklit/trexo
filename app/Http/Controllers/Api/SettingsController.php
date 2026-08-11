<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use TCG\Voyager\Models\Setting;
use Tymon\JWTAuth\Facades\JWTAuth;

class SettingsController extends Controller
{
    // Admin-editable (see the 'pricing.exchange_rate_lbp_usd' Setting, edited
    // at /admin/settings) rather than hardcoded — LBP/USD isn't pegged, so
    // this needs to be adjustable without shipping a new app build. Cached
    // client-side after the first fetch (see currency_format.dart).
    public function exchangeRate()
    {
        JWTAuth::parseToken()->authenticate();

        $value = Setting::where('key', 'pricing.exchange_rate_lbp_usd')->value('value');
        $rate = $value === null || $value === '' ? 89500.0 : (float) $value;

        return response()->json([
            'result' => true,
            'message' => 'Exchange rate loaded',
            'data' => ['lbp_per_usd' => $rate],
        ]);
    }
}

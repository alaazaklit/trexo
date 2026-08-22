<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use TCG\Voyager\Models\Setting;

// JSON counterpart to the 'Maps' group on Voyager's own /admin/settings page
// (both read/write the same two Setting rows) — this exists for scripted/
// programmatic toggling (e.g. flipping either provider back to Google in a
// hurry if Mapbox has an outage) without navigating Voyager's generic
// settings grid.
class MapsConfigController extends Controller
{
    private const DIRECTIONS_PROVIDERS = ['google', 'mapbox'];
    private const PLACES_PROVIDERS = ['google', 'mapbox'];

    public function show(): JsonResponse
    {
        return response()->json([
            'result' => true,
            'data' => $this->currentConfig(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'directions_provider' => ['sometimes', 'string', 'in:'.implode(',', self::DIRECTIONS_PROVIDERS)],
            'places_provider' => ['sometimes', 'string', 'in:'.implode(',', self::PLACES_PROVIDERS)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 400);
        }

        if ($request->filled('directions_provider')) {
            Setting::where('key', 'maps.directions_provider')
                ->update(['value' => $request->input('directions_provider')]);
        }

        if ($request->filled('places_provider')) {
            Setting::where('key', 'maps.places_provider')
                ->update(['value' => $request->input('places_provider')]);
        }

        return response()->json([
            'result' => true,
            'message' => 'Maps configuration updated',
            'data' => $this->currentConfig(),
        ]);
    }

    private function currentConfig(): array
    {
        return [
            'directions_provider' => Setting::where('key', 'maps.directions_provider')->value('value') ?: 'mapbox',
            'places_provider' => Setting::where('key', 'maps.places_provider')->value('value') ?: 'google',
        ];
    }
}

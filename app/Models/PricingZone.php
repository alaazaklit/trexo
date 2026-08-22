<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingZone extends Model
{
    protected $fillable = [
        'name',
        'keywords',
        'hub_lat',
        'hub_lng',
        'base_fare_taxi',
        'base_fare_delivery',
        'per_km_taxi',
        'per_km_delivery',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'hub_lat' => 'decimal:7',
        'hub_lng' => 'decimal:7',
        'base_fare_taxi' => 'decimal:2',
        'base_fare_delivery' => 'decimal:2',
        'per_km_taxi' => 'decimal:2',
        'per_km_delivery' => 'decimal:2',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    // City/region text from Google's geocoder comes back wildly
    // inconsistent (language, transliteration — "Sidon"/"Saida"/"صيدا" are
    // all the same place), so this checks whether ANY of this zone's
    // comma-separated keywords appears as a substring of either field,
    // case-insensitively, rather than requiring an exact match.
    public function matchesLocation(?string $city, ?string $region): bool
    {
        $haystack = mb_strtolower(trim(($city ?? '') . ' ' . ($region ?? '')));
        if ($haystack === '') {
            return false;
        }

        foreach (explode(',', $this->keywords ?? '') as $keyword) {
            $needle = mb_strtolower(trim($keyword));
            if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}

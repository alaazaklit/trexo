<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Corrects the tiered fare values from 2026_07_31_000005: those were USD
// figures picked without a real-world reference and came out far too high.
// Recalibrated against the site owner's actual LBP reference points for
// Sidon (150,000 LBP local ride ≈ $1.68, 200,000 LBP to its outskirts ≈
// $2.24, at 89,500 LBP/USD) — implying a base fare around $1.25-1.30 and
// ~$0.20/km for a "core city" zone, not the previous $2.50/$1.20. Other
// tiers scaled down proportionally, per the site owner's request to bias
// toward the lower price throughout.
return new class extends Migration
{
    private function tiers(): array
    {
        return [
            'major' => [
                'fares' => ['base_fare_taxi' => 1.50, 'base_fare_delivery' => 1.75, 'per_km_taxi' => 0.25, 'per_km_delivery' => 0.20],
                'zones' => ['Beirut & Mount Lebanon'],
            ],
            'core_city' => [
                'fares' => ['base_fare_taxi' => 1.25, 'base_fare_delivery' => 1.50, 'per_km_taxi' => 0.20, 'per_km_delivery' => 0.17],
                'zones' => [
                    'Sidon (Saida)', 'Tyre (Sour)', 'Tripoli', 'Zahle', 'Baalbek',
                    'Byblos (Jbeil)', 'Jounieh', 'Nabatieh',
                ],
            ],
            'small_remote' => [
                'fares' => ['base_fare_taxi' => 1.10, 'base_fare_delivery' => 1.30, 'per_km_taxi' => 0.17, 'per_km_delivery' => 0.14],
                'zones' => [
                    'Batroun', 'Jezzine', 'Chouf (Beiteddine)', 'Chtaura', 'Rachaya',
                    'Marjeyoun', 'Bint Jbeil', 'Hasbaya', 'Zgharta', 'Bcharre',
                    'Koura (Amioun)', 'Akkar (Halba)', 'Bekaa & Rural Areas',
                ],
            ],
        ];
    }

    // Values this migration is replacing, so down() can restore them exactly.
    private function previousTiers(): array
    {
        return [
            'major' => [
                'fares' => ['base_fare_taxi' => 3.00, 'base_fare_delivery' => 3.50, 'per_km_taxi' => 1.30, 'per_km_delivery' => 1.10],
                'zones' => ['Beirut & Mount Lebanon'],
            ],
            'core_city' => [
                'fares' => ['base_fare_taxi' => 2.50, 'base_fare_delivery' => 3.00, 'per_km_taxi' => 1.20, 'per_km_delivery' => 1.00],
                'zones' => [
                    'Sidon (Saida)', 'Tyre (Sour)', 'Tripoli', 'Zahle', 'Baalbek',
                    'Byblos (Jbeil)', 'Jounieh', 'Nabatieh',
                ],
            ],
            'small_remote' => [
                'fares' => ['base_fare_taxi' => 2.25, 'base_fare_delivery' => 2.75, 'per_km_taxi' => 1.10, 'per_km_delivery' => 0.90],
                'zones' => [
                    'Batroun', 'Jezzine', 'Chouf (Beiteddine)', 'Chtaura', 'Rachaya',
                    'Marjeyoun', 'Bint Jbeil', 'Hasbaya', 'Zgharta', 'Bcharre',
                    'Koura (Amioun)', 'Akkar (Halba)', 'Bekaa & Rural Areas',
                ],
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->tiers() as $tier) {
            DB::table('pricing_zones')
                ->whereIn('name', $tier['zones'])
                ->update(array_merge($tier['fares'], ['updated_at' => now()]));
        }
    }

    public function down(): void
    {
        foreach ($this->previousTiers() as $tier) {
            DB::table('pricing_zones')
                ->whereIn('name', $tier['zones'])
                ->update(array_merge($tier['fares'], ['updated_at' => now()]));
        }
    }
};

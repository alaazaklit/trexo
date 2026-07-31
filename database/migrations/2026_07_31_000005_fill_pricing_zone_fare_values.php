<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Fills in the previously-blank per-zone base_fare_*/per_km_* columns
// (they fell back to the global fare.* settings until now) with tiered
// values reviewed and approved by the site owner: Beirut is the busiest/
// highest-demand zone, established cities match the prior global default,
// and smaller/remote towns are priced slightly lower to stay competitive.
return new class extends Migration
{
    private function tiers(): array
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
        $allZoneNames = collect($this->tiers())->flatMap(fn ($tier) => $tier['zones'])->all();

        DB::table('pricing_zones')
            ->whereIn('name', $allZoneNames)
            ->update([
                'base_fare_taxi' => null,
                'base_fare_delivery' => null,
                'per_km_taxi' => null,
                'per_km_delivery' => null,
                'updated_at' => now(),
            ]);
    }
};

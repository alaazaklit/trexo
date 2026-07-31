<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Second expansion pass: adds 10 more specific city/region zones (higher
// priority than the broad catch-alls, same as the first batch in
// 2026_07_31_000003) and 13 new intercity routes connecting them — mostly
// to the Beirut hub, plus a few regional connections between adjacent new
// zones. Fares are estimates reviewed and approved by the site owner.
return new class extends Migration
{
    private function newZones(): array
    {
        return [
            ['name' => 'Chouf (Beiteddine)', 'keywords' => 'Chouf,الشوف,Beiteddine,بيت الدين,Deir el Qamar,دير القمر', 'priority' => 10],
            ['name' => 'Chtaura', 'keywords' => 'Chtaura,شتورا', 'priority' => 10],
            ['name' => 'Rachaya', 'keywords' => 'Rachaya,راشيا', 'priority' => 10],
            ['name' => 'Marjeyoun', 'keywords' => 'Marjeyoun,مرجعيون', 'priority' => 10],
            ['name' => 'Bint Jbeil', 'keywords' => 'Bint Jbeil,بنت جبيل', 'priority' => 10],
            ['name' => 'Hasbaya', 'keywords' => 'Hasbaya,حاصبيا', 'priority' => 10],
            ['name' => 'Zgharta', 'keywords' => 'Zgharta,زغرتا', 'priority' => 10],
            ['name' => 'Bcharre', 'keywords' => 'Bcharre,Bsharri,بشري', 'priority' => 10],
            ['name' => 'Koura (Amioun)', 'keywords' => 'Koura,الكورة,Amioun,اميون', 'priority' => 10],
            ['name' => 'Akkar (Halba)', 'keywords' => 'Akkar,عكار,Halba,حلبا', 'priority' => 10],
        ];
    }

    private function newRoutes(): array
    {
        // [fromZoneName, toZoneName, fixed_fare_taxi, fixed_fare_delivery]
        return [
            ['Beirut & Mount Lebanon', 'Chouf (Beiteddine)', 26.00, 21.00],
            ['Beirut & Mount Lebanon', 'Chtaura', 28.00, 22.00],
            ['Beirut & Mount Lebanon', 'Rachaya', 46.00, 37.00],
            ['Beirut & Mount Lebanon', 'Marjeyoun', 50.00, 40.00],
            ['Beirut & Mount Lebanon', 'Bint Jbeil', 58.00, 46.00],
            ['Beirut & Mount Lebanon', 'Hasbaya', 48.00, 38.00],
            ['Beirut & Mount Lebanon', 'Zgharta', 50.00, 40.00],
            ['Beirut & Mount Lebanon', 'Bcharre', 62.00, 50.00],
            ['Beirut & Mount Lebanon', 'Koura (Amioun)', 46.00, 37.00],
            ['Beirut & Mount Lebanon', 'Akkar (Halba)', 66.00, 53.00],
            ['Zgharta', 'Bcharre', 14.00, 11.00],
            ['Hasbaya', 'Marjeyoun', 14.00, 11.00],
            ['Akkar (Halba)', 'Tripoli', 20.00, 16.00],
        ];
    }

    public function up(): void
    {
        $now = now();

        foreach ($this->newZones() as $zone) {
            if (DB::table('pricing_zones')->where('name', $zone['name'])->exists()) {
                continue;
            }

            DB::table('pricing_zones')->insert([
                'name' => $zone['name'],
                'keywords' => $zone['keywords'],
                'base_fare_taxi' => null,
                'base_fare_delivery' => null,
                'per_km_taxi' => null,
                'per_km_delivery' => null,
                'priority' => $zone['priority'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($this->newRoutes() as [$fromName, $toName, $taxiFare, $deliveryFare]) {
            $fromZoneId = DB::table('pricing_zones')->where('name', $fromName)->value('id');
            $toZoneId = DB::table('pricing_zones')->where('name', $toName)->value('id');

            if (!$fromZoneId || !$toZoneId) {
                continue;
            }

            $exists = DB::table('intercity_routes')
                ->where('from_zone_id', $fromZoneId)
                ->where('to_zone_id', $toZoneId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('intercity_routes')->insert([
                'from_zone_id' => $fromZoneId,
                'to_zone_id' => $toZoneId,
                'fixed_fare_taxi' => $taxiFare,
                'fixed_fare_delivery' => $deliveryFare,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->newRoutes() as [$fromName, $toName, $taxiFare, $deliveryFare]) {
            $fromZoneId = DB::table('pricing_zones')->where('name', $fromName)->value('id');
            $toZoneId = DB::table('pricing_zones')->where('name', $toName)->value('id');

            if ($fromZoneId && $toZoneId) {
                DB::table('intercity_routes')
                    ->where('from_zone_id', $fromZoneId)
                    ->where('to_zone_id', $toZoneId)
                    ->delete();
            }
        }

        foreach ($this->newZones() as $zone) {
            DB::table('pricing_zones')->where('name', $zone['name'])->delete();
        }
    }
};

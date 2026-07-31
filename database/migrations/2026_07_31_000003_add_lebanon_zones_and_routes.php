<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Expands the existing 5 broad Lebanese zones with 7 more specific city
// zones (higher priority so they win over the broad catch-alls for their
// area), fills in the previously-blank delivery fare on the 4 existing
// intercity routes, and adds 8 new intercity routes connecting the new
// zones. Fares are estimates reviewed and approved by the site owner —
// not derived from any pricing API.
return new class extends Migration
{
    private function newZones(): array
    {
        return [
            ['name' => 'Byblos (Jbeil)', 'keywords' => 'Byblos,Jbeil,جبيل', 'priority' => 10],
            ['name' => 'Jounieh', 'keywords' => 'Jounieh,جونية', 'priority' => 10],
            ['name' => 'Batroun', 'keywords' => 'Batroun,البترون', 'priority' => 10],
            ['name' => 'Zahle', 'keywords' => 'Zahle,Zahlé,زحلة', 'priority' => 10],
            ['name' => 'Baalbek', 'keywords' => 'Baalbek,Baalbeck,بعلبك', 'priority' => 10],
            ['name' => 'Nabatieh', 'keywords' => 'Nabatieh,Nabatiye,النبطية', 'priority' => 10],
            ['name' => 'Jezzine', 'keywords' => 'Jezzine,Jazzine,جزين', 'priority' => 10],
        ];
    }

    private function existingRouteDeliveryFares(): array
    {
        // [fromZoneName, toZoneName, fixed_fare_delivery]
        return [
            ['Beirut & Mount Lebanon', 'Sidon (Saida)', 26.00],
            ['Beirut & Mount Lebanon', 'Tyre (Sour)', 44.00],
            ['Beirut & Mount Lebanon', 'Tripoli', 36.00],
            ['Sidon (Saida)', 'Tyre (Sour)', 10.00],
        ];
    }

    private function newRoutes(): array
    {
        // [fromZoneName, toZoneName, fixed_fare_taxi, fixed_fare_delivery]
        return [
            ['Beirut & Mount Lebanon', 'Byblos (Jbeil)', 25.00, 20.00],
            ['Beirut & Mount Lebanon', 'Batroun', 28.00, 22.00],
            ['Beirut & Mount Lebanon', 'Zahle', 30.00, 24.00],
            ['Beirut & Mount Lebanon', 'Baalbek', 48.00, 38.00],
            ['Beirut & Mount Lebanon', 'Nabatieh', 40.00, 32.00],
            ['Beirut & Mount Lebanon', 'Jezzine', 42.00, 34.00],
            ['Zahle', 'Baalbek', 15.00, 12.00],
            ['Byblos (Jbeil)', 'Tripoli', 28.00, 22.00],
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

        foreach ($this->existingRouteDeliveryFares() as [$fromName, $toName, $deliveryFare]) {
            $fromZoneId = DB::table('pricing_zones')->where('name', $fromName)->value('id');
            $toZoneId = DB::table('pricing_zones')->where('name', $toName)->value('id');

            if (!$fromZoneId || !$toZoneId) {
                continue;
            }

            DB::table('intercity_routes')
                ->where('from_zone_id', $fromZoneId)
                ->where('to_zone_id', $toZoneId)
                ->update(['fixed_fare_delivery' => $deliveryFare, 'updated_at' => $now]);
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

        foreach ($this->existingRouteDeliveryFares() as [$fromName, $toName, $deliveryFare]) {
            $fromZoneId = DB::table('pricing_zones')->where('name', $fromName)->value('id');
            $toZoneId = DB::table('pricing_zones')->where('name', $toName)->value('id');

            if ($fromZoneId && $toZoneId) {
                DB::table('intercity_routes')
                    ->where('from_zone_id', $fromZoneId)
                    ->where('to_zone_id', $toZoneId)
                    ->update(['fixed_fare_delivery' => null]);
            }
        }

        foreach ($this->newZones() as $zone) {
            DB::table('pricing_zones')->where('name', $zone['name'])->delete();
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Best-effort town-center coordinates for every zone that existed when
// hub_lat/hub_lng was introduced (2026_08_22_000001) — same "reasonable
// starting value, admin corrects via Voyager if needed" treatment already
// given to base_fare_taxi/per_km_taxi in 2026_07_31_000005/000006. Zones
// created after this migration start with a null hub and simply can't power
// the bracket-pricing builder until an admin sets one.
return new class extends Migration
{
    private function coordinates(): array
    {
        return [
            'Beirut & Mount Lebanon' => [33.8938, 35.5018], // Downtown / Nejmeh Sq
            'Sidon (Saida)' => [33.5571, 35.3734], // Nejmeh Sq
            'Tyre (Sour)' => [33.2704, 35.2038],
            'Tripoli' => [34.4367, 35.8497],
            'Bekaa & Rural Areas' => [33.8206, 35.8534], // Chtaura crossroads
            'Byblos (Jbeil)' => [34.1232, 35.6519],
            'Jounieh' => [33.9808, 35.6178],
            'Batroun' => [34.2554, 35.6581],
            'Zahle' => [33.8547, 35.9019],
            'Baalbek' => [34.0059, 36.2075],
            'Nabatieh' => [33.3789, 35.4839],
            'Jezzine' => [33.5417, 35.5847],
            'Chouf (Beiteddine)' => [33.6939, 35.5822],
            'Chtaura' => [33.8206, 35.8534],
            'Rachaya' => [33.5061, 35.8497],
            'Marjeyoun' => [33.3608, 35.5919],
            'Bint Jbeil' => [33.1211, 35.4325],
            'Hasbaya' => [33.3997, 35.6836],
            'Zgharta' => [34.3986, 35.8925],
            'Bcharre' => [34.2517, 36.0117],
            'Koura (Amioun)' => [34.2989, 35.8194],
            'Akkar (Halba)' => [34.5389, 36.0778],
        ];
    }

    public function up(): void
    {
        foreach ($this->coordinates() as $name => [$lat, $lng]) {
            DB::table('pricing_zones')
                ->where('name', $name)
                ->update(['hub_lat' => $lat, 'hub_lng' => $lng, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('pricing_zones')
            ->whereIn('name', array_keys($this->coordinates()))
            ->update(['hub_lat' => null, 'hub_lng' => null, 'updated_at' => now()]);
    }
};

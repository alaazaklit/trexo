<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Adds Voyager's "description" tooltip (rendered as a hover-help icon next
// to the field label by tcg/voyager's DescriptionHandler) to the pricing
// zone / intercity route fields whose behavior isn't obvious from the
// label alone: keyword matching, priority tie-breaking, fixed-fare
// override semantics, and the from/to zone relationship fields.
return new class extends Migration
{
    private function plainDescriptions(): array
    {
        return [
            'pricing_zones' => [
                'keywords' => 'Comma-separated city/region names, any language or spelling (e.g. Sidon,Saida,صيدا). A ride matches this zone if any keyword appears in its pickup or destination address text.',
                'priority' => 'Breaks ties when a location matches more than one zone\'s keywords. Higher number wins — give specific/narrow zones a higher priority than broad catch-all zones.',
                'base_fare_taxi' => 'Leave blank to use the global default from Fare Settings.',
                'base_fare_delivery' => 'Leave blank to use the global default from Fare Settings.',
                'per_km_taxi' => 'Leave blank to use the global default from Fare Settings.',
                'per_km_delivery' => 'Leave blank to use the global default from Fare Settings.',
            ],
            'intercity_routes' => [
                'fixed_fare_taxi' => 'Flat fare for taxi rides between these two zones. Replaces the normal base fare + per-km calculation entirely — it does not add to it.',
                'fixed_fare_delivery' => 'Flat fare for delivery rides between these two zones. Replaces the normal base fare + per-km calculation entirely — it does not add to it.',
                'is_active' => 'Inactive routes are ignored when matching a ride\'s fare, even if both zones match.',
            ],
        ];
    }

    private function relationshipDescriptions(): array
    {
        return [
            'from_zone_id' => 'Pickup zone for this fixed-fare route. Matches a ride in either direction with To Zone.',
            'to_zone_id' => 'Destination zone for this fixed-fare route. Matches a ride in either direction with From Zone.',
        ];
    }

    public function up(): void
    {
        foreach ($this->plainDescriptions() as $table => $fields) {
            $dataTypeId = DB::table('data_types')->where('name', $table)->value('id');
            if (!$dataTypeId) {
                continue;
            }

            foreach ($fields as $field => $description) {
                DB::table('data_rows')
                    ->where('data_type_id', $dataTypeId)
                    ->where('field', $field)
                    ->update(['details' => json_encode(['description' => $description])]);
            }
        }

        $dataTypeId = DB::table('data_types')->where('name', 'intercity_routes')->value('id');
        if ($dataTypeId) {
            foreach ($this->relationshipDescriptions() as $field => $description) {
                $row = DB::table('data_rows')
                    ->where('data_type_id', $dataTypeId)
                    ->where('field', $field)
                    ->first();

                if (!$row) {
                    continue;
                }

                $details = json_decode($row->details, true) ?: [];
                $details['description'] = $description;

                DB::table('data_rows')
                    ->where('id', $row->id)
                    ->update(['details' => json_encode($details)]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->plainDescriptions() as $table => $fields) {
            $dataTypeId = DB::table('data_types')->where('name', $table)->value('id');
            if (!$dataTypeId) {
                continue;
            }

            foreach (array_keys($fields) as $field) {
                DB::table('data_rows')
                    ->where('data_type_id', $dataTypeId)
                    ->where('field', $field)
                    ->update(['details' => '{}']);
            }
        }

        $dataTypeId = DB::table('data_types')->where('name', 'intercity_routes')->value('id');
        if ($dataTypeId) {
            foreach (array_keys($this->relationshipDescriptions()) as $field) {
                $row = DB::table('data_rows')
                    ->where('data_type_id', $dataTypeId)
                    ->where('field', $field)
                    ->first();

                if (!$row) {
                    continue;
                }

                $details = json_decode($row->details, true) ?: [];
                unset($details['description']);

                DB::table('data_rows')
                    ->where('id', $row->id)
                    ->update(['details' => json_encode($details)]);
            }
        }
    }
};

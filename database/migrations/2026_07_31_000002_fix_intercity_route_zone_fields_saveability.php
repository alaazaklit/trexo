<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Voyager's own controller has two separate belongsTo-relationship
// special-cases that make from_zone_id/to_zone_id unusable through the
// generic BREAD flow:
//   - removeRelationshipField() strips them out of browseRows/editRows/
//     addRows before any view even renders (Http/Controllers/
//     VoyagerBaseController.php).
//   - insertUpdateData() unconditionally skips saving any row where
//     type == 'relationship' && details->type == 'belongsTo'
//     (Http/Controllers/Controller.php), regardless of which view rendered
//     the form.
// Since nothing in this app renders a custom browse/read view for
// intercity_routes, 'relationship' bought zero benefit here while making
// the fields unbrowsable, uneditable, and uncreateable. Switching the row
// type to plain 'text' opts them back into the normal save path (a plain
// column value from the request) — resources/views/vendor/voyager/
// intercity-routes/edit-add.blade.php still renders them as proper zone-name
// dropdowns, it just no longer relies on Voyager's relationship machinery.
return new class extends Migration
{
    private function fields(): array
    {
        return ['from_zone_id', 'to_zone_id'];
    }

    public function up(): void
    {
        $dataTypeId = DB::table('data_types')->where('name', 'intercity_routes')->value('id');
        if (!$dataTypeId) {
            return;
        }

        DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->whereIn('field', $this->fields())
            ->update(['type' => 'text']);
    }

    public function down(): void
    {
        $dataTypeId = DB::table('data_types')->where('name', 'intercity_routes')->value('id');
        if (!$dataTypeId) {
            return;
        }

        DB::table('data_rows')
            ->where('data_type_id', $dataTypeId)
            ->whereIn('field', $this->fields())
            ->update(['type' => 'relationship']);
    }
};

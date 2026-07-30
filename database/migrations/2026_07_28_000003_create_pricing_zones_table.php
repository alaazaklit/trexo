<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lets an admin set a different base fare / per-km rate per region instead
// of one flat rate for all of Lebanon (e.g. Sidon should not cost the same
// as Beirut). `keywords` is a comma-separated list of city/region
// substrings to match against an address's `city`/`region` columns —
// those columns come back wildly inconsistent (language, transliteration:
// "Sidon"/"Saida"/"صيدا" are all the same place), so exact matching on a
// single canonical name would silently miss most addresses. A free-text
// keyword list lets an admin register every spelling variant they see
// without a code change. `priority` breaks ties when more than one zone's
// keywords match (e.g. a specific "Sidon" zone should win over a broader
// "South Lebanon" catch-all zone that would otherwise also match).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_zones', function (Blueprint $table) {
            // The DB's default engine is MyISAM, which doesn't support real
            // foreign keys — drivers.pricing_zone_id references this table.
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('name');
            $table->text('keywords');
            $table->decimal('base_fare_taxi', 8, 2)->nullable();
            $table->decimal('base_fare_delivery', 8, 2)->nullable();
            $table->decimal('per_km_taxi', 8, 2)->nullable();
            $table->decimal('per_km_delivery', 8, 2)->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_zones');
    }
};

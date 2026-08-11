<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Schools are no longer admin-pre-seeded only — a driver adding a route can
// now search Google Places by name, and an unrecognized result gets turned
// into a School row on the fly (see SchoolController::resolveFromPlace).
// `place_id` is the dedup key: the same real-world school picked by two
// different drivers (or the same driver twice) must resolve to one row.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('place_id')->nullable()->unique()->after('id');
            $table->decimal('lat', 10, 7)->nullable()->after('area');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['place_id', 'lat', 'lng']);
        });
    }
};

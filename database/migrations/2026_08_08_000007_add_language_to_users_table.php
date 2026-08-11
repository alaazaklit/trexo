<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The backend has no way to know a driver's app language when composing a
// push notification asynchronously (e.g. a parent submitting a school-bus
// request) — the app's locale only exists client-side. This persists it so
// server-generated notification text can match the driver's language
// instead of always being English. Synced opportunistically from a couple
// of frequently-hit driver-facing endpoints (see SchoolBusRouteController::
// status() and SchoolBusSubscriptionController::counts()) rather than
// requiring a dedicated sync call.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('language', 5)->nullable()->after('fcm_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};

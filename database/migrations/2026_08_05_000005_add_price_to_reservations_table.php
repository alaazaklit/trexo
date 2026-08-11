<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'price')) {
                // Today a reservation's fare is only ever a quote shown
                // during driver selection (MatchesDriverSchedules) and is
                // never persisted. This column is filled once, at
                // completion time, by re-running that same fare formula —
                // see TransactionService::recordForReservation().
                $table->decimal('price', 10, 2)->nullable()->after('route_distance_km');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (Schema::hasColumn('reservations', 'price')) {
                $table->dropColumn('price');
            }
        });
    }
};

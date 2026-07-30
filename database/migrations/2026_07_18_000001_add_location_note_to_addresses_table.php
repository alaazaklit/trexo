<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            // Free-text guidance the seller adds when the pickup pin can't
            // resolve to a full street address (e.g. "blue gate, behind the
            // supermarket") so the driver still has something to go on.
            $table->text('location_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('location_note');
        });
    }
};

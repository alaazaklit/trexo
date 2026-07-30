<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            if (!Schema::hasColumn('drivers', 'vehicle_make')) {
                $table->string('vehicle_make', 60)->nullable()->after('vehicle_type');
            }

            if (!Schema::hasColumn('drivers', 'vehicle_model')) {
                $table->string('vehicle_model', 60)->nullable()->after('vehicle_make');
            }

            if (!Schema::hasColumn('drivers', 'vehicle_color')) {
                $table->string('vehicle_color', 40)->nullable()->after('vehicle_model');
            }

            if (!Schema::hasColumn('drivers', 'vehicle_plate')) {
                $table->string('vehicle_plate', 30)->nullable()->after('vehicle_color');
            }

            if (!Schema::hasColumn('drivers', 'transmission')) {
                $table->string('transmission', 20)->nullable()->after('vehicle_plate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $columns = [
                'transmission',
                'vehicle_plate',
                'vehicle_color',
                'vehicle_model',
                'vehicle_make',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('drivers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

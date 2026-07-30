<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            if (!Schema::hasColumn('drivers', 'national_id_number')) {
                $table->string('national_id_number', 60)->nullable()->after('license_number');
            }

            if (!Schema::hasColumn('drivers', 'vehicle_year')) {
                $table->unsignedSmallInteger('vehicle_year')->nullable()->after('vehicle_plate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $columns = ['national_id_number', 'vehicle_year'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('drivers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The public form no longer collects vehicle/license details or the
     * three document uploads (dropped to reduce first-launch signup
     * friction — see driver-application/create.blade.php), but these
     * columns were still NOT NULL with no default, so submissions would
     * fail outright. The site isn't live yet, so no real row was ever
     * blocked by this — caught during the simplification pass.
     */
    public function up(): void
    {
        Schema::table('driver_applications', function (Blueprint $table) {
            $table->string('national_id_number', 60)->nullable()->change();
            $table->string('driving_license_number', 60)->nullable()->change();
            $table->string('vehicle_type', 40)->nullable()->change();
            $table->string('vehicle_brand', 60)->nullable()->change();
            $table->string('vehicle_model', 60)->nullable()->change();
            $table->unsignedSmallInteger('vehicle_year')->nullable()->change();
            $table->string('national_id_front_path')->nullable()->change();
            $table->string('driving_license_path')->nullable()->change();
            $table->string('vehicle_registration_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('driver_applications', function (Blueprint $table) {
            $table->string('national_id_number', 60)->nullable(false)->change();
            $table->string('driving_license_number', 60)->nullable(false)->change();
            $table->string('vehicle_type', 40)->nullable(false)->change();
            $table->string('vehicle_brand', 60)->nullable(false)->change();
            $table->string('vehicle_model', 60)->nullable(false)->change();
            $table->unsignedSmallInteger('vehicle_year')->nullable(false)->change();
            $table->string('national_id_front_path')->nullable(false)->change();
            $table->string('driving_license_path')->nullable(false)->change();
            $table->string('vehicle_registration_path')->nullable(false)->change();
        });
    }
};

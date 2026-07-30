<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_applications', function (Blueprint $table) {
            // The DB's default engine is MyISAM, which silently ignores FK
            // constraints — InnoDB is needed for driver_application_notes'
            // cascade-delete to actually work.
            $table->engine = 'InnoDB';
            $table->id();

            // Step 1 — personal information
            $table->string('full_name');
            $table->string('mobile_number', 30);
            $table->string('whatsapp_number', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('city', 60);
            $table->enum('service_type', ['taxi', 'delivery']);

            // Step 2 — driver information
            $table->string('national_id_number', 60);
            $table->string('driving_license_number', 60);
            $table->string('vehicle_type', 40);
            $table->string('vehicle_brand', 60);
            $table->string('vehicle_model', 60);
            $table->unsignedSmallInteger('vehicle_year');
            $table->string('plate_number', 30);

            // Step 3 — documents (paths on the "public" disk)
            $table->string('national_id_front_path');
            $table->string('driving_license_path');
            $table->string('vehicle_registration_path');
            $table->string('personal_photo_path')->nullable();
            $table->string('vehicle_photo_path')->nullable();

            // Agreements
            $table->boolean('confirmed_information_correct')->default(false);
            $table->boolean('agreed_terms')->default(false);

            // Admin review workflow
            $table->enum('status', ['pending', 'under_review', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('service_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_applications');
    }
};

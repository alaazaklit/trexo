<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->json('pickup');
            $table->json('destination');
            $table->json('route_points')->nullable();
            $table->decimal('route_distance_km', 10, 3)->nullable();
            $table->dateTime('start_date_time');
            $table->dateTime('end_date_time');
            $table->string('status', 40)->default('pending');
            $table->string('tracking_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};

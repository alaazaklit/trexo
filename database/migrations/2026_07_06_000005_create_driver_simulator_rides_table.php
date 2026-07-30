<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_simulator_rides', function (Blueprint $table) {
            $table->id();
            $table->string('passenger_name')->nullable();
            $table->string('passenger_phone')->nullable();
            $table->decimal('pickup_latitude', 10, 7);
            $table->decimal('pickup_longitude', 10, 7);
            $table->decimal('dropoff_latitude', 10, 7);
            $table->decimal('dropoff_longitude', 10, 7);
            $table->string('pickup_label')->nullable();
            $table->string('dropoff_label')->nullable();
            $table->string('status', 40)->default('pending');
            $table->string('auto_response_mode', 20)->default('manual');
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->json('matched_driver_ids')->nullable();
            $table->json('route_points')->nullable();
            $table->unsignedInteger('route_cursor')->default(0);
            $table->integer('response_time_seconds')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_simulator_rides');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_service_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('service_type', 20); // taxi | delivery | bus
            $table->string('line_type', 20); // work_area | schedule
            $table->string('client_line_id')->nullable();
            $table->string('from_label')->nullable();
            $table->string('to_label')->nullable();
            $table->json('discount_rules')->nullable();
            $table->string('schedule_mode', 20)->nullable(); // weekly | specific_dates
            $table->json('weekly_start_times')->nullable();
            $table->json('weekly_end_times')->nullable();
            $table->json('specific_dates')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'line_type', 'service_type']);
            $table->index(['user_id', 'client_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_service_lines');
    }
};

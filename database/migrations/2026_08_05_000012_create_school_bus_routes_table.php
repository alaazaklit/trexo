<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One row = one (driver, school, pickup area, price) combination — the
// "Route" from the spec. A driver is considered to "serve" a school iff
// they have at least one active row here for it; there's no separate
// driver<->school pivot table.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_bus_routes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->string('pickup_area');
            $table->decimal('monthly_price', 8, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'is_active']);
            $table->index(['driver_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_bus_routes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_categories', function (Blueprint $table) {
            // The DB's default engine is MyISAM, which doesn't support real
            // foreign keys — drivers.vehicle_category_id (InnoDB) needs this
            // parent table to also be InnoDB or the FK constraint fails.
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price_multiplier', 5, 2)->default(1.00);
            $table->unsignedTinyInteger('capacity')->default(4);
            $table->string('icon')->nullable();
            $table->boolean('supports_taxi')->default(true);
            $table->boolean('supports_delivery')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_categories');
    }
};

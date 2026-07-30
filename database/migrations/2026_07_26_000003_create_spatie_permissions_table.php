<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spatie_permissions', function (Blueprint $table) {
            // The DB's default engine is MyISAM (1000-byte key limit), too
            // small for a composite unique index on two utf8mb4 varchars.
            // InnoDB supports the larger key and is the correct engine here.
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spatie_permissions');
    }
};

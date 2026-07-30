<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE drivers MODIFY license_number VARCHAR(191) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE drivers MODIFY license_number VARCHAR(191) NOT NULL');
    }
};

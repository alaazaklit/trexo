<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_applications', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->after('agreed_terms')
                ->constrained('drivers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('driver_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('driver_id');
        });
    }
};

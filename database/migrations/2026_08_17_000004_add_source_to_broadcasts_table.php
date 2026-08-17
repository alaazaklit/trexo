<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Every broadcast sent before this migration was filtration-based (Excel
// upload didn't exist yet) — defaulting `source` to 'filtration' means those
// existing rows keep reading correctly with no backfill needed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->string('source')->default('filtration')->after('service_type');
            $table->string('source_file_name')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_file_name']);
        });
    }
};

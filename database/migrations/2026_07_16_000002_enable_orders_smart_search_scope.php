<?php

use Illuminate\Database\Migrations\Migration;
use TCG\Voyager\Models\DataType;

// Wires Order::scopeSmartSearch() into the admin's /admin/orders browse
// page via Voyager's per-BREAD-resource `scope` setting — this only
// affects that one admin page's query, not every Order query app-wide.
return new class extends Migration
{
    public function up(): void
    {
        $dataType = DataType::where('slug', 'orders')->first();
        if ($dataType && empty($dataType->scope)) {
            $dataType->scope = 'smartSearch';
            $dataType->save();
        }
    }

    public function down(): void
    {
        $dataType = DataType::where('slug', 'orders')->first();
        if ($dataType && $dataType->scope === 'smartSearch') {
            $dataType->scope = null;
            $dataType->save();
        }
    }
};

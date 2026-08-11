<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// `payout_requests` modeled "Trexo pays the driver out of a held balance" —
// wrong for this cash-to-driver app (see the wallet-rename migration this
// one follows). Renamed to `commission_payments`: a driver reports having
// paid Trexo their owed commission via Wish Money, with a receipt as proof
// (which the old table never needed, since it wasn't proving anything).
// Any existing rows predate this model and don't carry a receipt, so they're
// dropped outright rather than migrated — there's no real money movement to
// preserve since the feature had no live usage under the old semantics.
return new class extends Migration
{
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::table('payout_requests')->truncate();

        Schema::rename('payout_requests', 'commission_payments');

        Schema::table('commission_payments', function (Blueprint $table) {
            $table->string('receipt_path')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('commission_payments', function (Blueprint $table) {
            $table->dropColumn('receipt_path');
        });

        Schema::rename('commission_payments', 'payout_requests');
    }
};

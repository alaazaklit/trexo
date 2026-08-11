<?php

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Corrects a design mistake from the initial build: this app is cash-to-driver
// (the customer pays the driver directly, Trexo has no payment gateway), so
// `wallets.balance` was backwards — it was crediting `driver_earnings` as if
// Trexo owed the driver money, when in fact the driver already holds that
// cash and instead OWES Trexo the commission. Renaming the column to make
// that direction impossible to misread, and recomputing every existing
// driver's value from the (still-correct) `transactions` ledger — the
// per-trip commission/earnings split itself was never wrong, only which
// side of it got credited to the wallet.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->renameColumn('balance', 'commission_owed');
        });

        Wallet::with('driver')->get()->each(function (Wallet $wallet) {
            $owed = Transaction::where('driver_id', $wallet->driver_id)->sum('commission_amount');
            $wallet->update(['commission_owed' => $owed]);
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->renameColumn('commission_owed', 'balance');
        });
    }
};

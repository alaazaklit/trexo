<?php

use Illuminate\Database\Migrations\Migration;
use TCG\Voyager\Models\Setting;

return new class extends Migration
{
    public function up(): void
    {
        $setting = Setting::firstOrNew(['key' => 'wallet.debt_limit']);
        if (!$setting->exists) {
            $setting->fill([
                'display_name' => 'Driver Commission Debt Limit',
                'value' => '50.00',
                'details' => 'Drivers stop receiving new orders/reservations once their owed commission exceeds this amount.',
                'type' => 'text',
                'order' => 1,
                'group' => 'Wallet',
            ])->save();
        }
    }

    public function down(): void
    {
        Setting::where('key', 'wallet.debt_limit')->delete();
    }
};

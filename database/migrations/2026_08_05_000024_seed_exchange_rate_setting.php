<?php

use Illuminate\Database\Migrations\Migration;
use TCG\Voyager\Models\Setting;

// LBP/USD isn't pegged and the business sets its own working rate rather
// than tracking live forex — an admin-editable setting (not a hardcoded
// constant) so it can be updated without shipping a new app build. Type
// must be 'text', not 'number' — see 2026_08_05_000020_fix_wallet_debt_
// limit_setting_type.php for why Voyager's settings page needs that.
return new class extends Migration
{
    public function up(): void
    {
        $setting = Setting::firstOrNew(['key' => 'pricing.exchange_rate_lbp_usd']);
        if (!$setting->exists) {
            $setting->fill([
                'display_name' => 'Exchange Rate (LBP per 1 USD)',
                'value' => '89500',
                'details' => 'Used to show prices in Lebanese Lira alongside USD throughout the app.',
                'type' => 'text',
                'order' => 1,
                'group' => 'Pricing',
            ])->save();
        }
    }

    public function down(): void
    {
        Setting::where('key', 'pricing.exchange_rate_lbp_usd')->delete();
    }
};

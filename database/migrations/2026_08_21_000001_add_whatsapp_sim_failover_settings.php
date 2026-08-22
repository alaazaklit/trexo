<?php

use Illuminate\Database\Migrations\Migration;
use TCG\Voyager\Models\Setting;

// Lets an admin swap the WhatsApp-sending number (the UnlimitedMessaging
// 'simId' — see UnlimitedMessagingService) from /admin/settings if it gets
// blocked/banned, without a deploy — same "admin-editable Setting instead
// of a hardcoded .env value" pattern as maps.directions_provider. A second
// 'backup' sim is seeded alongside it: UnlimitedMessagingService now
// automatically retries on the backup if the primary send fails, so a
// blocked number doesn't silently stop OTPs from arriving at all while
// someone notices and reacts — the admin Setting is then just for fixing
// it properly afterward (or swapping which one is primary), not the only
// thing standing between a block and a total outage.
//
// Both seeded empty: UNLIMITED_MESSAGING_SIM_ID in .env remains the
// fallback primary if this Setting is left blank, so existing behavior is
// unchanged until an admin actually sets one.
return new class extends Migration
{
    public function up(): void
    {
        $primary = Setting::firstOrNew(['key' => 'messaging.whatsapp_sim_id']);
        if (!$primary->exists) {
            $primary->fill([
                'display_name' => 'WhatsApp Sender (Primary sim_id)',
                'value' => '',
                'details' => 'The UnlimitedMessaging simId OTPs are sent from. Leave blank to use UNLIMITED_MESSAGING_SIM_ID from .env instead.',
                'type' => 'text',
                'order' => 1,
                'group' => 'Messaging',
            ])->save();
        }

        $backup = Setting::firstOrNew(['key' => 'messaging.whatsapp_backup_sim_id']);
        if (!$backup->exists) {
            $backup->fill([
                'display_name' => 'WhatsApp Sender (Backup sim_id)',
                'value' => '',
                'details' => 'Used automatically if a send on the primary sim fails (e.g. the primary number is blocked). Leave blank to disable failover.',
                'type' => 'text',
                'order' => 2,
                'group' => 'Messaging',
            ])->save();
        }
    }

    public function down(): void
    {
        Setting::where('key', 'messaging.whatsapp_sim_id')->delete();
        Setting::where('key', 'messaging.whatsapp_backup_sim_id')->delete();
    }
};

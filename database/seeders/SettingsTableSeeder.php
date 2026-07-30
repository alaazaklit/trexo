<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use TCG\Voyager\Models\Setting;

class SettingsTableSeeder extends Seeder
{
    /**
     * Auto generated seed file.
     */
    public function run()
    {
        $setting = $this->findSetting('site.title');
        if (!$setting->exists) {
            $setting->fill([
                'display_name' => __('voyager::seeders.settings.site.title'),
                'value'        => __('voyager::seeders.settings.site.title'),
                'details'      => '',
                'type'         => 'text',
                'order'        => 1,
                'group'        => 'Site',
            ])->save();
        }

        $setting = $this->findSetting('site.description');
        if (!$setting->exists) {
            $setting->fill([
                'display_name' => __('voyager::seeders.settings.site.description'),
                'value'        => __('voyager::seeders.settings.site.description'),
                'details'      => '',
                'type'         => 'text',
                'order'        => 2,
                'group'        => 'Site',
            ])->save();
        }

        $setting = $this->findSetting('site.logo');
        if (!$setting->exists) {
            $setting->fill([
                'display_name' => __('voyager::seeders.settings.site.logo'),
                'value'        => '',
                'details'      => '',
                'type'         => 'image',
                'order'        => 3,
                'group'        => 'Site',
            ])->save();
        }

        $setting = $this->findSetting('site.google_analytics_tracking_id');
        if (!$setting->exists) {
            $setting->fill([
                'display_name' => __('voyager::seeders.settings.site.google_analytics_tracking_id'),
                'value'        => '',
                'details'      => '',
                'type'         => 'text',
                'order'        => 4,
                'group'        => 'Site',
            ])->save();
        }

        $setting = $this->findSetting('admin.bg_image');
        if (!$setting->exists) {
            $setting->fill([
                'display_name' => __('voyager::seeders.settings.admin.background_image'),
                'value'        => '',
                'details'      => '',
                'type'         => 'image',
                'order'        => 5,
                'group'        => 'Admin',
            ])->save();
        }

        $setting = $this->findSetting('admin.title');
        if (!$setting->exists) {
            $setting->fill([
                'display_name' => __('voyager::seeders.settings.admin.title'),
                'value'        => 'Voyager',
                'details'      => '',
                'type'         => 'text',
                'order'        => 1,
                'group'        => 'Admin',
            ])->save();
        }

        $setting = $this->findSetting('admin.description');
        if (!$setting->exists) {
            $setting->fill([
                'display_name' => __('voyager::seeders.settings.admin.description'),
                'value'        => __('voyager::seeders.settings.admin.description_value'),
                'details'      => '',
                'type'         => 'text',
                'order'        => 2,
                'group'        => 'Admin',
            ])->save();
        }

        $setting = $this->findSetting('admin.loader');
        if (!$setting->exists) {
            $setting->fill([
                'display_name' => __('voyager::seeders.settings.admin.loader'),
                'value'        => '',
                'details'      => '',
                'type'         => 'image',
                'order'        => 3,
                'group'        => 'Admin',
            ])->save();
        }

        $setting = $this->findSetting('admin.icon_image');
        if (!$setting->exists) {
            $setting->fill([
                'display_name' => __('voyager::seeders.settings.admin.icon_image'),
                'value'        => '',
                'details'      => '',
                'type'         => 'image',
                'order'        => 4,
                'group'        => 'Admin',
            ])->save();
        }

        $setting = $this->findSetting('admin.google_analytics_client_id');
        if (!$setting->exists) {
            $setting->fill([
                'display_name' => __('voyager::seeders.settings.admin.google_analytics_client_id'),
                'value'        => '',
                'details'      => '',
                'type'         => 'text',
                'order'        => 1,
                'group'        => 'Admin',
            ])->save();
        }

        $pricingSettings = [
            [
                'key' => 'fare.base_taxi',
                'display_name' => 'Base Taxi Fare',
                'value' => '2.50',
                'type' => 'number',
                'order' => 1,
                'group' => 'Fare',
                'details' => '',
            ],
            [
                'key' => 'fare.base_delivery',
                'display_name' => 'Base Delivery Fare',
                'value' => '3.00',
                'type' => 'number',
                'order' => 2,
                'group' => 'Fare',
                'details' => '',
            ],
            [
                'key' => 'fare.per_km_taxi',
                'display_name' => 'Taxi Price Per Km',
                'value' => '1.20',
                'type' => 'number',
                'order' => 3,
                'group' => 'Fare',
                'details' => '',
            ],
            [
                'key' => 'fare.per_km_delivery',
                'display_name' => 'Delivery Price Per Km',
                'value' => '1.00',
                'type' => 'number',
                'order' => 4,
                'group' => 'Fare',
                'details' => '',
            ],
            [
                'key' => 'fare.shared_multiplier',
                'display_name' => 'Shared Ride Multiplier',
                'value' => '0.70',
                'type' => 'number',
                'order' => 5,
                'group' => 'Fare',
                'details' => '',
            ],
            [
                'key' => 'fare.route_deviation_km',
                'display_name' => 'Route Deviation Threshold Km',
                'value' => '0.75',
                'type' => 'number',
                'order' => 6,
                'group' => 'Fare',
                'details' => '',
            ],
        ];

        foreach ($pricingSettings as $pricingSetting) {
            $setting = $this->findSetting($pricingSetting['key']);
            if (!$setting->exists) {
                $setting->fill([
                    'display_name' => $pricingSetting['display_name'],
                    'value' => $pricingSetting['value'],
                    'details' => $pricingSetting['details'],
                    'type' => $pricingSetting['type'],
                    'order' => $pricingSetting['order'],
                    'group' => $pricingSetting['group'],
                ])->save();
            }
        }
    }

    /**
     * [setting description].
     *
     * @param [type] $key [description]
     *
     * @return [type] [description]
     */
    protected function findSetting($key)
    {
        return Setting::firstOrNew(['key' => $key]);
    }
}

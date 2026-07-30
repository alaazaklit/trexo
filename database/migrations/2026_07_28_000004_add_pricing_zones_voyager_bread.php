<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Registers a standard Voyager BREAD for pricing_zones so an admin can
// manage region-based fares from the admin panel, the same way global
// fare.* settings are already managed there. Mirrors the exact same
// data_types/data_rows/permissions/menu_items pattern used for
// driver_gallery_images.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('data_types')->where('name', 'pricing_zones')->exists()) {
            return;
        }

        $dataTypeId = DB::table('data_types')->insertGetId([
            'name' => 'pricing_zones',
            'slug' => 'pricing-zones',
            'display_name_singular' => 'Pricing Zone',
            'display_name_plural' => 'Pricing Zones',
            'icon' => 'voyager-map',
            'model_name' => 'App\\Models\\PricingZone',
            'policy_name' => null,
            'controller' => null,
            'description' => null,
            'generate_permissions' => 1,
            'server_side' => 0,
            'details' => json_encode([
                'order_column' => 'priority',
                'order_display_column' => null,
                'order_direction' => 'desc',
                'default_search_key' => null,
                'scope' => null,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = [
            ['field' => 'id', 'type' => 'text', 'display_name' => 'Id', 'required' => 1, 'browse' => 0, 'read' => 0, 'edit' => 0, 'add' => 0, 'delete' => 0],
            ['field' => 'name', 'type' => 'text', 'display_name' => 'Zone Name', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'keywords', 'type' => 'text', 'display_name' => 'Match Keywords (comma-separated city/region names)', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'base_fare_taxi', 'type' => 'text', 'display_name' => 'Base Fare (Taxi)', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'base_fare_delivery', 'type' => 'text', 'display_name' => 'Base Fare (Delivery)', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'per_km_taxi', 'type' => 'text', 'display_name' => 'Price per Km (Taxi)', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'per_km_delivery', 'type' => 'text', 'display_name' => 'Price per Km (Delivery)', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'priority', 'type' => 'text', 'display_name' => 'Priority (higher wins when multiple zones match)', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'is_active', 'type' => 'checkbox', 'display_name' => 'Active', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'created_at', 'type' => 'timestamp', 'display_name' => 'Created At', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 0, 'add' => 0, 'delete' => 1],
            ['field' => 'updated_at', 'type' => 'timestamp', 'display_name' => 'Updated At', 'required' => 0, 'browse' => 0, 'read' => 0, 'edit' => 0, 'add' => 0, 'delete' => 0],
        ];

        foreach ($rows as $index => $row) {
            DB::table('data_rows')->insert(array_merge($row, [
                'data_type_id' => $dataTypeId,
                'details' => '{}',
                'order' => $index + 1,
                'created_at' => null,
                'updated_at' => null,
            ]));
        }

        $permissionKeys = ['browse', 'read', 'edit', 'add', 'delete'];
        $permissionIds = [];
        foreach ($permissionKeys as $action) {
            $permissionIds[] = DB::table('permissions')->insertGetId([
                'key' => $action . '_pricing_zones',
                'table_name' => 'pricing_zones',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insert([
                'permission_id' => $permissionId,
                'role_id' => 1,
            ]);
        }

        $menuOrder = (int) DB::table('menu_items')->where('menu_id', 1)->max('order') + 1;
        DB::table('menu_items')->insert([
            'menu_id' => 1,
            'title' => 'Pricing Zones',
            'url' => '',
            'target' => '_self',
            'icon_class' => 'voyager-map',
            'color' => null,
            'parent_id' => null,
            'order' => $menuOrder,
            'route' => 'voyager.pricing-zones.index',
            'parameters' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $dataType = DB::table('data_types')->where('name', 'pricing_zones')->first();
        if (!$dataType) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->where('table_name', 'pricing_zones')
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        DB::table('menu_items')->where('route', 'voyager.pricing-zones.index')->delete();
        DB::table('data_rows')->where('data_type_id', $dataType->id)->delete();
        DB::table('data_types')->where('id', $dataType->id)->delete();
    }
};

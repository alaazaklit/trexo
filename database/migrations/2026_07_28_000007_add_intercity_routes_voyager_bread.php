<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Registers a standard Voyager BREAD for intercity_routes so an admin can
// manage fixed long-distance fares from the admin panel, the same way
// pricing_zones already are. Mirrors the same data_types/data_rows/
// permissions/menu_items pattern.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('data_types')->where('name', 'intercity_routes')->exists()) {
            return;
        }

        $dataTypeId = DB::table('data_types')->insertGetId([
            'name' => 'intercity_routes',
            'slug' => 'intercity-routes',
            'display_name_singular' => 'Intercity Route',
            'display_name_plural' => 'Intercity Routes',
            'icon' => 'voyager-directions',
            'model_name' => 'App\\Models\\IntercityRoute',
            'policy_name' => null,
            'controller' => null,
            'description' => null,
            'generate_permissions' => 1,
            'server_side' => 0,
            'details' => json_encode([
                'order_column' => null,
                'order_display_column' => null,
                'order_direction' => 'asc',
                'default_search_key' => null,
                'scope' => null,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = [
            ['field' => 'id', 'type' => 'text', 'display_name' => 'Id', 'required' => 1, 'browse' => 0, 'read' => 0, 'edit' => 0, 'add' => 0, 'delete' => 0],
            ['field' => 'from_zone_id', 'type' => 'relationship', 'display_name' => 'From Zone', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'to_zone_id', 'type' => 'relationship', 'display_name' => 'To Zone', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'fixed_fare_taxi', 'type' => 'text', 'display_name' => 'Fixed Fare (Taxi)', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'fixed_fare_delivery', 'type' => 'text', 'display_name' => 'Fixed Fare (Delivery)', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'is_active', 'type' => 'checkbox', 'display_name' => 'Active', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'created_at', 'type' => 'timestamp', 'display_name' => 'Created At', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 0, 'add' => 0, 'delete' => 1],
            ['field' => 'updated_at', 'type' => 'timestamp', 'display_name' => 'Updated At', 'required' => 0, 'browse' => 0, 'read' => 0, 'edit' => 0, 'add' => 0, 'delete' => 0],
        ];

        foreach ($rows as $index => $row) {
            $details = '{}';
            if (in_array($row['field'], ['from_zone_id', 'to_zone_id'], true)) {
                $details = json_encode([
                    'model' => 'App\\Models\\PricingZone',
                    'table' => 'pricing_zones',
                    'type' => 'belongsTo',
                    'column' => $row['field'],
                    'key' => 'id',
                    'label' => 'name',
                    'pivot_table' => null,
                    'pivot' => false,
                ]);
            }

            DB::table('data_rows')->insert(array_merge($row, [
                'data_type_id' => $dataTypeId,
                'details' => $details,
                'order' => $index + 1,
                'created_at' => null,
                'updated_at' => null,
            ]));
        }

        $permissionKeys = ['browse', 'read', 'edit', 'add', 'delete'];
        $permissionIds = [];
        foreach ($permissionKeys as $action) {
            $permissionIds[] = DB::table('permissions')->insertGetId([
                'key' => $action . '_intercity_routes',
                'table_name' => 'intercity_routes',
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
            'title' => 'Intercity Routes',
            'url' => '',
            'target' => '_self',
            'icon_class' => 'voyager-directions',
            'color' => null,
            'parent_id' => null,
            'order' => $menuOrder,
            'route' => 'voyager.intercity-routes.index',
            'parameters' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $dataType = DB::table('data_types')->where('name', 'intercity_routes')->first();
        if (!$dataType) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->where('table_name', 'intercity_routes')
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        DB::table('menu_items')->where('route', 'voyager.intercity-routes.index')->delete();
        DB::table('data_rows')->where('data_type_id', $dataType->id)->delete();
        DB::table('data_types')->where('id', $dataType->id)->delete();
    }
};

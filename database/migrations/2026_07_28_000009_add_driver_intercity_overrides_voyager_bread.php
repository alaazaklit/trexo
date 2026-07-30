<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Registers a standard Voyager BREAD for driver_intercity_route_overrides
// so an admin can review/edit/revoke what a driver has set as their own
// fare for a specific intercity route (drivers set these themselves from
// the mobile app — this is the admin-side visibility into that data).
// Mirrors the same data_types/data_rows/permissions/menu_items pattern
// used for pricing_zones/intercity_routes.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('data_types')->where('name', 'driver_intercity_route_overrides')->exists()) {
            return;
        }

        $dataTypeId = DB::table('data_types')->insertGetId([
            'name' => 'driver_intercity_route_overrides',
            'slug' => 'driver-intercity-route-overrides',
            'display_name_singular' => 'Driver Route Price',
            'display_name_plural' => 'Driver Route Prices',
            'icon' => 'voyager-price-tag',
            'model_name' => 'App\\Models\\DriverIntercityRouteOverride',
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
            ['field' => 'user_id', 'type' => 'relationship', 'display_name' => 'Driver', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'intercity_route_id', 'type' => 'relationship', 'display_name' => 'Route', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'fixed_fare_taxi_override', 'type' => 'text', 'display_name' => 'Driver Fare (Taxi)', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'fixed_fare_delivery_override', 'type' => 'text', 'display_name' => 'Driver Fare (Delivery)', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'created_at', 'type' => 'timestamp', 'display_name' => 'Created At', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 0, 'add' => 0, 'delete' => 1],
            ['field' => 'updated_at', 'type' => 'timestamp', 'display_name' => 'Updated At', 'required' => 0, 'browse' => 0, 'read' => 0, 'edit' => 0, 'add' => 0, 'delete' => 0],
        ];

        $relationshipDetails = [
            'user_id' => ['model' => 'App\\Models\\User', 'table' => 'users', 'column' => 'user_id', 'label' => 'name'],
            'intercity_route_id' => ['model' => 'App\\Models\\IntercityRoute', 'table' => 'intercity_routes', 'column' => 'intercity_route_id', 'label' => 'name'],
        ];

        foreach ($rows as $index => $row) {
            $details = '{}';
            if (isset($relationshipDetails[$row['field']])) {
                $rel = $relationshipDetails[$row['field']];
                $details = json_encode([
                    'model' => $rel['model'],
                    'table' => $rel['table'],
                    'type' => 'belongsTo',
                    'column' => $rel['column'],
                    'key' => 'id',
                    'label' => $rel['label'],
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
                'key' => $action . '_driver_intercity_route_overrides',
                'table_name' => 'driver_intercity_route_overrides',
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
            'title' => 'Driver Route Prices',
            'url' => '',
            'target' => '_self',
            'icon_class' => 'voyager-price-tag',
            'color' => null,
            'parent_id' => null,
            'order' => $menuOrder,
            'route' => 'voyager.driver-intercity-route-overrides.index',
            'parameters' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $dataType = DB::table('data_types')->where('name', 'driver_intercity_route_overrides')->first();
        if (!$dataType) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->where('table_name', 'driver_intercity_route_overrides')
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        DB::table('menu_items')->where('route', 'voyager.driver-intercity-route-overrides.index')->delete();
        DB::table('data_rows')->where('data_type_id', $dataType->id)->delete();
        DB::table('data_types')->where('id', $dataType->id)->delete();
    }
};

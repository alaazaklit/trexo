<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Registers a standard Voyager BREAD (admin list/view/edit/delete UI) for
// driver_gallery_images — there was previously no way to review or remove
// a driver's uploaded gallery photos from the admin panel at all. Mirrors
// the exact same data_types/data_rows/permissions/menu_items pattern
// already used for the `notifications` table, since Voyager's BREAD
// config lives in the database rather than in code.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('data_types')->where('name', 'driver_gallery_images')->exists()) {
            return;
        }

        $dataTypeId = DB::table('data_types')->insertGetId([
            'name' => 'driver_gallery_images',
            'slug' => 'driver-gallery-images',
            'display_name_singular' => 'Driver Gallery Photo',
            'display_name_plural' => 'Driver Gallery Photos',
            'icon' => 'voyager-images',
            'model_name' => 'App\\Models\\DriverGalleryImage',
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
            ['field' => 'user_id', 'type' => 'text', 'display_name' => 'Driver (User Id)', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'category', 'type' => 'text', 'display_name' => 'Category', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'path', 'type' => 'image', 'display_name' => 'Photo', 'required' => 1, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'sort_order', 'type' => 'text', 'display_name' => 'Sort Order', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'delete' => 1],
            ['field' => 'created_at', 'type' => 'timestamp', 'display_name' => 'Created At', 'required' => 0, 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 0, 'delete' => 1],
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
                'key' => $action . '_driver_gallery_images',
                'table_name' => 'driver_gallery_images',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Admin (role_id 1) gets full access, same as every other BREAD.
        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insert([
                'permission_id' => $permissionId,
                'role_id' => 1,
            ]);
        }

        $menuOrder = (int) DB::table('menu_items')->where('menu_id', 1)->max('order') + 1;
        DB::table('menu_items')->insert([
            'menu_id' => 1,
            'title' => 'Driver Gallery Photos',
            'url' => '',
            'target' => '_self',
            'icon_class' => 'voyager-images',
            'color' => null,
            'parent_id' => null,
            'order' => $menuOrder,
            'route' => 'voyager.driver-gallery-images.index',
            'parameters' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $dataType = DB::table('data_types')->where('name', 'driver_gallery_images')->first();
        if (!$dataType) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->where('table_name', 'driver_gallery_images')
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        DB::table('menu_items')->where('route', 'voyager.driver-gallery-images.index')->delete();
        DB::table('data_rows')->where('data_type_id', $dataType->id)->delete();
        DB::table('data_types')->where('id', $dataType->id)->delete();
    }
};

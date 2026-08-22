<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Starter permission set for the Foundations phase, plus placeholders
     * for modules planned in later phases.
     */
    private const PERMISSIONS = [
        'dashboard.view',
        'audit-logs.view',
        'orders.view',
        'orders.manage',
        'drivers.view',
        'drivers.manage',
        'customers.view',
        'customers.manage',
        'vehicles.manage',
        'finance.view',
        'users.manage',
        'driver-applications.view',
        'driver-applications.manage',
        'contact-messages.view',
        'contact-messages.manage',
        'subscriptions.view',
        'subscriptions.manage',
        'wallet.view',
        'wallet.manage',
        'school-bus.view',
        'school-bus.manage',
        'broadcasts.view',
        'broadcasts.manage',
        'pricing.view',
        'pricing.manage',
        'request-reports.view',
        'maps.view',
        'maps.manage',
    ];

    private const ROLE_PERMISSIONS = [
        'Operations' => [
            'dashboard.view', 'audit-logs.view',
            'orders.view', 'orders.manage',
            'drivers.view', 'drivers.manage',
            'customers.view', 'customers.manage',
            'vehicles.manage',
            'driver-applications.view', 'driver-applications.manage',
            'subscriptions.view', 'wallet.view',
            'school-bus.view', 'school-bus.manage',
            'pricing.view', 'pricing.manage',
            'request-reports.view',
        ],
        'Finance' => [
            'dashboard.view', 'finance.view',
            'subscriptions.view', 'subscriptions.manage', 'wallet.view', 'wallet.manage',
        ],
        'Support' => [
            'dashboard.view',
            'orders.view', 'orders.manage',
            'customers.view', 'customers.manage',
            'contact-messages.view', 'contact-messages.manage',
            'request-reports.view',
        ],
        'Marketing' => [
            'dashboard.view',
            'driver-applications.view', 'driver-applications.manage',
            'contact-messages.view', 'contact-messages.manage',
            'broadcasts.view', 'broadcasts.manage',
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // The shared `roles` table is Voyager's, and requires `display_name`
        // (NOT NULL, no default) — Spatie's Role model doesn't know about
        // that column on its own, so it must be set explicitly here.
        $superAdmin = Role::firstOrCreate(
            ['name' => 'Super Admin', 'guard_name' => 'web'],
            ['display_name' => 'Super Admin']
        );
        $superAdmin->syncPermissions(Permission::all());

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['display_name' => $roleName]
            );
            $role->syncPermissions($permissions);
        }

        // Bootstrap existing Voyager admins into Super Admin so nobody is locked out.
        User::where('role_id', 1)->get()->each(function (User $user) {
            $user->assignRole('Super Admin');
        });
    }
}

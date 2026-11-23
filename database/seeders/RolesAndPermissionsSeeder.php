<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /** @var list<string> */
    public const PERMISSIONS = [
        'products.view', 'products.create', 'products.update', 'products.delete',
        'categories.view', 'categories.create', 'categories.update', 'categories.delete',
        'brands.view', 'brands.create', 'brands.update', 'brands.delete',
        'inventory.view', 'inventory.adjust',
        'orders.view', 'orders.update-status', 'orders.refund',
        'coupons.view', 'coupons.create', 'coupons.update', 'coupons.delete',
        'shipping.view', 'shipping.update',
        'users.view', 'users.update', 'users.block',
        'reports.view',
        'activity-log.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // super-admin: every permission, granted through Gate::before in AppServiceProvider.
        Role::findOrCreate('super-admin', 'web');

        Role::findOrCreate('admin', 'web')->syncPermissions(
            array_values(array_diff(self::PERMISSIONS, ['orders.refund', 'users.block', 'activity-log.view'])),
        );

        Role::findOrCreate('customer', 'web');
    }
}

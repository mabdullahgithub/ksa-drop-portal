<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Dashboard
            'view dashboard',
            'create dashboard',
            'edit dashboard',
            'delete dashboard',

            // Client
            'view client',
            'create client',
            'edit client',
            'delete client',
            'impersonate client',

            // Inventory
            'view inventory',
            'create inventory',
            'edit inventory',
            'delete inventory',

            // Orders
            'view orders',
            'create orders',
            'edit orders',
            'delete orders',

            // Apps
            'view apps',
            'create apps',
            'edit apps',
            'delete apps',

            // Tags
            'view tags',
            'create tags',
            'edit tags',
            'delete tags',

            // Users
            'view users',
            'create users',
            'edit users',
            'delete users',

            // Roles
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',

            // Permissions
            'view permissions',
            'create permissions',
            'edit permissions',
            'delete permissions',

            // Teams
            'view teams',
            'create teams',
            'edit teams',
            'delete teams',

            // Notifications
            'view notifications',
            'delete notifications',

            // Settings
            'view settings',
            'edit settings',
            'manage-email-settings',
        ];

        // Create permissions if they don't already exist
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Superadmin — sync all permissions (runs after all are created above)
        $superadmin = Role::firstOrCreate(['name' => 'superadmin']);
        $superadmin->syncPermissions(Permission::all());

        // Admin — most permissions except critical role/permission management
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions([
            'view dashboard',
            'view client', 'create client', 'edit client', 'delete client', 'impersonate client',
            'view inventory', 'create inventory', 'edit inventory', 'delete inventory',
            'view orders', 'create orders', 'edit orders', 'delete orders',
            'view apps', 'create apps', 'edit apps', 'delete apps',
            'view tags', 'create tags', 'edit tags', 'delete tags',
            'view users', 'create users', 'edit users',
            'view roles',
            'view permissions',
            'view teams', 'create teams', 'edit teams',
            'view notifications', 'delete notifications',
            'view settings', 'edit settings',
            'manage-email-settings',
        ]);

        // Manager — moderate permissions
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->syncPermissions([
            'view dashboard',
            'view client', 'create client', 'edit client',
            'view inventory', 'create inventory', 'edit inventory',
            'view orders', 'create orders', 'edit orders',
            'view apps',
            'view tags', 'create tags', 'edit tags',
            'view users',
            'view teams',
            'view notifications',
            'view settings',
        ]);

        // User — basic permissions
        $user = Role::firstOrCreate(['name' => 'user']);
        $user->syncPermissions([
            'view dashboard',
            'view client',
            'view inventory',
            'view orders',
            'view tags',
            'view notifications',
            'view settings',
        ]);

        $this->command->info('Permissions and roles seeded successfully!');
    }
}

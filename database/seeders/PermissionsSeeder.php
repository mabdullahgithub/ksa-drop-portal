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
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions
        $permissions = [
            // Dashboard
            'view dashboard',

            // Client
            'view client',
            'create client',
            'edit client',
            'delete client',

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

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Superadmin - all permissions
        $superadmin = Role::firstOrCreate(['name' => 'superadmin']);
        $superadmin->givePermissionTo(Permission::all());

        // Admin - most permissions except critical ones
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo([
            'view dashboard',
            'view client',
            'create client',
            'edit client',
            'delete client',
            'view inventory',
            'create inventory',
            'edit inventory',
            'delete inventory',
            'view orders',
            'create orders',
            'edit orders',
            'delete orders',
            'view apps',
            'create apps',
            'edit apps',
            'delete apps',
            'view users',
            'create users',
            'edit users',
            'view roles',
            'view permissions',
            'view teams',
            'create teams',
            'edit teams',
            'view notifications',
            'view settings',
            'edit settings',
            'manage-email-settings',
        ]);

        // Manager - moderate permissions
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->givePermissionTo([
            'view dashboard',
            'view client',
            'create client',
            'edit client',
            'view inventory',
            'create inventory',
            'edit inventory',
            'view orders',
            'create orders',
            'edit orders',
            'view apps',
            'view users',
            'view teams',
            'view notifications',
            'view settings',
        ]);

        // User - basic permissions
        $user = Role::firstOrCreate(['name' => 'user']);
        $user->givePermissionTo([
            'view dashboard',
            'view client',
            'view inventory',
            'view orders',
            'view notifications',
            'view settings',
        ]);

        $this->command->info('Permissions and roles seeded successfully!');
    }
}

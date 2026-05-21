<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Dashboard CRUD
            'view dashboard',
            'create dashboard',
            'edit dashboard',
            'delete dashboard',

            // Client CRUD
            'view client',
            'create client',
            'edit client',
            'delete client',

            // Inventory CRUD
            'view inventory',
            'create inventory',
            'edit inventory',
            'delete inventory',

            // Orders CRUD
            'view orders',
            'create orders',
            'edit orders',
            'delete orders',

            // Apps CRUD
            'view apps',
            'create apps',
            'edit apps',
            'delete apps',

            // User Management CRUD
            'view users',
            'create users',
            'edit users',
            'delete users',

            // Role Management CRUD
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',

            // Permission Management
            'view permissions',
            'edit permissions',

            // Settings
            'view settings',
            'edit settings',

            // Email Settings
            'manage-email-settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Super Admin - All permissions
        $superAdmin = Role::firstOrCreate(['name' => 'superadmin']);
        $superAdmin->syncPermissions(Permission::all());

        // Admin - Full CRUD on users/roles, view permissions, full CRUD on content, manage settings
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions([
            // User & Role Management
            'view users', 'create users', 'edit users', 'delete users',
            'view roles', 'create roles', 'edit roles', 'delete roles',
            'view permissions',

            // Dashboard
            'view dashboard', 'create dashboard', 'edit dashboard', 'delete dashboard',

            // Client
            'view client', 'create client', 'edit client', 'delete client',

            // Inventory
            'view inventory', 'create inventory', 'edit inventory', 'delete inventory',

            // Orders
            'view orders', 'create orders', 'edit orders', 'delete orders',

            // Apps
            'view apps', 'create apps', 'edit apps', 'delete apps',

            // Settings
            'view settings', 'edit settings',

            // Email Settings
            'manage-email-settings',
        ]);

        // Manager - View-only on user management, full CRUD on content, view settings
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->syncPermissions([
            // User & Role Management (view only)
            'view users',
            'view roles',
            'view permissions',

            // Dashboard
            'view dashboard', 'create dashboard', 'edit dashboard', 'delete dashboard',

            // Client
            'view client', 'create client', 'edit client', 'delete client',

            // Inventory
            'view inventory', 'create inventory', 'edit inventory', 'delete inventory',

            // Orders
            'view orders', 'create orders', 'edit orders', 'delete orders',

            // Apps
            'view apps', 'create apps', 'edit apps', 'delete apps',

            // Settings (view only)
            'view settings',
        ]);

        // Client - Portal access only, section access controlled via portal_features column
        $clientRole = Role::firstOrCreate(['name' => 'client']);
        $clientRole->syncPermissions([]);

        // Create admin user
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@ksadrop.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Assign superadmin role to admin user
        $adminUser->assignRole('superadmin');
    }
}

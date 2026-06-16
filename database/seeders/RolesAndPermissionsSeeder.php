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
            'impersonate client',

            // Inventory CRUD (global product catalog)
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

            // Tags CRUD
            'view tags',
            'create tags',
            'edit tags',
            'delete tags',

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
            'create permissions',
            'edit permissions',
            'delete permissions',

            // Team Management
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

            // Email Settings
            'manage-email-settings',
        ];

        // Create permissions if they don't already exist
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Super Admin — sync all permissions (runs after all are created above)
        $superAdmin = Role::firstOrCreate(['name' => 'superadmin']);
        $superAdmin->syncPermissions(Permission::all());

        // Admin — full CRUD on all content, manage users/roles, view permissions, manage settings
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $adminPermissions = [
            // Dashboard
            'view dashboard', 'create dashboard', 'edit dashboard', 'delete dashboard',

            // Client
            'view client', 'create client', 'edit client', 'delete client', 'impersonate client',

            // Inventory
            'view inventory', 'create inventory', 'edit inventory', 'delete inventory',

            // Orders
            'view orders', 'create orders', 'edit orders', 'delete orders',

            // Apps
            'view apps', 'create apps', 'edit apps', 'delete apps',

            // Tags
            'view tags', 'create tags', 'edit tags', 'delete tags',

            // User & Role Management
            'view users', 'create users', 'edit users', 'delete users',
            'view roles', 'create roles', 'edit roles', 'delete roles',
            'view permissions',

            // Teams
            'view teams', 'create teams', 'edit teams', 'delete teams',

            // Notifications
            'view notifications', 'delete notifications',

            // Settings
            'view settings', 'edit settings',
            'manage-email-settings',
        ];
        $admin->syncPermissions($adminPermissions);

        // Manager — view-only on user management, full CRUD on content, view settings
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $managerPermissions = [
            // Dashboard
            'view dashboard',

            // Client
            'view client', 'create client', 'edit client', 'delete client', 'impersonate client',

            // Inventory
            'view inventory', 'create inventory', 'edit inventory', 'delete inventory',

            // Orders
            'view orders', 'create orders', 'edit orders', 'delete orders',

            // Apps
            'view apps', 'create apps', 'edit apps', 'delete apps',

            // Tags
            'view tags', 'create tags', 'edit tags',

            // User & Role Management (view only)
            'view users',
            'view roles',
            'view permissions',

            // Teams
            'view teams', 'create teams', 'edit teams',

            // Notifications
            'view notifications',

            // Settings (view only)
            'view settings',
        ];
        $manager->syncPermissions($managerPermissions);

        // Client — portal access only, section access controlled via portal_features column
        $clientRole = Role::firstOrCreate(['name' => 'client']);
        $clientRole->syncPermissions([]);

        // Create admin user if not exists
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@ksadrop.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if (! $adminUser->hasRole('superadmin')) {
            $adminUser->assignRole('superadmin');
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\PermissionCategory;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'dashboard',
                'label' => 'Dashboard',
                'description' => 'Dashboard access and management',
                'order' => 1,
            ],
            [
                'name' => 'client',
                'label' => 'Clients',
                'description' => 'Client management permissions',
                'order' => 2,
            ],
            [
                'name' => 'inventory',
                'label' => 'Inventory',
                'description' => 'Inventory management permissions',
                'order' => 3,
            ],
            [
                'name' => 'orders',
                'label' => 'Orders',
                'description' => 'Order management permissions',
                'order' => 4,
            ],
            [
                'name' => 'apps',
                'label' => 'Apps',
                'description' => 'Application management permissions',
                'order' => 5,
            ],
            [
                'name' => 'tags',
                'label' => 'Tags',
                'description' => 'Tag management permissions',
                'order' => 6,
            ],
            [
                'name' => 'users',
                'label' => 'User Management',
                'description' => 'User management permissions',
                'order' => 7,
            ],
            [
                'name' => 'roles',
                'label' => 'Role Management',
                'description' => 'Role management permissions',
                'order' => 8,
            ],
            [
                'name' => 'permissions',
                'label' => 'Permission Management',
                'description' => 'Permission management',
                'order' => 9,
            ],
            [
                'name' => 'teams',
                'label' => 'Team Management',
                'description' => 'Team management permissions',
                'order' => 10,
            ],
            [
                'name' => 'notifications',
                'label' => 'Notifications',
                'description' => 'Notification management permissions',
                'order' => 11,
            ],
            [
                'name' => 'settings',
                'label' => 'Settings',
                'description' => 'Settings and configuration permissions',
                'order' => 12,
            ],
        ];

        foreach ($categories as $categoryData) {
            $category = PermissionCategory::firstOrCreate(
                ['name' => $categoryData['name']],
                $categoryData
            );

            // Assign permissions to categories
            $permissions = Permission::where('name', 'like', '%' . $categoryData['name'] . '%')->get();
            foreach ($permissions as $permission) {
                $permission->update(['category_id' => $category->id]);
            }

            // Manual assignments for specific permissions
            if ($categoryData['name'] === 'settings') {
                Permission::where('name', 'manage-email-settings')->update(['category_id' => $category->id]);
            }
        }

        $this->command->info('Permission categories seeded successfully!');
    }
}

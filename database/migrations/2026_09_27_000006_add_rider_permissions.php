<?php

use App\Models\PermissionCategory;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private const PERMISSIONS = ['view riders', 'manage riders'];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $category = PermissionCategory::firstOrCreate(
            ['name' => 'riders'],
            ['label' => 'Riders', 'description' => 'KSA Express rider management', 'order' => 13],
        );

        foreach (self::PERMISSIONS as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $permission->forceFill(['category_id' => $category->id])->save();

            foreach (['superadmin', 'admin', 'manager'] as $roleName) {
                $role = Role::where('name', $roleName)->first();
                if ($role && ! $role->hasPermissionTo($name)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', self::PERMISSIONS)->delete();
        PermissionCategory::where('name', 'riders')->delete();
    }
};

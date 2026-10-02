<?php

use App\Models\PermissionCategory;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Recording the cash a rider hands in is its own permission: whoever
     * edits rider details isn't necessarily who takes the money.
     */
    private const PERMISSION = 'manage rider payments';

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $category = PermissionCategory::firstOrCreate(
            ['name' => 'riders'],
            ['label' => 'Riders', 'description' => 'KSA Express rider management', 'order' => 13],
        );

        $permission = Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);
        $permission->forceFill(['category_id' => $category->id])->save();

        foreach (['superadmin', 'admin', 'manager'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role && ! $role->hasPermissionTo(self::PERMISSION)) {
                $role->givePermissionTo($permission);
            }
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', self::PERMISSION)->delete();
    }
};

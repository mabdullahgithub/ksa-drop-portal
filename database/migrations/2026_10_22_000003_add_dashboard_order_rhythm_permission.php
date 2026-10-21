<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The dashboard's order rhythm, a radar chart of the hours and days orders
 * come in. A role that can see the dashboard's order numbers gets it too;
 * `superadmin` and `developer` get it through sync().
 *
 * It replaces a short-lived courier scorecard, removed here wherever an
 * earlier copy of this migration created it.
 */
return new class extends Migration
{
    private const PERMISSION = 'view dashboard order rhythm';

    private const REPLACED = 'view dashboard courier scorecard';

    public function up(): void
    {
        PermissionCatalog::sync();

        $permission = Permission::findByName(self::PERMISSION, 'web');

        Role::whereHas('permissions', fn ($query) => $query->where('name', 'view dashboard order stats'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        Permission::where('name', self::REPLACED)->get()->each->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', self::PERMISSION)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

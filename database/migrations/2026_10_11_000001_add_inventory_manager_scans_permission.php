<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Opening an inventory manager's scans from the Riders page is a permission
 * of its own. `superadmin` and `developer` get it here; every other role
 * gets it when someone ticks it in the role editor.
 */
return new class extends Migration
{
    private const PERMISSION = 'view inventory manager scans';

    public function up(): void
    {
        PermissionCatalog::sync();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', self::PERMISSION)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

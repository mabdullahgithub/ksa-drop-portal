<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The `partner` role (partner-area access only, like `client` for the portal)
 * and the staff permissions for the Partners page. A role that can open the
 * client list gets to see partners; approving, paying and settings stay with
 * the full-access roles until handed out from the role editor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Role::findOrCreate('partner', 'web');

        PermissionCatalog::sync();

        $view = Permission::findByName('view partners', 'web');

        Role::whereHas('permissions', fn ($query) => $query->where('name', 'view client'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($view));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', [
            'view partners', 'view partner stats', 'view partner documents', 'view partner leads',
            'review partner applications', 'suspend partners', 'edit partners', 'adjust partner points',
            'view partner payouts', 'handle partner payouts', 'edit partner settings',
        ])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The dashboard drops its tag cards and shipment status cards for three
 * charts: a city heatmap, the best couriers and the best stores. A role that
 * could see either of the old blocks gets the three charts in their place;
 * `superadmin` and `developer` get them through sync().
 */
return new class extends Migration
{
    private const RETIRED = ['view dashboard tag cards', 'view dashboard shipment status'];

    private const ADDED = [
        'view dashboard city heatmap',
        'view dashboard courier ranking',
        'view dashboard store ranking',
    ];

    public function up(): void
    {
        PermissionCatalog::sync();

        $added = Permission::where('guard_name', 'web')->whereIn('name', self::ADDED)->get();

        Role::whereHas('permissions', fn ($query) => $query->whereIn('name', self::RETIRED))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($added));

        Permission::whereIn('name', self::RETIRED)->get()->each->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', self::ADDED)->delete();

        foreach (self::RETIRED as $name) {
            Permission::findOrCreate($name, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Requests page: one permission for the page, one per tab and one per
 * button on it. `superadmin` and `developer` get them here; every other role
 * gets them when someone ticks them in the role editor.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'view requests',
        'view product requests',
        'view stock requests',
        'view detail requests',
        'review product requests',
        'approve stock requests',
        'reject stock requests',
        'bulk approve stock requests',
        'bulk reject stock requests',
        'approve detail requests',
        'reject detail requests',
        'bulk approve detail requests',
        'bulk reject detail requests',
    ];

    public function up(): void
    {
        PermissionCatalog::sync();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', self::PERMISSIONS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

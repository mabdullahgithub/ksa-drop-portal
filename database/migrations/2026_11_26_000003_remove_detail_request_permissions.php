<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Detail changes to verified products no longer need approval, so the
 * "Details changes" tab and its buttons are gone.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'view detail requests',
        'approve detail requests',
        'reject detail requests',
        'bulk approve detail requests',
        'bulk reject detail requests',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::whereIn('name', self::PERMISSIONS)->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        PermissionCatalog::sync();
    }

    public function down(): void
    {
        // The permissions come back with the code that used them.
    }
};

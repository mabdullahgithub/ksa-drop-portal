<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Live chat page: one permission for the inbox and one per thing an
 * agent can do in it. `superadmin` and `developer` get them here; every other
 * role gets them when someone ticks them in the role editor.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'view chats',
        'view chat stats',
        'reply chats',
        'add chat notes',
        'start chats',
        'change chat status',
        'edit chat details',
        'delete chats',
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

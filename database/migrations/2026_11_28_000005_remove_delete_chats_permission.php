<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Chats are kept as a record and can no longer be deleted: this removes the
 * `delete chats` permission and every grant of it. Syncing the catalog only
 * ever adds, so it has to be deleted here.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // One model at a time, so the package detaches its grants.
        Permission::where('name', 'delete chats')->get()->each->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Nothing to put back: the catalog no longer has the permission.
    }
};

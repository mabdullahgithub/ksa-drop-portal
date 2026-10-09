<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Moves the portal onto the permission catalog: one permission for each page,
 * section and button, where there used to be one per module.
 *
 * Every existing role is given the new permissions carved out of the ones it
 * holds, so nobody loses access on deploy; the old bundles that no longer gate
 * anything are then removed. `superadmin` and `developer` get everything.
 */
return new class extends Migration
{
    public function up(): void
    {
        PermissionCatalog::sync();
        PermissionCatalog::migrateLegacyRoles();
    }

    public function down(): void
    {
        // The old bundles cannot be rebuilt from the permissions that replaced them.
    }
};

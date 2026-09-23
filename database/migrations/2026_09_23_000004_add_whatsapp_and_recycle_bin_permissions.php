<?php

use App\Models\PermissionCategory;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gives the WhatsApp inbox and the Recycle Bin permissions of their own.
 *
 * Both shipped borrowing other permissions -- the inbox rode on view/edit
 * orders and the bin on the three delete permissions -- so neither could be
 * granted on its own. Every role and user that can reach them today is granted
 * the matching new permission here, so nobody loses access on deploy.
 *
 * The bin keeps its per-entity scoping: each tab still also requires that
 * entity's delete permission. These only add a gate on top.
 */
return new class extends Migration
{
    /**
     * New permission => the permissions whose holders get it (any one of them).
     */
    private const GRANTS = [
        'view whatsapp' => ['view orders'],
        'reply whatsapp' => ['edit orders'],
        'view recycle bin' => ['delete orders', 'delete client', 'delete inventory'],
        'restore recycle bin' => ['delete orders', 'delete client', 'delete inventory'],
        'purge recycle bin' => ['delete orders', 'delete client', 'delete inventory'],
    ];

    private const CATEGORIES = [
        'whatsapp' => [
            'label' => 'WhatsApp',
            'description' => 'WhatsApp confirmation inbox permissions',
            'order' => 13,
            'permissions' => ['view whatsapp', 'reply whatsapp'],
        ],
        'recycle bin' => [
            'label' => 'Recycle Bin',
            'description' => 'Recycle bin permissions',
            'order' => 14,
            'permissions' => ['view recycle bin', 'restore recycle bin', 'purge recycle bin'],
        ],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::CATEGORIES as $name => $category) {
            $model = PermissionCategory::firstOrCreate(
                ['name' => $name],
                ['label' => $category['label'], 'description' => $category['description'], 'order' => $category['order']]
            );

            foreach ($category['permissions'] as $permission) {
                Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web'])
                    ->update(['category_id' => $model->id]);
            }
        }

        // Read the existing holders before granting anything, so the grants
        // depend only on what was true before this migration ran.
        $existing = Permission::whereIn('name', array_unique(array_merge(...array_values(self::GRANTS))))
            ->pluck('name')
            ->all();

        foreach (self::GRANTS as $permission => $sources) {
            $sources = array_values(array_intersect($sources, $existing));

            $roles = Role::where('name', 'superadmin')
                ->orWhereHas('permissions', fn ($query) => $query->whereIn('name', $sources))
                ->get();

            foreach ($roles as $role) {
                $role->givePermissionTo($permission);
            }

            // Permissions granted straight to a user, not through a role. Not
            // User::permission(), which also matches role holders (already
            // covered above) and whose second argument means "without".
            if ($sources !== []) {
                User::whereHas('permissions', fn ($query) => $query->whereIn('name', $sources))->get()
                    ->each(fn (User $user) => $user->givePermissionTo($permission));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', array_keys(self::GRANTS))->delete();
        PermissionCategory::whereIn('name', array_keys(self::CATEGORIES))->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Safe to run any number of times. Every run checks the whole permission
     * list (App\Support\PermissionCatalog) and gives all of it to superadmin
     * and developer. The other roles are only created when missing: once they
     * exist they belong to whoever edits them in the Roles page, and a re-run
     * must not undo that.
     */
    public function run(): void
    {
        // Every permission exists, and superadmin + developer hold them all.
        PermissionCatalog::sync();

        $all = PermissionCatalog::names();

        // Admin — everything except handing out roles and permissions.
        $this->createRole('admin', array_values(array_diff($all, [
            'create roles', 'edit roles', 'delete roles',
        ])));

        // Manager — the daily work, without deleting forever or managing the team.
        $this->createRole('manager', array_values(array_filter($all, fn (string $permission) => ! str_starts_with($permission, 'purge ')
            && ! in_array($permission, [
                'empty recycle bin',
                'create users', 'edit users', 'assign user roles', 'assign client access', 'delete users',
                'create roles', 'edit roles', 'delete roles',
                'edit email settings', 'send test email', 'restore graveyard emails',
                'reveal connector secrets',
            ], true))));

        // Client — portal access only, section access controlled via portal_features column
        $this->createRole('client', []);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Create admin user if not exists
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@ksadrop.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if (! $adminUser->hasRole('superadmin')) {
            $adminUser->assignRole('superadmin');
        }
    }

    /** @param  list<string>  $permissions */
    private function createRole(string $name, array $permissions): void
    {
        if (Role::where('name', $name)->where('guard_name', 'web')->exists()) {
            return;
        }

        Role::create(['name' => $name, 'guard_name' => 'web'])->syncPermissions($permissions);
    }
}

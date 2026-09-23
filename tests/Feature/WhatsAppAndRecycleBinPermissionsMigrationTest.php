<?php

namespace Tests\Feature;

use App\Models\PermissionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The WhatsApp inbox and the Recycle Bin used to ride on order and delete
 * permissions. The migration that split them out must hand the new ones to
 * exactly the roles and users that could reach them before, or deploying it
 * would lock people out (or let new people in).
 */
class WhatsAppAndRecycleBinPermissionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PERMISSIONS = [
        'view whatsapp', 'reply whatsapp',
        'view recycle bin', 'restore recycle bin', 'purge recycle bin',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // RefreshDatabase already ran the migration against an empty table.
        // Start from the pre-migration state so up() below sees real holders.
        Permission::whereIn('name', self::NEW_PERMISSIONS)->delete();
        PermissionCategory::whereIn('name', ['whatsapp', 'recycle bin'])->delete();

        foreach (['view orders', 'edit orders', 'delete orders', 'delete client', 'delete inventory', 'view tags'] as $permission) {
            Permission::findOrCreate($permission);
        }
    }

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_09_23_000004_add_whatsapp_and_recycle_bin_permissions.php');
        $migration->up();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function role(string $name, array $permissions): Role
    {
        $role = Role::findOrCreate($name);
        $role->givePermissionTo($permissions);

        return $role;
    }

    public function test_order_viewers_can_read_whatsapp_but_not_reply(): void
    {
        $role = $this->role('viewer', ['view orders']);

        $this->runMigration();

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('view whatsapp'));
        $this->assertFalse($role->hasPermissionTo('reply whatsapp'));
    }

    public function test_order_editors_can_reply(): void
    {
        $role = $this->role('agent', ['view orders', 'edit orders']);

        $this->runMigration();

        $this->assertTrue($role->refresh()->hasPermissionTo('reply whatsapp'));
    }

    public function test_any_delete_permission_keeps_full_use_of_the_bin(): void
    {
        $role = $this->role('stock-keeper', ['delete inventory']);

        $this->runMigration();

        $role->refresh();
        foreach (['view recycle bin', 'restore recycle bin', 'purge recycle bin'] as $permission) {
            $this->assertTrue($role->hasPermissionTo($permission), $permission);
        }
    }

    public function test_roles_without_the_old_access_get_nothing(): void
    {
        $role = $this->role('tagger', ['view tags']);

        $this->runMigration();

        $this->assertSame([], $role->refresh()->permissions->pluck('name')
            ->intersect(self::NEW_PERMISSIONS)->values()->all());
    }

    public function test_superadmin_gets_everything_even_without_the_old_permissions(): void
    {
        $role = Role::findOrCreate('superadmin');

        $this->runMigration();

        $role->refresh();
        foreach (self::NEW_PERMISSIONS as $permission) {
            $this->assertTrue($role->hasPermissionTo($permission), $permission);
        }
    }

    public function test_directly_granted_user_permissions_are_carried_over(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('delete orders');

        $this->runMigration();

        $user->refresh();
        $this->assertTrue($user->hasDirectPermission('view recycle bin'));
        $this->assertFalse($user->hasDirectPermission('view whatsapp'));
    }

    public function test_the_new_permissions_land_in_their_own_categories(): void
    {
        $this->runMigration();

        $whatsapp = PermissionCategory::where('name', 'whatsapp')->firstOrFail();
        $bin = PermissionCategory::where('name', 'recycle bin')->firstOrFail();

        $this->assertSame($whatsapp->id, Permission::findByName('reply whatsapp')->category_id);
        $this->assertSame($bin->id, Permission::findByName('purge recycle bin')->category_id);
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        $role = $this->role('agent', ['view orders', 'edit orders']);

        $this->runMigration();
        $this->runMigration();

        $this->assertSame(1, Permission::where('name', 'view whatsapp')->count());
        $this->assertSame(1, PermissionCategory::where('name', 'whatsapp')->count());
        $this->assertTrue($role->refresh()->hasPermissionTo('reply whatsapp'));
    }
}

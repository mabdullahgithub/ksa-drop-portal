<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The move from one permission per module to one per button: a role keeps
 * everything it could do, and gains nothing it could not.
 */
class PermissionMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** A role as it was before the catalog, holding the old bundles. */
    private function legacyRole(string $name, array $permissions): Role
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        return Role::create(['name' => $name])->givePermissionTo($permissions);
    }

    private function migrated(Role $role): array
    {
        PermissionCatalog::migrateLegacyRoles();

        return $role->fresh()->permissions->pluck('name')->all();
    }

    public function test_an_order_editor_gets_every_button_the_bundle_covered(): void
    {
        $held = $this->migrated($this->legacyRole('ops', ['view orders', 'edit orders']));

        foreach (['view order details', 'update order call status', 'create shipments', 'cancel shipments', 'generate waybills', 'export orders'] as $permission) {
            $this->assertContains($permission, $held);
        }

        // Nothing from a bundle it never held.
        foreach (['delete orders', 'view client', 'reply whatsapp', 'view riders', 'check courier api health'] as $permission) {
            $this->assertNotContains($permission, $held);
        }
    }

    public function test_a_viewer_gets_the_view_side_only(): void
    {
        $held = $this->migrated($this->legacyRole('viewer', ['view orders']));

        $this->assertContains('view shipments', $held);
        $this->assertContains('view waybills', $held);
        $this->assertNotContains('create shipments', $held);
        $this->assertNotContains('update order call status', $held);
    }

    public function test_the_recycle_bin_keeps_its_two_key_rule(): void
    {
        $held = $this->migrated($this->legacyRole('bin', ['view recycle bin', 'restore recycle bin', 'delete orders']));

        $this->assertContains('view deleted orders', $held);
        $this->assertContains('restore deleted orders', $held);
        // It had the bin and could delete orders, nothing else.
        $this->assertNotContains('purge deleted orders', $held);
        $this->assertNotContains('view deleted clients', $held);
    }

    public function test_the_retired_bundles_are_removed(): void
    {
        $this->migrated($this->legacyRole('riders', ['manage riders', 'manage rider payments', 'edit apps', 'manage-email-settings']));

        $this->assertSame(0, Permission::whereIn('name', PermissionCatalog::retired())->count());
    }

    public function test_every_staff_role_keeps_its_own_account_and_the_bell(): void
    {
        $held = $this->migrated($this->legacyRole('anyone', ['view tags']));

        foreach (['view settings', 'change password', 'view notifications'] as $permission) {
            $this->assertContains($permission, $held);
        }
    }

    public function test_the_client_role_is_given_nothing(): void
    {
        $client = Role::findOrCreate('client');

        $this->assertSame([], $this->migrated($client));
    }

    public function test_a_user_holding_a_bundle_directly_is_carried_over_too(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('manage riders');

        $user = User::factory()->create();
        $user->givePermissionTo('manage riders');

        PermissionCatalog::migrateLegacyRoles();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $held = $user->fresh()->getDirectPermissions()->pluck('name')->all();

        $this->assertContains('create riders', $held);
        $this->assertContains('view riders', $held);
        // The account pages are for staff roles, not for a lone permission.
        $this->assertNotContains('view settings', $held);
    }
}

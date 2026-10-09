<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Concerns\MakesStaff;
use Tests\TestCase;

/**
 * superadmin and developer can do everything, always. Every other role,
 * `admin` included, can do exactly what it was given.
 */
class FullAccessTest extends TestCase
{
    use MakesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpStaff();
    }

    public function test_superadmin_and_developer_pass_every_gate_without_holding_a_single_permission(): void
    {
        foreach (User::FULL_ACCESS_ROLES as $name) {
            // Not even the permissions the seeder gives them: the role alone is enough.
            Role::findByName($name)->syncPermissions([]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $user = $this->fullAccessUser($name);

            $this->assertTrue($user->can('delete orders'));
            $this->actingAs($user)->getJson('/api/orders')->assertOk();
            $this->actingAs($user)->get('/team-management/roles')->assertOk();
        }
    }

    public function test_a_permission_added_later_already_works_for_full_access(): void
    {
        Permission::create(['name' => 'something added tomorrow']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($this->fullAccessUser('developer')->can('something added tomorrow'));
        $this->assertFalse($this->staffWith(['view orders'])->can('something added tomorrow'));
    }

    public function test_admin_is_an_ordinary_role(): void
    {
        $admin = $this->staffWith(['view orders'], 'admin');

        $this->assertFalse($admin->hasFullAccess());
        $this->actingAs($admin)->getJson('/api/orders')->assertOk();
        $this->actingAs($admin)->getJson('/api/clients')->assertForbidden();
        $this->actingAs($admin)->get('/team-management/roles')->assertForbidden();
    }

    public function test_the_browser_is_told_who_has_full_access(): void
    {
        $this->actingAs($this->fullAccessUser('developer'))->get('/orders')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.full_access', true)
                ->where('auth.permissions', PermissionCatalog::names()));

        $this->actingAs($this->staffWith(['view orders']))->get('/orders')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.full_access', false)
                ->where('auth.permissions', ['view orders']));
    }

    // ----------------------------------------------------------------- seeder

    public function test_every_seeder_run_gives_both_roles_every_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (User::FULL_ACCESS_ROLES as $name) {
            $this->assertEqualsCanonicalizing(
                PermissionCatalog::names(),
                Role::findByName($name)->permissions->pluck('name')->all(),
            );
        }
    }

    public function test_a_second_run_restores_what_went_missing_and_leaves_edited_roles_alone(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // A permission lost from the database, and one the superadmin was stripped of.
        Permission::findByName('cancel shipments')->delete();
        Role::findByName('superadmin')->revokePermissionTo('view orders');
        // The admin role, edited in the Roles page.
        Role::findByName('admin')->syncPermissions(['view orders']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (User::FULL_ACCESS_ROLES as $name) {
            $held = Role::findByName($name)->permissions->pluck('name')->all();

            $this->assertContains('cancel shipments', $held);
            $this->assertContains('view orders', $held);
            $this->assertCount(count(PermissionCatalog::names()), $held);
        }

        $this->assertSame(['view orders'], Role::findByName('admin')->permissions->pluck('name')->all());
    }

    // ------------------------------------------------------------ role editor

    public function test_the_full_access_roles_cannot_be_edited_or_deleted(): void
    {
        $superadmin = $this->fullAccessUser();

        foreach ([...User::FULL_ACCESS_ROLES, 'client'] as $name) {
            $role = Role::findByName($name);

            $this->actingAs($superadmin)
                ->put("/team-management/roles/{$role->id}", ['name' => 'renamed', 'permissions' => []])
                ->assertSessionHasErrors('name');
            $this->actingAs($superadmin)
                ->delete("/team-management/roles/{$role->id}")
                ->assertSessionHasErrors('name');

            $this->assertNotNull(Role::where('name', $name)->first());
        }
    }

    public function test_saving_a_role_adds_the_pages_its_buttons_sit_on(): void
    {
        $this->actingAs($this->fullAccessUser())
            ->post('/team-management/roles', ['name' => 'canceller', 'permissions' => ['cancel shipments']])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['view orders', 'view order details', 'view shipments', 'cancel shipments'],
            Role::findByName('canceller')->permissions->pluck('name')->all(),
        );
    }

    public function test_a_role_editor_can_only_hand_out_permissions_they_hold(): void
    {
        $editor = $this->staffWith(['view roles', 'create roles', 'edit roles', 'view orders']);

        $this->actingAs($editor)
            ->post('/team-management/roles', ['name' => 'helper', 'permissions' => ['view orders', 'delete orders', 'view client']])
            ->assertSessionHasNoErrors();

        $this->assertSame(['view orders'], Role::findByName('helper')->permissions->pluck('name')->all());
    }

    public function test_editing_a_role_leaves_the_permissions_the_editor_does_not_hold(): void
    {
        $editor = $this->staffWith(['view roles', 'edit roles', 'view orders']);
        $role = Role::create(['name' => 'support']);
        $role->givePermissionTo(['view client', 'view orders']);

        // The editor unticks what they can and tries to add what they cannot.
        $this->actingAs($editor)
            ->put("/team-management/roles/{$role->id}", ['name' => 'support', 'permissions' => ['view tags']])
            ->assertSessionHasNoErrors();

        $this->assertSame(['view client'], $role->fresh()->permissions->pluck('name')->all());
    }

    public function test_nobody_edits_or_deletes_a_role_they_belong_to(): void
    {
        $editor = $this->staffWith(['view roles', 'edit roles', 'delete roles', 'view orders'], 'team-lead');
        $role = Role::findByName('team-lead');

        $this->actingAs($editor)
            ->put("/team-management/roles/{$role->id}", ['name' => 'team-lead', 'permissions' => ['view orders', 'edit roles']])
            ->assertSessionHasErrors('name');
        $this->actingAs($editor)
            ->delete("/team-management/roles/{$role->id}")
            ->assertSessionHasErrors('name');

        $this->assertNotNull(Role::where('name', 'team-lead')->first());
    }

    // ---------------------------------------------------------------- users

    public function test_only_full_access_hands_out_the_developer_role_and_nobody_the_superadmin_role(): void
    {
        $target = User::factory()->create();
        $manager = $this->staffWith(['view users', 'edit users', 'assign user roles']);

        $this->actingAs($manager)
            ->put("/team-management/users/{$target->id}", ['roles' => ['developer', 'superadmin']])
            ->assertSessionHasNoErrors();
        $this->assertFalse($target->fresh()->hasFullAccess());

        $this->actingAs($this->fullAccessUser())
            ->put("/team-management/users/{$target->id}", ['roles' => ['developer', 'superadmin']])
            ->assertSessionHasNoErrors();
        $this->assertSame(['developer'], $target->fresh()->getRoleNames()->all());
    }

    public function test_a_role_with_more_than_you_have_cannot_be_handed_out(): void
    {
        Role::create(['name' => 'big'])->givePermissionTo(['view orders', 'delete orders']);
        Role::create(['name' => 'small'])->givePermissionTo(['view orders']);

        $manager = $this->staffWith(['view users', 'edit users', 'assign user roles', 'view orders']);
        $target = User::factory()->create();

        $this->actingAs($manager)
            ->put("/team-management/users/{$target->id}", ['roles' => ['big', 'small']])
            ->assertSessionHasNoErrors();

        $this->assertSame(['small'], $target->fresh()->getRoleNames()->all());
    }

    public function test_a_role_you_cannot_hand_out_is_not_taken_away_either(): void
    {
        Role::create(['name' => 'big'])->givePermissionTo(['view orders', 'delete orders']);

        $manager = $this->staffWith(['view users', 'edit users', 'assign user roles', 'view orders']);
        $target = User::factory()->create();
        $target->assignRole('big');

        $this->actingAs($manager)
            ->put("/team-management/users/{$target->id}", ['roles' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame(['big'], $target->fresh()->getRoleNames()->all());
    }

    public function test_nobody_changes_their_own_roles(): void
    {
        $manager = $this->staffWith(['view users', 'edit users', 'assign user roles']);

        $this->actingAs($manager)
            ->put("/team-management/users/{$manager->id}", ['roles' => []])
            ->assertSessionHasErrors('roles');

        $this->assertCount(1, $manager->fresh()->roles);
    }

    public function test_a_developer_is_only_edited_or_deleted_by_full_access(): void
    {
        $developer = $this->fullAccessUser('developer');
        $manager = $this->staffWith(['view users', 'edit users', 'assign user roles', 'delete users']);

        $this->actingAs($manager)
            ->put("/team-management/users/{$developer->id}", ['roles' => []])
            ->assertSessionHasErrors('roles');
        $this->actingAs($manager)
            ->delete("/team-management/users/{$developer->id}")
            ->assertSessionHasErrors('user');

        $this->assertTrue($developer->fresh()->hasRole('developer'));
    }

    public function test_assigning_roles_is_its_own_permission(): void
    {
        Role::create(['name' => 'small']);
        $target = User::factory()->create();

        // Can open the user, but not change their roles.
        $this->actingAs($this->staffWith(['view users', 'edit users']))
            ->put("/team-management/users/{$target->id}", ['roles' => ['small']])
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $target->fresh()->roles);
    }
}

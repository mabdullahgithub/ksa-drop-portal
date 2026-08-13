<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A role granted "edit apps" but not "view apps" could reach /apps/imile
 * (page route only requires edit apps) yet get silently 403'd on
 * /api/connectors (required view apps) — the settings page's init() then
 * swallowed the error and rendered every field empty, including unrelated
 * ones like customer_id. Fixed by letting CheckPermission accept several
 * comma-separated permissions with OR semantics.
 */
class CheckPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('view apps');
        Permission::findOrCreate('edit apps');
    }

    private function makeUserWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'role-' . uniqid()]);
        $role->givePermissionTo($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_edit_only_role_can_reach_a_route_gated_on_view_or_edit(): void
    {
        $user = $this->makeUserWithPermissions(['edit apps']);

        $this->actingAs($user)
            ->getJson('/api/connectors')
            ->assertOk();
    }

    public function test_view_only_role_can_still_reach_a_route_gated_on_view_or_edit(): void
    {
        $user = $this->makeUserWithPermissions(['view apps']);

        $this->actingAs($user)
            ->getJson('/api/connectors')
            ->assertOk();
    }

    public function test_role_with_neither_permission_is_still_forbidden(): void
    {
        $user = $this->makeUserWithPermissions([]);

        $this->actingAs($user)
            ->getJson('/api/connectors')
            ->assertForbidden();
    }
}

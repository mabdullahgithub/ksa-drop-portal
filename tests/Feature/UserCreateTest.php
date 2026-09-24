<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_user(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('create users');
        $admin = Role::create(['name' => 'admin']);
        $admin->givePermissionTo('create users');
        Role::create(['name' => 'superadmin']);
        Role::create(['name' => 'staff']);

        $actor = User::factory()->create();
        $actor->assignRole('admin');
        User::factory()->create()->assignRole('superadmin');

        $this->withoutExceptionHandling()->actingAs($actor)
            ->post('/team-management/users', ['name' => 'New Person', 'email' => 'new@example.com', 'roles' => ['staff']])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_users_page_flags_client_accounts_for_the_clients_tab(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('view users');
        Role::create(['name' => 'admin'])->givePermissionTo('view users');
        Role::create(['name' => 'client']);

        $actor = User::factory()->create();
        $actor->assignRole('admin');
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($actor)->get('/team-management/users')
            ->assertInertia(fn (Assert $page) => $page
                ->where('users', fn ($users) => collect($users)->firstWhere('id', $client->id)['is_client'] === true
                    && collect($users)->firstWhere('id', $actor->id)['is_client'] === false));
    }
}

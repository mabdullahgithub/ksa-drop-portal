<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureRecycleBinUnlocked;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\DeletionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Team management users: create, the Team / Clients split, and deletes that
 * go to the recycle bin instead of removing the row.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view users', 'create users', 'delete users'] as $permission) {
            Permission::findOrCreate($permission);
        }

        Role::create(['name' => 'admin'])->givePermissionTo(['view users', 'create users', 'delete users']);
        Role::create(['name' => 'superadmin']);
        Role::create(['name' => 'client']);
        Role::create(['name' => 'staff']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->withSession([EnsureRecycleBinUnlocked::SESSION_KEY => now()]);
    }

    public function test_admin_can_create_a_user(): void
    {
        $this->actingAs($this->admin)
            ->post('/team-management/users', ['name' => 'New Person', 'email' => 'new@example.com', 'roles' => ['staff']])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(User::where('email', 'new@example.com')->firstOrFail()->hasRole('staff'));
    }

    public function test_users_page_flags_client_accounts_for_the_clients_tab(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($this->admin)->get('/team-management/users')
            ->assertInertia(fn (Assert $page) => $page
                ->where('users', fn ($users) => collect($users)->firstWhere('id', $client->id)['is_client'] === true
                    && collect($users)->firstWhere('id', $this->admin->id)['is_client'] === false));
    }

    public function test_deleting_a_user_moves_it_to_the_recycle_bin(): void
    {
        $user = User::factory()->create(['name' => 'Binned Person']);
        $user->assignRole('staff');

        $this->actingAs($this->admin)->delete("/team-management/users/{$user->id}")->assertRedirect();

        $this->assertSoftDeleted($user);
        $this->assertSame($this->admin->id, User::withTrashed()->find($user->id)->deleted_by);
        $this->assertTrue(DeletionLog::where('subject_type', 'User')->where('subject_id', $user->id)->where('action', 'deleted')->exists());

        // Gone from the users page, listed in the bin.
        $this->actingAs($this->admin)->get('/team-management/users')
            ->assertInertia(fn (Assert $page) => $page
                ->where('users', fn ($users) => collect($users)->doesntContain('id', $user->id)));

        $this->actingAs($this->admin)->getJson('/api/recycle-bin/users')
            ->assertOk()
            ->assertJsonPath('data.0.id', $user->id)
            ->assertJsonPath('data.0.deleted_by.name', $this->admin->name);

        $this->actingAs($this->admin)->getJson('/api/recycle-bin/counts')->assertJsonPath('users', 1);
    }

    public function test_a_deleted_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['email' => 'gone@example.com', 'password' => 'secret-password']);
        $user->delete();

        $this->post('/login', ['email' => 'gone@example.com', 'password' => 'secret-password']);

        $this->assertGuest();
    }

    public function test_restore_brings_the_user_back_with_their_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('staff');
        $user->delete();

        $this->actingAs($this->admin)->postJson('/api/recycle-bin/users/restore', ['ids' => [$user->id]])
            ->assertOk()
            ->assertJsonPath('restored_count', 1);

        $user = User::find($user->id);
        $this->assertNotNull($user);
        $this->assertNull($user->deleted_by);
        $this->assertTrue($user->hasRole('staff'));
    }

    public function test_bulk_delete_bins_users_but_skips_superadmins_and_self(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $super = User::factory()->create();
        $super->assignRole('superadmin');

        $this->actingAs($this->admin)
            ->post('/team-management/users/bulk-delete', ['ids' => [$a->id, $b->id, $super->id, $this->admin->id]])
            ->assertRedirect();

        $this->assertSoftDeleted($a);
        $this->assertSoftDeleted($b);
        $this->assertNotSoftDeleted($super);
        $this->assertNotSoftDeleted($this->admin);
    }

    public function test_creating_a_user_with_a_binned_email_explains_where_it_is(): void
    {
        User::factory()->create(['email' => 'taken@example.com'])->delete();

        $this->actingAs($this->admin)
            ->post('/team-management/users', ['name' => 'Again', 'email' => 'taken@example.com'])
            ->assertSessionHasErrors(['email' => 'A deleted user with this email is in the recycle bin. Restore them from there instead.']);
    }

    public function test_purge_removes_a_plain_user_for_good(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $this->actingAs($this->admin)->postJson('/api/recycle-bin/users/purge', ['ids' => [$user->id]])
            ->assertOk()
            ->assertJsonPath('purged_count', 1);

        $this->assertNull(User::withTrashed()->find($user->id));
    }

    /**
     * clients.user_id cascades and client_payments.created_by restricts, so
     * purging either kind of user would destroy a client or fail outright.
     */
    public function test_purge_skips_client_logins_and_payment_recorders(): void
    {
        $clientUser = User::factory()->create(['name' => 'Client Login']);
        $client = Client::create([
            'user_id' => $clientUser->id,
            'client_types' => ['fulfilment'],
            'company_name' => 'Acme Trading',
            'short_id' => 'ACME' . substr(uniqid(), -6),
            'status' => 'active',
        ]);

        $payer = User::factory()->create(['name' => 'Payment Recorder']);
        ClientPayment::create(['client_id' => $client->id, 'amount' => 10, 'paid_at' => now(), 'created_by' => $payer->id]);

        $clientUser->delete();
        $payer->delete();

        $this->actingAs($this->admin)->postJson('/api/recycle-bin/users/purge-all')
            ->assertOk()
            ->assertJsonPath('purged_count', 0)
            ->assertJsonCount(2, 'blocked');

        $this->assertNotNull(Client::find($client->id));
        $this->assertSoftDeleted($clientUser);
        $this->assertSoftDeleted($payer);
    }

    public function test_users_tab_requires_delete_users(): void
    {
        $role = Role::create(['name' => 'orders-only']);
        Permission::findOrCreate('delete orders');
        $role->givePermissionTo('delete orders');
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->getJson('/api/recycle-bin/users')->assertForbidden();
    }
}

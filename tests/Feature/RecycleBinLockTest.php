<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureRecycleBinUnlocked;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The recycle bin is PIN-gated. The gate has to hold on the API, not just in
 * the UI: listing, restore and purge are plain JSON routes that anyone signed
 * in with the delete permission could otherwise call directly.
 */
class RecycleBinLockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A throwaway value, not the real PIN: that lives only in .env, never in
     * the repository. Every test here sets config('recyclebin.pin') to this.
     */
    private const PIN = '1111111';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        RateLimiter::clear('');

        foreach (['delete orders', 'delete client', 'delete inventory'] as $permission) {
            Permission::findOrCreate($permission);
        }

        config(['recyclebin.pin' => self::PIN, 'recyclebin.unlock_ttl' => 30]);
    }

    private function admin(): User
    {
        $role = Role::create(['name' => 'role-' . uniqid()]);
        $role->givePermissionTo(['delete orders', 'delete client', 'delete inventory']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_listing_is_locked_until_the_pin_is_entered(): void
    {
        $this->actingAs($this->admin())
            ->getJson('/api/recycle-bin/orders')
            ->assertStatus(423);
    }

    public function test_every_data_route_is_locked(): void
    {
        $user = $this->admin();

        $gets = ['/api/recycle-bin/counts', '/api/recycle-bin/orders', '/api/recycle-bin/clients', '/api/recycle-bin/inventory'];
        foreach ($gets as $route) {
            $this->actingAs($user)->getJson($route)->assertStatus(423);
        }

        $posts = [
            '/api/recycle-bin/orders/restore', '/api/recycle-bin/orders/purge', '/api/recycle-bin/orders/purge-all',
            '/api/recycle-bin/clients/restore', '/api/recycle-bin/clients/purge', '/api/recycle-bin/clients/purge-all',
            '/api/recycle-bin/inventory/restore', '/api/recycle-bin/inventory/purge', '/api/recycle-bin/inventory/purge-all',
        ];
        foreach ($posts as $route) {
            $this->actingAs($user)->postJson($route, ['ids' => [1]])->assertStatus(423);
        }
    }

    /**
     * The gate must stop the destructive routes specifically -- a lock that
     * only covered the listing would be decorative.
     */
    public function test_purge_cannot_run_while_locked(): void
    {
        $order = Order::create(['order_number' => 'ORD-LOCK-1']);
        $order->delete();

        $this->actingAs($this->admin())
            ->postJson('/api/recycle-bin/orders/purge-all')
            ->assertStatus(423);

        $this->assertNotNull(Order::withoutGlobalScopes()->withTrashed()->find($order->id));
    }

    public function test_the_correct_pin_unlocks_the_bin(): void
    {
        $user = $this->admin();

        $this->actingAs($user)
            ->postJson('/api/recycle-bin/unlock', ['pin' => self::PIN])
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/recycle-bin/orders')
            ->assertOk();
    }

    public function test_a_wrong_pin_is_rejected_and_does_not_unlock(): void
    {
        $user = $this->admin();

        $this->actingAs($user)
            ->postJson('/api/recycle-bin/unlock', ['pin' => '0000000'])
            ->assertStatus(422);

        $this->actingAs($user)
            ->getJson('/api/recycle-bin/orders')
            ->assertStatus(423);
    }

    public function test_an_expired_unlock_relocks_the_bin(): void
    {
        $user = $this->admin();

        $this->actingAs($user)
            ->withSession([EnsureRecycleBinUnlocked::SESSION_KEY => now()->subMinutes(31)])
            ->getJson('/api/recycle-bin/orders')
            ->assertStatus(423);
    }

    public function test_locking_drops_the_unlock(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->postJson('/api/recycle-bin/unlock', ['pin' => self::PIN])->assertOk();
        $this->actingAs($user)->postJson('/api/recycle-bin/lock')->assertOk();

        $this->actingAs($user)
            ->getJson('/api/recycle-bin/orders')
            ->assertStatus(423);
    }

    /**
     * A 7-digit PIN is walkable without a limit on attempts.
     */
    public function test_unlock_attempts_are_rate_limited(): void
    {
        $user = $this->admin();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($user)
                ->postJson('/api/recycle-bin/unlock', ['pin' => '0000000'])
                ->assertStatus(422);
        }

        $this->actingAs($user)
            ->postJson('/api/recycle-bin/unlock', ['pin' => '0000000'])
            ->assertStatus(429);
    }

    public function test_the_pin_is_never_sent_to_the_browser(): void
    {
        $user = $this->admin();

        $page = $this->actingAs($user)->get('/recycle-bin')->assertOk();

        $this->assertStringNotContainsString(self::PIN, $page->getContent());
    }

    public function test_a_user_without_delete_permission_cannot_even_try_the_pin(): void
    {
        $role = Role::create(['name' => 'role-' . uniqid()]);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->postJson('/api/recycle-bin/unlock', ['pin' => self::PIN])
            ->assertForbidden();
    }
}

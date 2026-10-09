<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureWhatsAppUnlocked;
use App\Models\Order;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The WhatsApp inbox is locked with the recycle bin's PIN. As with the bin, the
 * gate has to hold on the API: the list, thread and reply are plain JSON routes
 * that anyone with 'view whatsapp' could otherwise call directly.
 */
class WhatsAppInboxLockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A throwaway value, not the real PIN: that lives only in .env, never in
     * the repository.
     */
    private const PIN = '1111111';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view whatsapp', 'reply whatsapp', 'view recycle bin'] as $permission) {
            Permission::findOrCreate($permission);
        }

        config(['recyclebin.pin' => self::PIN, 'whatsapp.unlock_ttl' => 30]);
    }

    private function agent(array $permissions = ['view whatsapp', 'reply whatsapp']): User
    {
        $role = Role::create(['name' => 'role-' . uniqid()]);
        $role->givePermissionTo($this->permissions($permissions));

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function conversation(): Order
    {
        $order = Order::create([
            'order_number' => 'KSA-LOCK-' . random_int(1000, 9999),
            'customer_name' => 'Zain Al Otaibi',
            'customer_phone' => '0501234567',
            'whatsapp_status' => Order::WHATSAPP_REPLIED,
            'whatsapp_phone_e164' => '+966501234567',
            'whatsapp_sent_at' => now()->subHour(),
            'whatsapp_replied_at' => now()->subMinutes(5),
        ]);

        // An inbound message opens the 24-hour window, so a refused reply can
        // only be the lock's doing.
        WhatsAppMessage::create([
            'order_id' => $order->id,
            'direction' => WhatsAppMessage::DIRECTION_INBOUND,
            'provider_message_id' => 'wamid.' . random_int(100000, 999999),
            'body' => 'Yes please',
            'from_number' => '+966501234567',
            'status' => 'received',
        ]);

        return $order;
    }

    public function test_every_conversation_route_is_locked_until_the_pin_is_entered(): void
    {
        $user = $this->agent();
        $order = $this->conversation();

        $this->actingAs($user)->getJson('/api/whatsapp/conversations')->assertStatus(423);
        $this->actingAs($user)->getJson("/api/whatsapp/conversations/{$order->id}")->assertStatus(423);
        $this->actingAs($user)
            ->postJson("/api/whatsapp/conversations/{$order->id}/reply", ['body' => 'Hello'])
            ->assertStatus(423);

        $this->assertSame(0, $order->whatsappMessages()->where('direction', WhatsAppMessage::DIRECTION_OUTBOUND)->count());
    }

    /**
     * The dashboard tiles read the counts; they carry no customer data.
     */
    public function test_the_stats_stay_open(): void
    {
        $this->actingAs($this->agent())->getJson('/api/whatsapp/stats')->assertOk();
    }

    public function test_the_recycle_bin_pin_unlocks_the_inbox(): void
    {
        $user = $this->agent();

        $this->actingAs($user)
            ->postJson('/api/whatsapp/unlock', ['pin' => self::PIN])
            ->assertOk();

        $this->actingAs($user)->getJson('/api/whatsapp/conversations')->assertOk();
    }

    public function test_a_wrong_pin_is_rejected_and_does_not_unlock(): void
    {
        $user = $this->agent();

        $this->actingAs($user)
            ->postJson('/api/whatsapp/unlock', ['pin' => '0000000'])
            ->assertStatus(422);

        $this->actingAs($user)->getJson('/api/whatsapp/conversations')->assertStatus(423);
    }

    public function test_with_no_pin_configured_nothing_unlocks(): void
    {
        config(['recyclebin.pin' => '']);

        $this->actingAs($this->agent())
            ->postJson('/api/whatsapp/unlock', ['pin' => ''])
            ->assertStatus(422);
    }

    public function test_it_locks_again_after_sitting_idle(): void
    {
        $this->actingAs($this->agent())
            ->withSession([EnsureWhatsAppUnlocked::SESSION_KEY => now()->subMinutes(31)])
            ->getJson('/api/whatsapp/conversations')
            ->assertStatus(423);
    }

    /**
     * Idle time, not time since unlock: an agent working the inbox all
     * afternoon is not sent back to the prompt.
     */
    public function test_activity_keeps_it_unlocked(): void
    {
        $user = $this->agent();

        $this->actingAs($user)
            ->withSession([EnsureWhatsAppUnlocked::SESSION_KEY => now()->subMinutes(20)])
            ->getJson('/api/whatsapp/conversations')
            ->assertOk();

        $this->travel(20)->minutes();

        // 40 minutes after unlocking, but only 20 since the last request.
        $this->actingAs($user)->getJson('/api/whatsapp/conversations')->assertOk();
    }

    public function test_locking_drops_the_unlock(): void
    {
        $user = $this->agent();

        $this->actingAs($user)->postJson('/api/whatsapp/unlock', ['pin' => self::PIN])->assertOk();
        $this->actingAs($user)->postJson('/api/whatsapp/lock')->assertOk();

        $this->actingAs($user)->getJson('/api/whatsapp/conversations')->assertStatus(423);
    }

    /**
     * A 7-digit PIN is walkable without a limit on attempts.
     */
    public function test_unlock_attempts_are_rate_limited(): void
    {
        $user = $this->agent();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($user)
                ->postJson('/api/whatsapp/unlock', ['pin' => '0000000'])
                ->assertStatus(422);
        }

        $this->actingAs($user)
            ->postJson('/api/whatsapp/unlock', ['pin' => '0000000'])
            ->assertStatus(429);
    }

    /**
     * Same PIN, separate attempt counts: failing at the inbox must not lock
     * someone out of the recycle bin, or the other way round.
     */
    public function test_failed_inbox_attempts_do_not_use_up_the_recycle_bins(): void
    {
        $user = $this->agent(['view whatsapp', 'view recycle bin']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($user)->postJson('/api/whatsapp/unlock', ['pin' => '0000000'])->assertStatus(422);
        }

        $this->actingAs($user)
            ->postJson('/api/recycle-bin/unlock', ['pin' => self::PIN])
            ->assertOk();
    }

    public function test_unlocking_the_inbox_does_not_open_the_recycle_bin(): void
    {
        $user = $this->agent(['view whatsapp', 'view recycle bin']);

        $this->actingAs($user)->postJson('/api/whatsapp/unlock', ['pin' => self::PIN])->assertOk();

        $this->actingAs($user)->getJson('/api/recycle-bin/counts')->assertStatus(423);
    }

    public function test_the_pin_is_never_sent_to_the_browser(): void
    {
        $page = $this->actingAs($this->agent())->get('/whatsapp')->assertOk();

        $this->assertStringNotContainsString(self::PIN, $page->getContent());
    }

    public function test_a_user_without_whatsapp_permission_cannot_even_try_the_pin(): void
    {
        $this->actingAs($this->agent([]))
            ->postJson('/api/whatsapp/unlock', ['pin' => self::PIN])
            ->assertForbidden();
    }
}

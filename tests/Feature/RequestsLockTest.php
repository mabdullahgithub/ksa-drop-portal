<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureRecycleBinUnlocked;
use App\Http\Middleware\EnsureRequestsUnlocked;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MakesInventory;
use Tests\Feature\Concerns\MakesStaff;
use Tests\TestCase;

/**
 * The Requests page is locked with the recycle bin's PIN. As with the bin and
 * the WhatsApp inbox, the gate has to hold on the API: the queues and the
 * approve and reject routes are plain JSON that anyone with the permission
 * could otherwise call directly.
 *
 * Each locked screen has a switch in .env; the last tests cover those.
 */
class RequestsLockTest extends TestCase
{
    use MakesInventory, MakesStaff, RefreshDatabase;

    /**
     * A throwaway value, not the real PIN: that lives only in .env, never in
     * the repository.
     */
    private const PIN = '1111111';

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpStaff();

        config(['recyclebin.pin' => self::PIN, 'locks.requests_unlock_ttl' => 30]);

        $this->staff = $this->fullAccessUser();
    }

    public function test_every_route_of_the_page_is_locked_until_the_pin_is_entered(): void
    {
        [, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $pending = $this->makeProduct($client, ['verification_status' => 'pending', 'sku' => 'NEW-1']);
        $stock = $this->stockRequest($product, 300);
        $details = $this->detailsRequest($product, ['name' => 'Navy Mug']);

        foreach (['counts', 'products', 'stock', 'details'] as $list) {
            $this->actingAs($this->staff)->getJson("/api/requests/{$list}")->assertStatus(423);
        }

        $posts = [
            "/api/requests/products/{$pending->id}/review" => ['action' => 'verify'],
            "/api/requests/stock/{$stock->id}/approve" => ['units' => 200],
            "/api/requests/stock/{$stock->id}/reject" => ['reason' => 'No'],
            '/api/requests/stock/bulk-approve' => ['ids' => [$stock->id]],
            '/api/requests/stock/bulk-reject' => ['ids' => [$stock->id], 'reason' => 'No'],
            "/api/requests/details/{$details->id}/approve" => [],
            "/api/requests/details/{$details->id}/reject" => ['reason' => 'No'],
            '/api/requests/details/bulk-approve' => ['ids' => [$details->id]],
            '/api/requests/details/bulk-reject' => ['ids' => [$details->id], 'reason' => 'No'],
        ];

        foreach ($posts as $url => $payload) {
            $this->actingAs($this->staff)->postJson($url, $payload)->assertStatus(423);
        }

        // Nothing got through.
        $this->assertSame(100, $product->fresh()->quantity);
        $this->assertSame('Blue Mug', $product->fresh()->name);
        $this->assertSame('pending', $pending->fresh()->verification_status);
        $this->assertSame(['pending', 'pending'], [$stock->fresh()->status, $details->fresh()->status]);
    }

    /**
     * The page itself opens, so there is something to put the prompt on; and
     * the number in the sidebar is only a count.
     */
    public function test_the_page_and_the_sidebar_count_stay_open(): void
    {
        [, $client] = $this->fulfilmentClient();
        $this->stockRequest($this->makeProduct($client), 300);

        $this->actingAs($this->staff)->get('/requests')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('pendingRequestsCount', 1)
                ->where('locks.requests', true));
    }

    public function test_the_recycle_bin_pin_unlocks_the_page(): void
    {
        $this->actingAs($this->staff)->postJson('/api/requests/unlock', ['pin' => self::PIN])->assertOk();

        $this->actingAs($this->staff)->getJson('/api/requests/stock')->assertOk();
    }

    public function test_a_wrong_pin_is_rejected_and_does_not_unlock(): void
    {
        $this->actingAs($this->staff)->postJson('/api/requests/unlock', ['pin' => '0000000'])->assertStatus(422);

        $this->actingAs($this->staff)->getJson('/api/requests/stock')->assertStatus(423);
    }

    public function test_with_no_pin_configured_nothing_unlocks(): void
    {
        config(['recyclebin.pin' => '']);

        $this->actingAs($this->staff)->postJson('/api/requests/unlock', ['pin' => ''])->assertStatus(422);
        $this->actingAs($this->staff)->getJson('/api/requests/stock')->assertStatus(423);
    }

    public function test_it_locks_again_after_sitting_idle(): void
    {
        $this->actingAs($this->staff)->postJson('/api/requests/unlock', ['pin' => self::PIN])->assertOk();

        $this->travel(31)->minutes();

        $this->actingAs($this->staff)->getJson('/api/requests/stock')->assertStatus(423);
    }

    public function test_activity_keeps_it_unlocked(): void
    {
        $this->actingAs($this->staff)->postJson('/api/requests/unlock', ['pin' => self::PIN])->assertOk();

        // Three visits twenty minutes apart: an hour in all, never idle for thirty.
        foreach (range(1, 3) as $visit) {
            $this->travel(20)->minutes();
            $this->actingAs($this->staff)->getJson('/api/requests/stock')->assertOk();
        }
    }

    public function test_locking_drops_the_unlock(): void
    {
        $this->actingAs($this->staff)->postJson('/api/requests/unlock', ['pin' => self::PIN])->assertOk();
        $this->actingAs($this->staff)->postJson('/api/requests/lock')->assertOk();

        $this->actingAs($this->staff)->getJson('/api/requests/stock')->assertStatus(423);
    }

    public function test_unlock_attempts_are_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->actingAs($this->staff)->postJson('/api/requests/unlock', ['pin' => '0000000'])->assertStatus(422);
        }

        // The sixth is refused before the PIN is even looked at, right or wrong.
        $this->actingAs($this->staff)->postJson('/api/requests/unlock', ['pin' => self::PIN])->assertStatus(429);
    }

    public function test_unlocking_the_requests_does_not_open_the_recycle_bin(): void
    {
        $this->actingAs($this->staff)->postJson('/api/requests/unlock', ['pin' => self::PIN])->assertOk();

        $this->actingAs($this->staff)->getJson('/api/recycle-bin/counts')->assertStatus(423);
    }

    public function test_someone_who_cannot_open_the_page_cannot_even_try_the_pin(): void
    {
        $outsider = $this->staffWith(['view orders']);

        $this->actingAs($outsider)->postJson('/api/requests/unlock', ['pin' => self::PIN])->assertForbidden();
    }

    public function test_the_pin_is_never_sent_to_the_browser(): void
    {
        $this->actingAs($this->staff)->get('/requests')->assertOk()->assertDontSee(self::PIN);
    }

    // ------------------------------------------------- the switches in .env

    public function test_with_its_lock_switched_off_the_page_needs_no_pin(): void
    {
        config(['locks.screens.requests' => false]);

        $this->actingAs($this->staff)->getJson('/api/requests/stock')->assertOk();
        $this->actingAs($this->staff)->get('/requests')
            ->assertInertia(fn ($page) => $page->where('locks.requests', false)->where('locks.recycle_bin', true));

        // Its switch is its own: the other screens stay locked.
        $this->actingAs($this->staff)->getJson('/api/recycle-bin/counts')->assertStatus(423);
        $this->actingAs($this->staff)->getJson('/api/whatsapp/conversations')->assertStatus(423);
    }

    public function test_the_recycle_bin_and_the_inbox_each_have_a_switch_too(): void
    {
        config(['locks.screens.recycle_bin' => false]);

        $this->actingAs($this->staff)->getJson('/api/recycle-bin/counts')->assertOk();
        $this->actingAs($this->staff)->getJson('/api/whatsapp/conversations')->assertStatus(423);
        $this->actingAs($this->staff)->getJson('/api/requests/stock')->assertStatus(423);

        config(['locks.screens.recycle_bin' => true, 'locks.screens.whatsapp' => false]);

        $this->actingAs($this->staff)->getJson('/api/whatsapp/conversations')->assertOk();
        $this->actingAs($this->staff)->getJson('/api/recycle-bin/counts')->assertStatus(423);
    }

    public function test_a_screen_is_locked_unless_told_otherwise(): void
    {
        $this->assertSame(
            ['recycle_bin' => true, 'whatsapp' => true, 'requests' => true],
            (require config_path('locks.php'))['screens'],
        );

        // Switching one off does not unlock a session that was never opened
        // on another.
        $this->assertNull(session(EnsureRecycleBinUnlocked::SESSION_KEY));
        $this->assertNull(session(EnsureRequestsUnlocked::SESSION_KEY));
    }
}

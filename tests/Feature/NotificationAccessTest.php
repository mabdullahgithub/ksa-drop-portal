<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The notifications endpoints are polled by the header bell on every page for
 * every role. They must never 403 for clients — the Shopify app reviewer
 * recorded exactly those 403s in the portal console and rejected the app for
 * "web errors" (policy 2.1.1).
 */
class NotificationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('client');
    }

    private function makeClientUser(): User
    {
        return $this->makeClientUserWithShortId('TST');
    }

    private function makeClientUserWithShortId(string $shortId): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        Client::create([
            'user_id'         => $user->id,
            'company_name'    => "Test Client {$shortId}",
            'short_id'        => $shortId,
            'client_types'    => ['fulfilment'],
            'portal_features' => ['orders'],
        ]);

        return $user;
    }

    /** Insert a database notification for the user; returns its id. */
    private function notify(User $user, string $message): string
    {
        $notification = $user->notifications()->create([
            'id'   => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\TestNotification',
            'data' => ['message' => $message],
        ]);

        return $notification->id;
    }

    public function test_client_can_fetch_notifications(): void
    {
        $this->actingAs($this->makeClientUser())
            ->getJson('/api/notifications?per_page=15')
            ->assertOk();
    }

    public function test_client_can_fetch_unread_count(): void
    {
        $this->actingAs($this->makeClientUser())
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJson(['count' => 0]);
    }

    public function test_client_can_open_notifications_page(): void
    {
        $this->actingAs($this->makeClientUser())
            ->get('/notifications')
            ->assertOk();
    }

    public function test_client_can_mark_all_notifications_read(): void
    {
        $this->actingAs($this->makeClientUser())
            ->postJson('/api/notifications/read-all')
            ->assertOk();
    }

    public function test_client_sees_only_their_own_notifications(): void
    {
        $mine   = $this->makeClientUser();
        $theirs = $this->makeClientUserWithShortId('OTH');

        $this->notify($mine, 'Your order shipped');
        $this->notify($theirs, 'Private message for another client');

        $response = $this->actingAs($mine)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame(
            'Your order shipped',
            $response->json('data.0.data.message')
        );

        $this->actingAs($mine)
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJson(['count' => 1]);
    }

    public function test_client_cannot_read_or_delete_another_users_notification(): void
    {
        $mine   = $this->makeClientUser();
        $theirs = $this->makeClientUserWithShortId('OTH');

        $foreign = $this->notify($theirs, 'Not yours');

        $this->actingAs($mine)
            ->postJson("/api/notifications/{$foreign}/read")
            ->assertNotFound();

        $this->actingAs($mine)
            ->deleteJson("/api/notifications/{$foreign}")
            ->assertNotFound();
    }

    public function test_non_client_user_still_has_access(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/notifications/unread-count')
            ->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
    }

    /**
     * A stale `impersonate.client_id` left in an admin's session (e.g. the
     * client was deleted after impersonation started) must not hard-fail the
     * request — it previously threw ModelNotFoundException, which Laravel
     * turns into an HTTP 404 for a request that otherwise looks completely
     * normal from the browser's side.
     */
    public function test_stale_impersonation_session_falls_back_instead_of_404ing(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)
            ->withSession([
                'impersonate.admin_id' => $admin->id,
                'impersonate.client_id' => 999999, // no such client
            ])
            ->getJson('/api/notifications/unread-count');

        $response->assertOk()->assertJson(['count' => 0]);
    }

    public function test_unread_count_is_shared_on_every_inertia_visit(): void
    {
        $user = $this->makeClientUser();
        $this->notify($user, 'Your order shipped');

        $this->actingAs($user)
            ->get('/notifications')
            ->assertInertia(fn ($page) => $page->where('unreadNotificationsCount', 1));
    }

    public function test_marking_read_invalidates_the_cached_count(): void
    {
        $user = $this->makeClientUser();
        $id = $this->notify($user, 'Your order shipped');

        $this->actingAs($user)
            ->getJson('/api/notifications/unread-count')
            ->assertJson(['count' => 1]);

        $this->actingAs($user)->postJson("/api/notifications/{$id}/read")->assertOk();

        // Without cache invalidation this would still read the stale value.
        $this->actingAs($user)
            ->getJson('/api/notifications/unread-count')
            ->assertJson(['count' => 0]);
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\ShopifyOrderSyncJob;
use App\Models\Client;
use App\Models\ClientShopifyConnection;
use App\Models\User;
use App\Services\ShopifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression coverage for the Shopify connect flows. There is no
 * portal-initiated OAuth handshake anymore (App Store review requirement
 * 2.3.1 forbids installation/authorization starting from a non-Shopify
 * surface) — every grant is exchanged exactly once in the callback, and a
 * KSA Drop client is attached either immediately (portal session present) or
 * later via the claim endpoint (no second OAuth). The callback must never
 * dead-end on an error page for any auth state.
 */
class ShopifyConnectFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SHOP   = 'mystore.myshopify.com';
    private const SECRET = 'test-shopify-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');

        config([
            'services.shopify.key'          => 'test-api-key',
            'services.shopify.secret'       => self::SECRET,
            'services.shopify.scopes'       => 'read_orders',
            'services.shopify.redirect_uri' => 'https://portal.test/shopify/callback',
        ]);
    }

    private function makeClientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        Client::create([
            'user_id'         => $user->id,
            'company_name'    => 'Test Client',
            'short_id'        => 'TST',
            'client_types'    => ['fulfilment'],
            'portal_features' => ['orders'],
        ]);

        return $user;
    }

    /**
     * Build a callback/app-URL query string signed the way Shopify signs it
     * (HMAC-SHA256 over the sorted raw pairs, excluding hmac itself).
     */
    private function signedQuery(array $params): string
    {
        ksort($params);

        $message = collect($params)->map(fn ($v, $k) => "{$k}={$v}")->implode('&');

        return $message . '&hmac=' . hash_hmac('sha256', $message, self::SECRET);
    }

    /**
     * Build a fake App Bridge session token (HS256 JWT) for $shop, signed the
     * way Shopify signs it, for exercising the claim-token endpoint.
     */
    private function makeSessionToken(string $shop): string
    {
        $header = $this->base64UrlJson(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload = $this->base64UrlJson([
            'iss'  => "https://{$shop}/admin",
            'dest' => "https://{$shop}",
            'aud'  => 'test-api-key',
            'sub'  => '1',
            'exp'  => time() + 60,
            'nbf'  => time() - 5,
            'iat'  => time() - 5,
            'jti'  => 'test-jti',
            'sid'  => 'test-sid',
        ]);

        $signature = rtrim(strtr(
            base64_encode(hash_hmac('sha256', "{$header}.{$payload}", self::SECRET, true)),
            '+/',
            '-_'
        ), '=');

        return "{$header}.{$payload}.{$signature}";
    }

    private function base64UrlJson(array $data): string
    {
        return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    }

    // ─── Callback with a portal session already active ───────────────────────

    public function test_callback_connects_store_for_logged_in_client(): void
    {
        Queue::fake();
        Http::fake([
            'https://' . self::SHOP . '/admin/oauth/access_token' => Http::response([
                'access_token'             => 'tok-123',
                'refresh_token'            => 'ref-123',
                'expires_in'               => 3600,
                'refresh_token_expires_in' => 7776000,
                'scope'                    => 'read_orders',
            ]),
            'https://' . self::SHOP . '/admin/api/*' => Http::response([
                'data' => ['webhookSubscriptionCreate' => [
                    'webhookSubscription' => ['id' => 'gid://shopify/WebhookSubscription/1'],
                    'userErrors'          => [],
                ]],
            ]),
        ]);

        $user  = $this->makeClientUser();
        $state = app(ShopifyService::class)->makeState(self::SHOP);

        $query = $this->signedQuery([
            'code'      => 'test-code',
            'shop'      => self::SHOP,
            'state'     => $state,
            'timestamp' => (string) time(),
        ]);

        $this->actingAs($user)
            ->get('/shopify/callback?' . $query)
            ->assertRedirect(route('portal.connectors'))
            ->assertSessionHas('success');

        $connection = ClientShopifyConnection::first();
        $this->assertNotNull($connection);
        $this->assertSame($user->client->id, $connection->client_id);
        $this->assertSame(self::SHOP, $connection->shop_domain);
        $this->assertSame('active', $connection->status);
        $this->assertSame('tok-123', $connection->access_token);

        Queue::assertPushed(ShopifyOrderSyncJob::class);
    }

    public function test_callback_with_invalid_state_returns_client_to_connectors(): void
    {
        $query = $this->signedQuery([
            'code'      => 'test-code',
            'shop'      => self::SHOP,
            'state'     => 'forged-state',
            'timestamp' => (string) time(),
        ]);

        $this->actingAs($this->makeClientUser())
            ->get('/shopify/callback?' . $query)
            ->assertRedirect(route('portal.connectors'))
            ->assertSessionHas('error');

        $this->assertSame(0, ClientShopifyConnection::count());
    }

    public function test_callback_without_login_for_unlinked_store_stores_token_and_lands_in_app_ui(): void
    {
        // Fresh App Store install, store not linked to any client yet: the
        // grant is exchanged and stored right away (client_id null) so it's
        // never thrown away, and the merchant lands in the embedded app,
        // whose onboarding screen guides account linking via claim().
        Http::fake([
            'https://' . self::SHOP . '/admin/oauth/access_token' => Http::response([
                'access_token'             => 'tok-unlinked',
                'refresh_token'            => 'ref-unlinked',
                'expires_in'               => 3600,
                'refresh_token_expires_in' => 7776000,
                'scope'                    => 'read_orders',
            ]),
            'https://' . self::SHOP . '/admin/api/*' => Http::response([
                'data' => ['webhookSubscriptionCreate' => [
                    'webhookSubscription' => ['id' => 'gid://shopify/WebhookSubscription/1'],
                    'userErrors'          => [],
                ]],
            ]),
        ]);

        $state = app(ShopifyService::class)->makeState(self::SHOP);

        $query = $this->signedQuery([
            'code'      => 'test-code',
            'shop'      => self::SHOP,
            'state'     => $state,
            'timestamp' => (string) time(),
        ]);

        $this->get('/shopify/callback?' . $query)
            ->assertRedirect('https://' . self::SHOP . '/admin/apps/test-api-key');

        $connection = ClientShopifyConnection::sole();
        $this->assertNull($connection->client_id);
        $this->assertSame('active', $connection->status);
        $this->assertSame('tok-unlinked', $connection->access_token);
    }

    public function test_callback_without_login_relinks_store_already_owned_by_a_client(): void
    {
        // Reinstall from the Shopify admin: the store already belongs to a
        // client, so the tokens refresh on the existing link — ownership never
        // changes — and the merchant lands back in the embedded app.
        Queue::fake();
        Http::fake([
            'https://' . self::SHOP . '/admin/oauth/access_token' => Http::response([
                'access_token'             => 'tok-new',
                'refresh_token'            => 'ref-new',
                'expires_in'               => 3600,
                'refresh_token_expires_in' => 7776000,
                'scope'                    => 'read_orders',
            ]),
            'https://' . self::SHOP . '/admin/api/*' => Http::response([
                'data' => ['webhookSubscriptionCreate' => [
                    'webhookSubscription' => ['id' => 'gid://shopify/WebhookSubscription/1'],
                    'userErrors'          => [],
                ]],
            ]),
        ]);

        $user = $this->makeClientUser();

        ClientShopifyConnection::create([
            'client_id'    => $user->client->id,
            'shop_domain'  => self::SHOP,
            'access_token' => 'tok-old',
            'status'       => 'disconnected',
            'connected_at' => now()->subMonth(),
        ]);

        $state = app(ShopifyService::class)->makeState(self::SHOP);

        $query = $this->signedQuery([
            'code'      => 'test-code',
            'shop'      => self::SHOP,
            'state'     => $state,
            'timestamp' => (string) time(),
        ]);

        $this->get('/shopify/callback?' . $query)
            ->assertRedirect('https://' . self::SHOP . '/admin/apps/test-api-key');

        $connection = ClientShopifyConnection::sole();
        $this->assertSame($user->client->id, $connection->client_id);
        $this->assertSame('active', $connection->status);
        $this->assertSame('tok-new', $connection->access_token);

        Queue::assertPushed(ShopifyOrderSyncJob::class);
    }

    public function test_callback_without_login_and_invalid_state_lands_on_login_page(): void
    {
        $query = $this->signedQuery([
            'code'      => 'test-code',
            'shop'      => self::SHOP,
            'state'     => 'forged-state',
            'timestamp' => (string) time(),
        ]);

        $this->get('/shopify/callback?' . $query)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertSame(0, ClientShopifyConnection::count());
    }

    // ─── Embedded app shell (existing connected-store behavior) ──────────────

    public function test_embedded_app_renders_shell_for_connected_store(): void
    {
        $user = $this->makeClientUser();

        ClientShopifyConnection::create([
            'client_id'    => $user->client->id,
            'shop_domain'  => self::SHOP,
            'access_token' => 'tok-123',
            'status'       => 'active',
            'connected_at' => now(),
        ]);

        $query = $this->signedQuery([
            'shop'      => self::SHOP,
            'embedded'  => '1',
            'timestamp' => (string) time(),
        ]);

        $this->get('/embedded/shopify?' . $query)
            ->assertOk()
            ->assertSee('embedded-root', false);
    }

    public function test_embedded_app_without_shopify_hmac_renders_shell(): void
    {
        $this->get('/embedded/shopify')
            ->assertOk()
            ->assertSee('embedded-root', false);
    }

    public function test_embedded_app_starts_oauth_for_uninstalled_store(): void
    {
        $query = $this->signedQuery([
            'shop'      => 'new-store.myshopify.com',
            'timestamp' => (string) time(),
        ]);

        $response = $this->get('/embedded/shopify?' . $query);

        $response->assertRedirect();
        $this->assertStringStartsWith(
            'https://new-store.myshopify.com/admin/oauth/authorize?',
            $response->headers->get('Location')
        );
    }

    public function test_embedded_iframe_load_never_starts_oauth(): void
    {
        // Inside the admin iframe the grant already happened on Shopify's
        // side — the shell must render (an unlinked store gets the in-app
        // onboarding screen), never bounce into OAuth. This is also what
        // makes a redirect loop structurally impossible.
        $query = $this->signedQuery([
            'shop'      => 'new-store.myshopify.com',
            'embedded'  => '1',
            'timestamp' => (string) time(),
        ]);

        $this->get('/embedded/shopify?' . $query)
            ->assertOk()
            ->assertSee('embedded-root', false);
    }

    // ─── Connection management endpoints (untouched, must keep working) ──────

    public function test_disconnect_still_works(): void
    {
        $user = $this->makeClientUser();

        ClientShopifyConnection::create([
            'client_id'    => $user->client->id,
            'shop_domain'  => self::SHOP,
            'access_token' => 'tok-123',
            'status'       => 'active',
            'connected_at' => now(),
        ]);

        $this->actingAs($user)
            ->deleteJson('/portal/api/shopify/disconnect')
            ->assertOk();

        $this->assertSame('disconnected', ClientShopifyConnection::first()->status);
    }

    public function test_disconnect_releases_the_store_so_it_can_be_connected_again(): void
    {
        // Disconnecting does not uninstall the app from Shopify. Leaving
        // client_id set meant the next embedded load re-ran token exchange and
        // silently relinked the store to the account that had just left, while
        // claim() — which only matches unlinked rows — could never find it
        // again, making a reconnect impossible.
        $user = $this->makeClientUser();

        ClientShopifyConnection::create([
            'client_id'    => $user->client->id,
            'shop_domain'  => self::SHOP,
            'access_token' => 'tok-123',
            'status'       => 'active',
            'sync_mode'    => 'manual_approval',
            'connected_at' => now(),
        ]);

        $this->actingAs($user)
            ->deleteJson('/portal/api/shopify/disconnect')
            ->assertOk();

        $connection = ClientShopifyConnection::sole();
        $this->assertNull($connection->client_id);
        $this->assertNull($connection->access_token);
        $this->assertSame('auto_sync', $connection->sync_mode);

        // Reopening the embedded app must not resurrect the old link.
        $this->fakeTokenExchange();

        $this->withHeader('Authorization', 'Bearer ' . $this->makeSessionToken(self::SHOP))
            ->getJson('/embedded/shopify/api/claim-token')
            ->assertOk()
            ->assertJson(['installed' => true, 'linked' => false]);

        $this->assertNull(ClientShopifyConnection::sole()->client_id);

        // And the merchant can claim it again from the portal.
        $mint = $this->withHeader('Authorization', 'Bearer ' . $this->makeSessionToken(self::SHOP))
            ->getJson('/embedded/shopify/api/claim-token')
            ->assertOk();

        Queue::fake();

        $this->actingAs($user)
            ->postJson('/portal/api/shopify/claim', [
                'shop'        => self::SHOP,
                'claim_token' => $mint->json('token'),
            ])
            ->assertOk();

        $this->assertSame($user->client->id, ClientShopifyConnection::sole()->client_id);
    }

    public function test_sync_mode_update_still_works(): void
    {
        $user = $this->makeClientUser();

        ClientShopifyConnection::create([
            'client_id'    => $user->client->id,
            'shop_domain'  => self::SHOP,
            'access_token' => 'tok-123',
            'status'       => 'active',
            'connected_at' => now(),
        ]);

        $this->actingAs($user)
            ->putJson('/portal/api/shopify/sync-mode', ['sync_mode' => 'manual_approval'])
            ->assertOk();

        $this->assertSame('manual_approval', ClientShopifyConnection::first()->sync_mode);
    }

    // ─── Claim (link an already-installed store — no OAuth) ──────────────────

    public function test_claim_links_pending_connection_to_client(): void
    {
        Queue::fake();

        ClientShopifyConnection::create([
            'client_id'    => null,
            'shop_domain'  => self::SHOP,
            'access_token' => 'tok-pending',
            'status'       => 'active',
            'connected_at' => now(),
        ]);

        $user = $this->makeClientUser();

        $this->actingAs($user)
            ->postJson('/portal/api/shopify/claim', [
                'shop'        => self::SHOP,
                'claim_token' => app(ShopifyService::class)->makeClaimToken(self::SHOP),
            ])
            ->assertOk();

        $connection = ClientShopifyConnection::sole();
        $this->assertSame($user->client->id, $connection->client_id);
        $this->assertSame('tok-pending', $connection->access_token);

        Queue::assertPushed(ShopifyOrderSyncJob::class);
    }

    public function test_claim_without_pending_connection_returns_404(): void
    {
        $this->actingAs($this->makeClientUser())
            ->postJson('/portal/api/shopify/claim', [
                'shop'        => self::SHOP,
                'claim_token' => app(ShopifyService::class)->makeClaimToken(self::SHOP),
            ])
            ->assertNotFound();

        $this->assertSame(0, ClientShopifyConnection::count());
    }

    public function test_claim_replaces_clients_other_existing_connection(): void
    {
        Queue::fake();

        $user = $this->makeClientUser();

        ClientShopifyConnection::create([
            'client_id'    => $user->client->id,
            'shop_domain'  => 'old-store.myshopify.com',
            'access_token' => 'tok-old',
            'status'       => 'active',
            'connected_at' => now()->subDay(),
        ]);

        ClientShopifyConnection::create([
            'client_id'    => null,
            'shop_domain'  => self::SHOP,
            'access_token' => 'tok-new',
            'status'       => 'active',
            'connected_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson('/portal/api/shopify/claim', [
                'shop'        => self::SHOP,
                'claim_token' => app(ShopifyService::class)->makeClaimToken(self::SHOP),
            ])
            ->assertOk();

        $connection = ClientShopifyConnection::sole();
        $this->assertSame(self::SHOP, $connection->shop_domain);
        $this->assertSame($user->client->id, $connection->client_id);
    }

    public function test_claim_rejects_missing_claim_token(): void
    {
        ClientShopifyConnection::create([
            'client_id'    => null,
            'shop_domain'  => self::SHOP,
            'access_token' => 'tok-pending',
            'status'       => 'active',
            'connected_at' => now(),
        ]);

        $this->actingAs($this->makeClientUser())
            ->postJson('/portal/api/shopify/claim', ['shop' => self::SHOP])
            ->assertStatus(422);

        $this->assertNull(ClientShopifyConnection::sole()->client_id);
    }

    /**
     * The vulnerability this token closes: knowing (or guessing) another
     * store's domain must not be enough to link it — without a token minted
     * from inside that store's own Shopify Admin session, the claim has to
     * be refused even though a pending connection genuinely exists.
     */
    public function test_claim_rejects_a_forged_claim_token(): void
    {
        ClientShopifyConnection::create([
            'client_id'    => null,
            'shop_domain'  => self::SHOP,
            'access_token' => 'tok-pending',
            'status'       => 'active',
            'connected_at' => now(),
        ]);

        $this->actingAs($this->makeClientUser())
            ->postJson('/portal/api/shopify/claim', [
                'shop'        => self::SHOP,
                'claim_token' => 'not-a-real-token',
            ])
            ->assertStatus(422);

        $this->assertNull(ClientShopifyConnection::sole()->client_id);
    }

    public function test_claim_rejects_a_claim_token_minted_for_a_different_shop(): void
    {
        ClientShopifyConnection::create([
            'client_id'    => null,
            'shop_domain'  => self::SHOP,
            'access_token' => 'tok-pending',
            'status'       => 'active',
            'connected_at' => now(),
        ]);

        $tokenForAnotherShop = app(ShopifyService::class)->makeClaimToken('other-store.myshopify.com');

        $this->actingAs($this->makeClientUser())
            ->postJson('/portal/api/shopify/claim', [
                'shop'        => self::SHOP,
                'claim_token' => $tokenForAnotherShop,
            ])
            ->assertStatus(422);

        $this->assertNull(ClientShopifyConnection::sole()->client_id);
    }

    // ─── Claim-token endpoint (embedded, mints the token above) ──────────────

    public function test_claim_token_endpoint_mints_a_token_for_a_valid_session_token(): void
    {
        $this->fakeTokenExchange();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->makeSessionToken(self::SHOP))
            ->getJson('/embedded/shopify/api/claim-token')
            ->assertOk();

        $response->assertJson(['shop' => self::SHOP, 'linked' => false]);

        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->assertTrue(app(ShopifyService::class)->verifyClaimToken($token, self::SHOP));
    }

    public function test_claim_token_endpoint_reports_linked_for_a_connected_store(): void
    {
        $user = $this->makeClientUser();

        ClientShopifyConnection::create([
            'client_id'    => $user->client->id,
            'shop_domain'  => self::SHOP,
            'access_token' => 'tok-123',
            'status'       => 'active',
            'connected_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->makeSessionToken(self::SHOP))
            ->getJson('/embedded/shopify/api/claim-token')
            ->assertOk()
            ->assertJson(['shop' => self::SHOP, 'linked' => true]);
    }

    public function test_claim_token_endpoint_reports_unlinked_for_a_pending_store(): void
    {
        // Installed but not yet claimed by a client — must not report linked.
        ClientShopifyConnection::create([
            'client_id'    => null,
            'shop_domain'  => self::SHOP,
            'access_token' => 'tok-pending',
            'status'       => 'active',
            'connected_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->makeSessionToken(self::SHOP))
            ->getJson('/embedded/shopify/api/claim-token')
            ->assertOk()
            ->assertJson(['linked' => false]);
    }

    public function test_claim_token_endpoint_rejects_missing_session_token(): void
    {
        $this->getJson('/embedded/shopify/api/claim-token')->assertStatus(401);
    }

    // ─── Managed installation (token exchange) ───────────────────────────────

    public function test_first_embedded_load_installs_the_store_via_token_exchange(): void
    {
        // Managed installation never calls the OAuth redirect_uri, so the first
        // authenticated call the embedded app makes is where the grant has to be
        // turned into an API token. Without it the store has no connection row
        // and claim() has nothing to attach the client account to.
        $this->fakeTokenExchange();

        $sessionToken = $this->makeSessionToken(self::SHOP);

        $this->withHeader('Authorization', 'Bearer ' . $sessionToken)
            ->getJson('/embedded/shopify/api/claim-token')
            ->assertOk()
            ->assertJson(['shop' => self::SHOP, 'linked' => false]);

        $connection = ClientShopifyConnection::sole();
        $this->assertNull($connection->client_id);
        $this->assertSame('active', $connection->status);
        $this->assertSame('tok-exchanged', $connection->access_token);
        // Exchanged offline tokens never expire and carry no refresh token.
        $this->assertNull($connection->token_expires_at);
        $this->assertNull($connection->refresh_token);
        $this->assertTrue($connection->webhooks_registered);

        // Shopify answers a wrong URN with a bare {"error":"invalid_request"}
        // that names nothing, so pin the exact values. Note grant_type is
        // "grant-type", while subject_token_type is "token-type".
        Http::assertSent(fn ($request) => $request->url() === 'https://' . self::SHOP . '/admin/oauth/access_token'
            && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:token-exchange'
            && $request['subject_token_type'] === 'urn:ietf:params:oauth:token-type:id_token'
            && $request['requested_token_type'] === 'urn:shopify:params:oauth:token-type:offline-access-token'
            && $request['subject_token'] === $sessionToken);
    }

    public function test_store_installed_via_token_exchange_can_then_be_claimed(): void
    {
        // End-to-end for the reported 404: install (token exchange) then link.
        $this->fakeTokenExchange();
        Queue::fake();

        $mint = $this->withHeader('Authorization', 'Bearer ' . $this->makeSessionToken(self::SHOP))
            ->getJson('/embedded/shopify/api/claim-token')
            ->assertOk();

        $user = $this->makeClientUser();

        $this->actingAs($user)
            ->postJson('/portal/api/shopify/claim', [
                'shop'        => self::SHOP,
                'claim_token' => $mint->json('token'),
            ])
            ->assertOk();

        $this->assertSame($user->client->id, ClientShopifyConnection::sole()->client_id);
    }

    public function test_embedded_load_leaves_an_already_installed_store_alone(): void
    {
        $user = $this->makeClientUser();

        ClientShopifyConnection::create([
            'client_id'           => $user->client->id,
            'shop_domain'         => self::SHOP,
            'access_token'        => 'tok-existing',
            'status'              => 'active',
            'webhooks_registered' => true,
            'connected_at'        => now(),
        ]);

        Http::fake();

        $this->withHeader('Authorization', 'Bearer ' . $this->makeSessionToken(self::SHOP))
            ->getJson('/embedded/shopify/api/claim-token')
            ->assertOk()
            ->assertJson(['linked' => true]);

        Http::assertNothingSent();
        $this->assertSame('tok-existing', ClientShopifyConnection::sole()->access_token);
    }

    public function test_reinstall_refreshes_the_token_without_reassigning_ownership(): void
    {
        // Uninstall marks the connection disconnected but keeps the link. The
        // merchant reinstalling must get a working token back on the same row.
        $user = $this->makeClientUser();

        ClientShopifyConnection::create([
            'client_id'    => $user->client->id,
            'shop_domain'  => self::SHOP,
            'access_token' => 'tok-stale',
            'status'       => 'disconnected',
            'connected_at' => now()->subMonth(),
        ]);

        $this->fakeTokenExchange();

        $this->withHeader('Authorization', 'Bearer ' . $this->makeSessionToken(self::SHOP))
            ->getJson('/embedded/shopify/api/claim-token')
            ->assertOk()
            ->assertJson(['linked' => true]);

        $connection = ClientShopifyConnection::sole();
        $this->assertSame($user->client->id, $connection->client_id);
        $this->assertSame('active', $connection->status);
        $this->assertSame('tok-exchanged', $connection->access_token);
    }

    public function test_failed_token_exchange_reports_not_installed_and_mints_no_claim_token(): void
    {
        // A claim token here would send the merchant to the portal for a claim
        // that must 404 — there is no stored grant to attach an account to.
        Http::fake([
            'https://' . self::SHOP . '/admin/oauth/access_token' => Http::response(
                ['error' => 'invalid_subject_token'],
                400
            ),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->makeSessionToken(self::SHOP))
            ->getJson('/embedded/shopify/api/claim-token')
            ->assertOk()
            ->assertJson(['installed' => false, 'linked' => false, 'token' => null]);

        $this->assertSame(0, ClientShopifyConnection::count());
    }

    /**
     * Fake the token-exchange call plus the webhook registration that follows it.
     */
    private function fakeTokenExchange(): void
    {
        Http::fake([
            'https://' . self::SHOP . '/admin/oauth/access_token' => Http::response([
                'access_token' => 'tok-exchanged',
                'scope'        => 'read_orders',
            ]),
            'https://' . self::SHOP . '/admin/api/*' => Http::response([
                'data' => ['webhookSubscriptionCreate' => [
                    'webhookSubscription' => ['id' => 'gid://shopify/WebhookSubscription/1'],
                    'userErrors'          => [],
                ]],
            ]),
        ]);
    }
}

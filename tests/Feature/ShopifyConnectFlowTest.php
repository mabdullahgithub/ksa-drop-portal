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
 * Regression coverage for the Shopify connect flows: the portal-initiated
 * OAuth handshake must keep working exactly as before, and the callback must
 * never dead-end on an error page for any auth state.
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

    // ─── Portal-initiated connect (existing client flow) ─────────────────────

    public function test_portal_connect_redirects_to_shopify_authorize(): void
    {
        $response = $this->actingAs($this->makeClientUser())
            ->get('/portal/shopify/connect?shop=mystore');

        $response->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://mystore.myshopify.com/admin/oauth/authorize?', $location);
        $this->assertStringContainsString('client_id=test-api-key', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertTrue(app(ShopifyService::class)->verifyState($query['state'], self::SHOP));
    }

    public function test_portal_connect_rejects_invalid_domain_with_flash_error(): void
    {
        $this->actingAs($this->makeClientUser())
            ->get('/portal/shopify/connect?shop=my_bad_store!')
            ->assertRedirect(route('portal.connectors'))
            ->assertSessionHas('error');
    }

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

    public function test_callback_without_login_for_unlinked_store_lands_in_app_ui(): void
    {
        // Fresh App Store install, store not linked to any client: no row is
        // created (client_id is always required) and the merchant lands back
        // in the embedded app, whose onboarding screen guides account linking.
        $state = app(ShopifyService::class)->makeState(self::SHOP);

        $query = $this->signedQuery([
            'code'      => 'test-code',
            'shop'      => self::SHOP,
            'state'     => $state,
            'timestamp' => (string) time(),
        ]);

        $this->get('/shopify/callback?' . $query)
            ->assertRedirect('https://' . self::SHOP . '/admin/apps/test-api-key');

        $this->assertSame(0, ClientShopifyConnection::count());
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
}

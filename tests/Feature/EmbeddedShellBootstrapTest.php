<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientShopifyConnection;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The embedded shell inlines this store's payload on the initial load so the
 * first paint doesn't wait on a round trip (an LCP fix). That shortcut reads
 * the `id_token` Shopify puts on the app URL, so it needs the same scrutiny as
 * any other authenticated path: these tests pin down that a payload is emitted
 * only for a verified token belonging to a linked store, and that every other
 * case falls back to the fetch path the app has always used.
 */
class EmbeddedShellBootstrapTest extends TestCase
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

    /** A connection that is both installed and linked to a KSA Drop client. */
    private function makeLinkedConnection(): ClientShopifyConnection
    {
        $user = $this->makeClientUser();

        return ClientShopifyConnection::create([
            'client_id'    => $user->client->id,
            'shop_domain'  => self::SHOP,
            'access_token' => 'tok-123',
            'status'       => 'active',
            'connected_at' => now(),
        ]);
    }

    /** Session token (HS256 JWT) signed the way Shopify signs it. */
    private function makeSessionToken(string $shop, ?string $secret = null): string
    {
        $header  = $this->base64UrlJson(['alg' => 'HS256', 'typ' => 'JWT']);
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

        $signature = rtrim(strtr(base64_encode(
            hash_hmac('sha256', "{$header}.{$payload}", $secret ?? self::SECRET, true)
        ), '+/', '-_'), '=');

        return "{$header}.{$payload}.{$signature}";
    }

    private function base64UrlJson(array $data): string
    {
        return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    }

    /** The decoded payload the shell inlined, or null when it inlined none. */
    private function inlinedPayload(string $html): ?array
    {
        preg_match('/<script id="embedded-bootstrap" type="application\\/json">(.*?)<\\/script>/s', $html, $m);

        return $m ? json_decode($m[1], true) : null;
    }

    // ─── The fast path ───────────────────────────────────────────────────────

    public function test_shell_inlines_dashboard_payload_for_verified_token_on_linked_store(): void
    {
        $this->makeLinkedConnection();

        $response = $this->get('/embedded/shopify?embedded=1&shop=' . self::SHOP
            . '&id_token=' . $this->makeSessionToken(self::SHOP));

        $response->assertOk()->assertSee('data-linked="1"', false);

        $payload = $this->inlinedPayload($response->getContent());

        $this->assertNotNull($payload);
        $this->assertArrayHasKey('dashboard', $payload);
        $this->assertSame(self::SHOP, $payload['dashboard']['shop_domain']);

        // Merchant order data must never sit in a shared cache.
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_settings_route_inlines_settings_payload_not_dashboard(): void
    {
        $this->makeLinkedConnection();

        $response = $this->get('/embedded/shopify/settings?embedded=1&shop=' . self::SHOP
            . '&id_token=' . $this->makeSessionToken(self::SHOP))->assertOk();

        $payload = $this->inlinedPayload($response->getContent());

        $this->assertNotNull($payload);
        $this->assertArrayHasKey('settings', $payload);
        $this->assertArrayNotHasKey('dashboard', $payload);
        $this->assertArrayHasKey('sync_filters', $payload['settings']);
    }

    public function test_inlined_payload_matches_what_the_api_endpoint_returns(): void
    {
        $this->makeLinkedConnection();

        $token = $this->makeSessionToken(self::SHOP);

        $api = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/embedded/shopify/api/dashboard')
            ->assertOk()
            ->json();

        $html = $this->get('/embedded/shopify?embedded=1&shop=' . self::SHOP . '&id_token=' . $token)
            ->assertOk()
            ->getContent();

        $payload = $this->inlinedPayload($html);

        $this->assertNotNull($payload, 'shell did not inline a bootstrap payload');
        $this->assertSame($api, $payload['dashboard']);
    }

    public function test_order_data_cannot_break_out_of_the_inlined_script_tag(): void
    {
        $connection = $this->makeLinkedConnection();

        Order::create([
            'client_id'     => $connection->client_id,
            'order_number'  => 'TST9001',
            'source'        => 'shopify',
            'customer_name' => '</script><script>alert(1)</script>',
            'currency'      => 'SAR',
            'total'         => '10.00',
        ]);

        $html = $this->get('/embedded/shopify?embedded=1&shop=' . self::SHOP
            . '&id_token=' . $this->makeSessionToken(self::SHOP))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);

        // The value still round-trips intact — it is escaped, not mangled.
        $payload = $this->inlinedPayload($html);
        $this->assertSame(
            '</script><script>alert(1)</script>',
            $payload['dashboard']['recent_orders'][0]['customer_name']
        );
    }

    // ─── Every fallback: the shell must emit nothing and the app fetches ─────

    public function test_no_payload_without_an_id_token(): void
    {
        $this->makeLinkedConnection();

        $this->get('/embedded/shopify?embedded=1&shop=' . self::SHOP)
            ->assertOk()
            ->assertSee('embedded-root', false)
            ->assertDontSee('embedded-bootstrap', false);
    }

    public function test_no_payload_for_a_token_signed_with_the_wrong_secret(): void
    {
        $this->makeLinkedConnection();

        $this->get('/embedded/shopify?embedded=1&shop=' . self::SHOP
            . '&id_token=' . $this->makeSessionToken(self::SHOP, 'not-the-app-secret'))
            ->assertOk()
            ->assertDontSee('embedded-bootstrap', false);
    }

    public function test_no_payload_when_the_token_names_a_different_shop_than_the_query(): void
    {
        // The unsigned ?shop= param is not what decides whose data this is.
        // Rewriting it must drop the shortcut, not retarget it.
        $this->makeLinkedConnection();

        $this->get('/embedded/shopify?embedded=1&shop=' . self::SHOP
            . '&id_token=' . $this->makeSessionToken('attacker.myshopify.com'))
            ->assertOk()
            ->assertDontSee('embedded-bootstrap', false);
    }

    public function test_no_payload_and_no_linked_hint_for_an_installed_but_unlinked_store(): void
    {
        // Installed, but not yet claimed by a KSA Drop account. The hint must
        // read 0 so the app takes its original sequential path and never
        // provokes a 401 on the client-gated endpoint.
        ClientShopifyConnection::create([
            'client_id'    => null,
            'shop_domain'  => self::SHOP,
            'access_token' => 'tok-123',
            'status'       => 'active',
            'connected_at' => now(),
        ]);

        $this->get('/embedded/shopify?embedded=1&shop=' . self::SHOP
            . '&id_token=' . $this->makeSessionToken(self::SHOP))
            ->assertOk()
            ->assertSee('data-linked="0"', false)
            ->assertDontSee('embedded-bootstrap', false);
    }

    public function test_no_payload_for_a_disconnected_store(): void
    {
        $connection = $this->makeLinkedConnection();
        $connection->update(['status' => 'disconnected']);

        $this->get('/embedded/shopify?embedded=1&shop=' . self::SHOP
            . '&id_token=' . $this->makeSessionToken(self::SHOP))
            ->assertOk()
            ->assertSee('data-linked="0"', false)
            ->assertDontSee('embedded-bootstrap', false);
    }

    public function test_shell_still_renders_for_an_unknown_store(): void
    {
        $this->get('/embedded/shopify?embedded=1&shop=' . self::SHOP
            . '&id_token=' . $this->makeSessionToken(self::SHOP))
            ->assertOk()
            ->assertSee('embedded-root', false)
            ->assertDontSee('embedded-bootstrap', false);
    }

    // ─── The static skeleton the shell paints before the bundle runs ─────────

    public function test_shell_paints_a_route_specific_skeleton(): void
    {
        $this->get('/embedded/shopify?embedded=1&shop=' . self::SHOP)
            ->assertOk()
            ->assertSee('Order Sync')
            ->assertSee('Total orders synced');

        $this->get('/embedded/shopify/settings?embedded=1&shop=' . self::SHOP)
            ->assertOk()
            ->assertSee('Sync Settings')
            ->assertDontSee('Total orders synced');
    }
}

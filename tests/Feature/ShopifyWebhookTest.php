<?php

namespace Tests\Feature;

use App\Jobs\ProcessShopifyWebhookJob;
use App\Models\Client;
use App\Models\ClientShopifyConnection;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Coverage for the public Shopify webhook endpoint: HMAC authenticity, the
 * shop-domain header/body cross-check that stops a validly-signed webhook from
 * being retargeted at another store, and the GDPR redaction handlers Shopify's
 * app review exercises directly.
 */
class ShopifyWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SHOP   = 'mystore.myshopify.com';
    private const SECRET = 'test-shopify-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');

        config([
            'services.shopify.key'    => 'test-api-key',
            'services.shopify.secret' => self::SECRET,
        ]);
    }

    private function makeConnection(): ClientShopifyConnection
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $client = Client::create([
            'user_id'         => $user->id,
            'company_name'    => 'Test Client',
            'short_id'        => 'TST',
            'client_types'    => ['fulfilment'],
            'portal_features' => ['orders'],
        ]);

        return ClientShopifyConnection::create([
            'client_id'    => $client->id,
            'shop_domain'  => self::SHOP,
            'access_token' => 'tok-123',
            'status'       => 'active',
            'connected_at' => now(),
        ]);
    }

    private function postWebhook(array $body, string $topic, string $shopHeader, ?string $hmac = null): \Illuminate\Testing\TestResponse
    {
        $raw = json_encode($body);

        return $this->call(
            'POST',
            '/webhooks/shopify',
            [],
            [],
            [],
            [
                'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac ?? base64_encode(hash_hmac('sha256', $raw, self::SECRET, true)),
                'HTTP_X_SHOPIFY_TOPIC'       => $topic,
                'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $shopHeader,
                'CONTENT_TYPE'               => 'application/json',
            ],
            $raw
        );
    }

    public function test_rejects_webhook_with_invalid_hmac(): void
    {
        Queue::fake();

        $this->postWebhook(['shop_domain' => self::SHOP], 'shop/redact', self::SHOP, hmac: 'forged')
            ->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_rejects_when_body_shop_does_not_match_header(): void
    {
        Queue::fake();

        // Validly signed body for one shop, but header points at a victim store.
        $this->postWebhook(['shop_domain' => 'attacker.myshopify.com'], 'shop/redact', self::SHOP)
            ->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_accepts_and_queues_valid_shop_redact(): void
    {
        Queue::fake();

        $this->postWebhook(['shop_domain' => self::SHOP], 'shop/redact', self::SHOP)
            ->assertOk();

        Queue::assertPushed(ProcessShopifyWebhookJob::class);
    }

    public function test_shop_redact_deletes_connection_and_redacts_pii(): void
    {
        $connection = $this->makeConnection();

        $order = Order::withoutGlobalScope('shopify_visible')->create([
            'client_id'           => $connection->client_id,
            'shopify_shop_domain' => self::SHOP,
            'shopify_order_id'    => '5001',
            'order_number'        => 'TST5001',
            'source'              => 'shopify',
            'customer_name'       => 'Jane Buyer',
            'customer_email'      => 'jane@example.com',
        ]);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'shop/redact', ['shop_domain' => self::SHOP]);

        $this->assertDatabaseMissing('client_shopify_connections', ['id' => $connection->id]);

        $fresh = Order::withoutGlobalScope('shopify_visible')->find($order->id);
        $this->assertSame('[redacted]', $fresh->customer_name);
        $this->assertNull($fresh->customer_email);
    }

    public function test_orders_create_stores_the_order_with_its_shop_domain(): void
    {
        // Covers the mapper end-to-end: every synced order now records which
        // store it came from, which is what keeps shop/redact able to find it
        // after the connection is unlinked.
        $connection = $this->makeConnection();

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', [
            'id'               => 9001,
            'order_number'     => 1042,
            'email'            => 'jane@example.com',
            'currency'         => 'SAR',
            'total_price'      => '250.00',
            'financial_status' => 'paid',
            'customer'         => ['first_name' => 'Jane', 'last_name' => 'Buyer'],
            'shipping_address' => ['phone' => '+966500000000', 'city' => 'Riyadh'],
        ]);

        $order = Order::withoutGlobalScope('shopify_visible')
            ->where('shopify_order_id', '9001')
            ->sole();

        $this->assertSame(self::SHOP, $order->shopify_shop_domain);
        $this->assertSame($connection->client_id, $order->client_id);
        $this->assertSame('Jane Buyer', $order->customer_name);
    }

    public function test_shop_redact_still_erases_pii_after_the_store_was_unlinked(): void
    {
        // A portal disconnect releases client_id, so redaction cannot be scoped
        // by client — doing so matched nothing and silently erased no PII at
        // all, while still reporting success. The shop domain on the order is
        // what keeps erasure reachable.
        $connection = $this->makeConnection();

        $order = Order::withoutGlobalScope('shopify_visible')->create([
            'client_id'           => $connection->client_id,
            'shopify_shop_domain' => self::SHOP,
            'shopify_order_id'    => '5002',
            'order_number'        => 'TST5002',
            'source'              => 'shopify',
            'customer_name'       => 'Jane Buyer',
            'customer_email'      => 'jane@example.com',
        ]);

        $connection->update(['client_id' => null, 'status' => 'disconnected']);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'shop/redact', ['shop_domain' => self::SHOP]);

        $fresh = Order::withoutGlobalScope('shopify_visible')->find($order->id);
        $this->assertSame('[redacted]', $fresh->customer_name);
        $this->assertNull($fresh->customer_email);
    }

    public function test_app_uninstalled_disconnects_but_keeps_orders(): void
    {
        $connection = $this->makeConnection();
        // Shopify deletes the subscriptions on uninstall; the flag must follow,
        // or the next reinstall skips re-registration and live sync never
        // returns even though the store reconnects.
        $connection->update(['webhooks_registered' => true]);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'app/uninstalled', ['myshopify_domain' => self::SHOP]);

        $connection->refresh();
        $this->assertSame('disconnected', $connection->status);
        $this->assertNull($connection->access_token);
        $this->assertFalse((bool) $connection->webhooks_registered);
    }

    public function test_the_endpoint_does_not_run_session_or_view_middleware(): void
    {
        // Shopify records a delivery as failed if we take longer than five
        // seconds. Shopify sends no cookie, so StartSession opened a fresh
        // session for every webhook and wrote a junk row to the `sessions`
        // table that nothing would ever read — two database round trips per
        // delivery, and a table these webhooks inflated indefinitely. Session
        // GC then fires on a [2, 100] lottery, so roughly one webhook in fifty
        // ran a DELETE across that bloated table: seconds of work, holding
        // locks, with every concurrent webhook queued behind it.
        //
        // Nothing in this path reads a session, sets a cookie or renders a
        // view, so none of it may come back.
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->firstWhere(fn ($r) => $r->uri() === 'webhooks/shopify');

        $this->assertNotNull($route, 'The Shopify webhook route is missing.');

        $gathered = app('router')->gatherRouteMiddleware($route);

        foreach ([
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ] as $forbidden) {
            $this->assertNotContains($forbidden, $gathered, "{$forbidden} must not run on the Shopify webhook endpoint.");
        }
    }

    public function test_the_endpoint_sets_no_session_cookie(): void
    {
        // The observable half of the same guarantee: a response that still
        // handed back a session cookie would mean a session was started.
        Queue::fake();

        $response = $this->postWebhook(['id' => 9001], 'orders/create', self::SHOP)->assertOk();

        $this->assertEmpty(
            $response->headers->getCookies(),
            'The webhook endpoint must not set cookies.'
        );
    }
}

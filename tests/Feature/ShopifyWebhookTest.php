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

    private function orderPayload(): array
    {
        return [
            'id'               => 9001,
            'order_number'     => 1042,
            'email'            => 'jane@example.com',
            'currency'         => 'SAR',
            'total_price'      => '250.00',
            'financial_status' => 'paid',
            'customer'         => ['first_name' => 'Jane', 'last_name' => 'Buyer'],
            'shipping_address' => ['phone' => '+966500000000', 'city' => 'Riyadh'],
            'line_items'       => [['name' => 'Widget', 'quantity' => 1, 'price' => '250.00']],
        ];
    }

    // ── Workflow tags ────────────────────────────────────────────────────────

    public function test_a_synced_order_starts_on_the_pending_tag(): void
    {
        // Every order enters the portal as Pending whatever its source; CSV
        // import and manual creation already did this, Shopify orders arrived
        // with no tag at all and sat outside the workflow.
        $connection = $this->makeConnection();

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $this->orderPayload());

        $this->assertSame(['Pending'], Order::withoutGlobalScope('shopify_visible')->sole()->tags);

        // The tag row itself has to exist or the portal cannot colour or filter it.
        $this->assertDatabaseHas('tags', ['name' => 'Pending']);
    }

    public function test_it_reuses_the_orders_own_spelling_of_the_tag(): void
    {
        // Adding "Pending" next to a lowercase "pending" would show the merchant
        // two tags for one state.
        $connection = $this->makeConnection();

        $payload         = $this->orderPayload();
        $payload['tags'] = 'pending, buyease-x';

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $payload);

        $tags = Order::withoutGlobalScope('shopify_visible')->sole()->tags;

        $this->assertContains('pending', $tags);
        $this->assertNotContains('Pending', $tags);
        $this->assertContains('buyease-x', $tags);
    }

    public function test_a_later_sync_does_not_drag_the_order_back_to_pending(): void
    {
        // Tags are the portal's workflow state once an order is in the list. The
        // mapper always produces the starting set, so writing it through on
        // every orders/updated would undo the operator's work — and before
        // Pending existed it emptied the tags outright.
        $connection = $this->makeConnection();

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $this->orderPayload());

        Order::withoutGlobalScope('shopify_visible')->sole()->update(['tags' => ['Confirmed']]);

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/updated', $this->orderPayload());

        $this->assertSame(['Confirmed'], Order::withoutGlobalScope('shopify_visible')->sole()->tags);
    }

    public function test_the_workflow_tag_does_not_leak_into_the_merchants_own_tags(): void
    {
        // shopify_raw_tags keeps the merchant's list untouched, and that is what
        // evaluateSyncFilters reads. Adding Pending to `tags` must not reach it,
        // or a tags_exclude rule could start matching a tag we invented.
        $connection = $this->makeConnection();
        $connection->update(['sync_filters' => ['tags_include' => ['vip']]]);

        $payload         = $this->orderPayload();
        $payload['tags'] = 'wholesale, vip';

        ProcessShopifyWebhookJob::dispatchSync(self::SHOP, 'orders/create', $payload);

        $order = Order::withoutGlobalScope('shopify_visible')->sole();

        // Passed the filter on its own tags, so it syncs normally...
        $this->assertNull($order->shopify_sync_status);
        // ...with the merchant's list intact and the workflow tag kept separate.
        $this->assertSame(['wholesale', 'vip'], $order->shopify_raw_tags);
        $this->assertSame(['Pending'], $order->tags);
    }
}

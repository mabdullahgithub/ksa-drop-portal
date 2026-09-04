<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientShopifyConnection;
use App\Models\Order;
use App\Models\ShopifySyncFailure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Coverage for the recovery paths that handle webhooks Shopify never delivered
 * to us at all — the case the dead-letter queue is blind to, because nothing
 * reaches the app to be parked.
 *
 * Shopify gives a failed delivery 8 attempts over 4 hours and then drops it, and
 * removes the subscription entirely after repeated failures in a 24-hour period.
 * The reconciliation poll recovers the orders; the webhook verifier restores the
 * subscription so new orders start arriving again.
 */
class ShopifyReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private const SHOP = 'mystore.myshopify.com';

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');

        config([
            'services.shopify.key'    => 'test-api-key',
            'services.shopify.secret' => 'test-shopify-secret',
            'app.url'                 => 'https://portal.test',
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
            'client_id'        => $client->id,
            'shop_domain'      => self::SHOP,
            'access_token'     => 'tok-123',
            'refresh_token'    => 'ref-123',
            // getValidToken() refuses a token with no expiry (legacy
            // non-expiring grant), so every fixture needs a live one.
            'token_expires_at' => now()->addHour(),
            'status'           => 'active',
            'connected_at'     => now(),
        ]);
    }

    /**
     * A GraphQL order node in the shape fetchOrdersSince() asks for.
     */
    private function orderNode(int $id = 9001, int $number = 1042): array
    {
        return [
            'id'                       => "gid://shopify/Order/{$id}",
            'name'                     => "#{$number}",
            'email'                    => 'jane@example.com',
            'phone'                    => null,
            'createdAt'                => now()->subHour()->toIso8601String(),
            'processedAt'              => now()->subHour()->toIso8601String(),
            'cancelledAt'              => null,
            'note'                     => null,
            'tags'                     => [],
            'currencyCode'             => 'SAR',
            'paymentGatewayNames'      => ['cash_on_delivery'],
            'displayFinancialStatus'   => 'PAID',
            'displayFulfillmentStatus' => 'UNFULFILLED',
            'subtotalPriceSet'         => ['shopMoney' => ['amount' => '250.00']],
            'totalPriceSet'            => ['shopMoney' => ['amount' => '250.00']],
            'totalTaxSet'              => ['shopMoney' => ['amount' => '0.00']],
            'totalShippingPriceSet'    => ['shopMoney' => ['amount' => '0.00']],
            'discountCodes'            => [],
            'customer'                 => ['firstName' => 'Jane', 'lastName' => 'Buyer', 'email' => 'jane@example.com', 'phone' => null],
            'billingAddress'           => null,
            'shippingAddress'          => ['firstName' => 'Jane', 'lastName' => 'Buyer', 'city' => 'Riyadh', 'phone' => '+966500000000', 'countryCodeV2' => 'SA'],
        ];
    }

    /**
     * The separate per-order line-item response. Reconciliation fetches these
     * only for orders it decides to import, so they never appear in the listing.
     */
    private function lineItemsResponse(): array
    {
        return ['edges' => [
            ['node' => [
                'name'                 => 'Widget',
                'quantity'             => 2,
                'sku'                  => 'W-1',
                'requiresShipping'     => true,
                'taxable'              => false,
                'variantTitle'         => null,
                'originalUnitPriceSet' => ['shopMoney' => ['amount' => '125.00']],
            ]],
        ]];
    }

    /**
     * Fake the orders query with one or more pages.
     *
     * @param  array<int,array{orders: array, next: string|null}>  $pages
     */
    private function fakeOrderPages(array $pages): void
    {
        $call = 0;

        Http::fake([
            'https://' . self::SHOP . '/admin/api/*' => function (Request $request) use ($pages, &$call) {
                // Line items come from their own query, issued per imported order.
                if (str_contains($request->body(), 'orderLineItems')) {
                    return Http::response(['data' => ['order' => ['lineItems' => $this->lineItemsResponse()]]]);
                }

                $page = $pages[min($call, count($pages) - 1)];
                $call++;

                return Http::response(['data' => ['orders' => [
                    'pageInfo' => [
                        'hasNextPage' => $page['next'] !== null,
                        'endCursor'   => $page['next'],
                    ],
                    'edges' => array_map(fn ($node) => ['node' => $node], $page['orders']),
                ]]]);
            },
        ]);
    }

    // ── Reconciliation ───────────────────────────────────────────────────────

    public function test_it_imports_an_order_that_was_never_delivered_by_webhook(): void
    {
        $connection = $this->makeConnection();

        $this->fakeOrderPages([['orders' => [$this->orderNode()], 'next' => null]]);

        $this->artisan('shopify:reconcile-orders')->assertSuccessful();

        $order = Order::withoutGlobalScope('shopify_visible')->sole();

        $this->assertSame('9001', $order->shopify_order_id);
        $this->assertSame('TST1042', $order->order_number);
        $this->assertSame(self::SHOP, $order->shopify_shop_domain);
        $this->assertSame($connection->client_id, $order->client_id);
        $this->assertSame('Jane Buyer', $order->customer_name);
        $this->assertSame('paid', $order->financial_status);
        $this->assertSame('250.00', (string) $order->total);
        $this->assertCount(1, $order->items);
        $this->assertSame('W-1', $order->items->first()->lineitem_sku);
    }

    public function test_it_leaves_an_order_we_already_hold_completely_alone(): void
    {
        // Reconciliation is additive only. The order may have moved on locally
        // — shipped, delivered, edited — and re-writing it from a Shopify view
        // that predates all of that is exactly the regression the webhook path
        // already guards against. Simplest correct rule: skip it.
        $connection = $this->makeConnection();

        Order::withoutGlobalScope('shopify_visible')->create([
            'client_id'           => $connection->client_id,
            'shopify_shop_domain' => self::SHOP,
            'shopify_order_id'    => '9001',
            'order_number'        => 'TST1042',
            'source'              => 'shopify',
            'customer_name'       => 'Jane Buyer',
            'fulfillment_status'  => 'fulfilled',
        ]);

        $this->fakeOrderPages([['orders' => [$this->orderNode()], 'next' => null]]);

        $this->artisan('shopify:reconcile-orders')->assertSuccessful();

        $order = Order::withoutGlobalScope('shopify_visible')->sole();

        $this->assertSame('fulfilled', $order->fulfillment_status);
        // The row was created without line items. Reconciliation writing it
        // would have added the node's one item, so an empty set is what proves
        // it was skipped outright rather than re-written and then guarded.
        $this->assertCount(0, $order->items);
    }

    public function test_it_does_not_resurrect_an_order_the_merchant_dismissed(): void
    {
        $connection = $this->makeConnection();

        Order::withoutGlobalScope('shopify_visible')->create([
            'client_id'           => $connection->client_id,
            'shopify_shop_domain' => self::SHOP,
            'shopify_order_id'    => '9001',
            'order_number'        => 'TST1042',
            'source'              => 'shopify',
            'shopify_sync_status' => 'dismissed',
        ]);

        $this->fakeOrderPages([['orders' => [$this->orderNode()], 'next' => null]]);

        $this->artisan('shopify:reconcile-orders')->assertSuccessful();

        $this->assertSame(
            'dismissed',
            Order::withoutGlobalScope('shopify_visible')->sole()->shopify_sync_status
        );
    }

    public function test_it_follows_pagination(): void
    {
        $this->makeConnection();

        $this->fakeOrderPages([
            ['orders' => [$this->orderNode(9001, 1042)], 'next' => 'cursor-1'],
            ['orders' => [$this->orderNode(9002, 1043)], 'next' => null],
        ]);

        $this->artisan('shopify:reconcile-orders')->assertSuccessful();

        $this->assertSame(2, Order::withoutGlobalScope('shopify_visible')->count());
    }

    public function test_recovering_an_order_clears_its_parked_failure(): void
    {
        // Belt and braces: an order can be both parked (a delivery we received
        // and failed) and missing. Whichever recovery path gets there first,
        // the other must not keep showing it as stuck.
        $connection = $this->makeConnection();

        ShopifySyncFailure::record(
            self::SHOP,
            'orders/create',
            ['id' => 9001, 'name' => '#1042'],
            ShopifySyncFailure::REASON_EXCEPTION,
            'boom',
            $connection->client_id,
        );

        $this->fakeOrderPages([['orders' => [$this->orderNode()], 'next' => null]]);

        $this->artisan('shopify:reconcile-orders')->assertSuccessful();

        $this->assertSame(ShopifySyncFailure::STATUS_RESOLVED, ShopifySyncFailure::sole()->status);
    }

    public function test_dry_run_reports_without_importing(): void
    {
        $this->makeConnection();

        $this->fakeOrderPages([['orders' => [$this->orderNode()], 'next' => null]]);

        $this->artisan('shopify:reconcile-orders --dry-run')->assertSuccessful();

        $this->assertSame(0, Order::withoutGlobalScope('shopify_visible')->count());
    }

    public function test_it_skips_a_store_whose_token_cannot_be_used(): void
    {
        // A legacy non-expiring grant: getValidToken() refuses it and flags the
        // connection. One bad store must not abort the whole sweep.
        $connection = $this->makeConnection();
        $connection->update(['token_expires_at' => null, 'refresh_token' => null]);

        Http::fake();

        $this->artisan('shopify:reconcile-orders')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, Order::withoutGlobalScope('shopify_visible')->count());
    }

    // ── Webhook subscription health ──────────────────────────────────────────

    /**
     * Fake listWebhookSubscriptions() with the given topic => URL map, and let
     * every subsequent mutation succeed.
     */
    private function fakeSubscriptions(array $held): void
    {
        Http::fake([
            'https://' . self::SHOP . '/admin/api/*' => function (Request $request) use ($held) {
                if (str_contains($request->body(), 'webhookSubscriptions')) {
                    return Http::response(['data' => ['webhookSubscriptions' => [
                        'edges' => array_map(
                            fn ($url, $topic) => ['node' => ['topic' => $topic, 'endpoint' => ['callbackUrl' => $url]]],
                            $held,
                            array_keys($held),
                        ),
                    ]]]);
                }

                return Http::response(['data' => ['webhookSubscriptionCreate' => [
                    'webhookSubscription' => ['id' => 'gid://shopify/WebhookSubscription/1'],
                    'userErrors'          => [],
                ]]]);
            },
        ]);
    }

    private function allTopicsAt(string $url): array
    {
        return array_fill_keys(\App\Services\ShopifyService::WEBHOOK_TOPICS, $url);
    }

    public function test_it_re_registers_a_subscription_shopify_dropped(): void
    {
        // The exact failure mode this exists for: Shopify removed the
        // subscription after repeated delivery failures, told us nothing, and
        // our flag still claims everything is fine.
        $connection = $this->makeConnection();
        $connection->update(['webhooks_registered' => true]);

        $held = $this->allTopicsAt('https://portal.test/webhooks/shopify');
        unset($held['ORDERS_CREATE']);

        $this->fakeSubscriptions($held);

        $this->artisan('shopify:verify-webhooks')
            ->expectsOutputToContain('missing ORDERS_CREATE')
            ->assertSuccessful();

        $this->assertTrue($connection->fresh()->webhooks_registered);
    }

    public function test_it_treats_a_stale_callback_url_as_missing(): void
    {
        // A subscription left pointing at an old tunnel or a previous domain
        // delivers nowhere — just as broken as no subscription at all.
        $this->makeConnection();

        $this->fakeSubscriptions($this->allTopicsAt('https://old-tunnel.ngrok.io/webhooks/shopify'));

        $this->artisan('shopify:verify-webhooks')
            ->expectsOutputToContain('missing ORDERS_CREATE')
            ->assertSuccessful();
    }

    public function test_it_leaves_a_healthy_store_alone(): void
    {
        $this->makeConnection();

        $this->fakeSubscriptions($this->allTopicsAt('https://portal.test/webhooks/shopify'));

        $this->artisan('shopify:verify-webhooks')->assertSuccessful();

        // Only the listing call — no re-registration mutations.
        Http::assertSentCount(1);
    }

    public function test_a_store_that_cannot_be_repaired_stops_claiming_it_is_healthy(): void
    {
        // The portal surfaces webhooks_registered. If automatic repair fails,
        // the flag has to go false so the merchant is told to press "Retry
        // webhooks" instead of being shown a healthy store receiving nothing.
        $connection = $this->makeConnection();
        $connection->update(['webhooks_registered' => true]);

        Http::fake([
            'https://' . self::SHOP . '/admin/api/*' => function (Request $request) {
                if (str_contains($request->body(), 'webhookSubscriptions')) {
                    return Http::response(['data' => ['webhookSubscriptions' => ['edges' => []]]]);
                }

                return Http::response(['data' => ['webhookSubscriptionCreate' => [
                    'webhookSubscription' => null,
                    'userErrors'          => [['field' => null, 'message' => 'Access denied']],
                ]]]);
            },
        ]);

        $this->artisan('shopify:verify-webhooks')->assertSuccessful();

        $this->assertFalse($connection->fresh()->webhooks_registered);
    }

    // ── Sync filters ─────────────────────────────────────────────────────────

    public function test_it_does_not_pull_an_order_the_stores_sync_filters_reject(): void
    {
        // Filters are the merchant's decision about what belongs in the portal.
        // A delivery Shopify never managed to make is no reason to revisit it —
        // so the order is left in Shopify entirely, not stored as a hidden
        // skipped_filtered row.
        $connection = $this->makeConnection();
        $connection->update(['sync_filters' => ['tags_include' => ['wholesale']]]);

        $this->fakeOrderPages([['orders' => [$this->orderNode()], 'next' => null]]);

        $this->artisan('shopify:reconcile-orders')
            ->expectsOutputToContain("left in Shopify by the store's sync filters")
            ->assertSuccessful();

        $this->assertSame(0, Order::withoutGlobalScope('shopify_visible')->count());
    }

    public function test_it_pulls_an_order_that_passes_the_filters(): void
    {
        // The same filter, satisfied — proof the exclusion above is the filter
        // doing its job and not reconciliation quietly dropping everything.
        $connection = $this->makeConnection();
        $connection->update(['sync_filters' => ['tags_include' => ['wholesale']]]);

        $node         = $this->orderNode();
        $node['tags'] = ['Wholesale'];

        $this->fakeOrderPages([['orders' => [$node], 'next' => null]]);

        $this->artisan('shopify:reconcile-orders')->assertSuccessful();

        $this->assertSame(1, Order::withoutGlobalScope('shopify_visible')->count());
    }

    public function test_a_manual_approval_store_still_gets_its_orders_into_the_review_queue(): void
    {
        // Only skipped_filtered is dropped. pending_review orders belong in the
        // portal's review queue — which is exactly where the merchant looks for
        // them — so they must still be imported.
        $connection = $this->makeConnection();
        $connection->update(['sync_mode' => 'manual_approval']);

        $this->fakeOrderPages([['orders' => [$this->orderNode()], 'next' => null]]);

        $this->artisan('shopify:reconcile-orders')->assertSuccessful();

        $this->assertSame(
            'pending_review',
            Order::withoutGlobalScope('shopify_visible')->sole()->shopify_sync_status
        );
    }

    public function test_loosening_the_filters_lets_a_previously_excluded_order_in(): void
    {
        // The upside of not writing a skipped_filtered row: the decision is
        // re-made on each sweep rather than frozen the first time.
        $connection = $this->makeConnection();
        $connection->update(['sync_filters' => ['tags_include' => ['wholesale']]]);

        $this->fakeOrderPages([['orders' => [$this->orderNode()], 'next' => null]]);

        $this->artisan('shopify:reconcile-orders')->assertSuccessful();
        $this->assertSame(0, Order::withoutGlobalScope('shopify_visible')->count());

        $connection->update(['sync_filters' => []]);

        $this->fakeOrderPages([['orders' => [$this->orderNode()], 'next' => null]]);

        $this->artisan('shopify:reconcile-orders')->assertSuccessful();
        $this->assertSame(1, Order::withoutGlobalScope('shopify_visible')->count());
    }

    public function test_it_does_not_fetch_line_items_for_orders_it_already_holds(): void
    {
        // The whole point of splitting the query: on a healthy store almost
        // every order scanned is one we already have, and paying for its line
        // items would make the sweep cost scale with order volume rather than
        // with how much is actually missing.
        $connection = $this->makeConnection();

        Order::withoutGlobalScope('shopify_visible')->create([
            'client_id'           => $connection->client_id,
            'shopify_shop_domain' => self::SHOP,
            'shopify_order_id'    => '9001',
            'order_number'        => 'TST1042',
            'source'              => 'shopify',
        ]);

        $this->fakeOrderPages([['orders' => [$this->orderNode()], 'next' => null]]);

        $this->artisan('shopify:reconcile-orders')->assertSuccessful();

        // The listing call, and nothing else.
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request) => str_contains($request->body(), 'orderLineItems'));
    }

    public function test_the_listing_query_never_asks_for_line_items(): void
    {
        // A nested lineItems selection puts the query over Shopify's hard
        // 1,000-point ceiling, which is rejected before execution — the whole
        // sweep would fail rather than run slowly.
        $this->makeConnection();

        $this->fakeOrderPages([['orders' => [], 'next' => null]]);

        $this->artisan('shopify:reconcile-orders')->assertSuccessful();

        Http::assertSent(fn (Request $request) => str_contains($request->body(), 'reconcileOrders')
            && ! str_contains($request->body(), 'lineItems'));
    }
}

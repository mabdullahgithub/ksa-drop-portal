<?php

namespace Tests\Feature;

use App\Jobs\RequestShopifyFulfillmentJob;
use App\Models\Client;
use App\Models\ClientShopifyConnection;
use App\Models\Order;
use App\Models\User;
use App\Services\ShopifyFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * App Store requirement 5.5.1 — letting merchants request fulfillment — and the
 * payment gate requirement 5.5.5 puts around it.
 *
 * The COD case is the one to read first. 5.5.5 says not to fulfil orders in a
 * pending payment state, and taken at face value that would stop KSA Drop
 * trading: cash on delivery is most of the volume here and Shopify reports
 * every COD order as `pending` until the courier collects the money, so payment
 * cannot precede fulfillment — collecting it is the delivery. COD is exempt;
 * an unpaid card order, which is the fraud case the requirement exists for, is
 * refused.
 */
class ShopifyFulfillmentRequestTest extends TestCase
{
    use RefreshDatabase;

    private const SHOP = 'mystore.myshopify.com';

    private const LOCATION = 'gid://shopify/Location/9';

    private const SCOPES = 'read_orders,write_fulfillments,write_third_party_fulfillment_orders';

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');

        config([
            'services.shopify.key'         => 'test-api-key',
            'services.shopify.secret'      => 'test-shopify-secret',
            'services.shopify.fulfillment' => true,
            'app.url'                      => 'https://portal.test',
        ]);
    }

    private function connection(array $overrides = []): ClientShopifyConnection
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $client = Client::create([
            'user_id' => $user->id, 'company_name' => 'Test Client', 'short_id' => 'TST',
            'client_types' => ['dropshipper'], 'portal_features' => ['orders'],
        ]);

        return ClientShopifyConnection::create(array_merge([
            'client_id'                  => $client->id,
            'shop_domain'                => self::SHOP,
            'access_token'               => 'tok-123',
            'token_expires_at'           => now()->addHour(),
            'scope'                      => self::SCOPES,
            'status'                     => 'active',
            'sync_mode'                  => 'auto_sync',
            'fulfillment_service_id'     => 'gid://shopify/FulfillmentService/1',
            'fulfillment_service_handle' => 'ksadrop',
            'fulfillment_location_id'    => self::LOCATION,
        ], $overrides));
    }

    private function order(ClientShopifyConnection $connection, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'client_id'           => $connection->client_id,
            'order_number'        => '1001',
            'shopify_order_id'    => '5555',
            'shopify_shop_domain' => self::SHOP,
            'customer_name'       => 'Sara',
            'financial_status'    => 'paid',
            'fulfillment_status'  => 'unfulfilled',
            'payment_method'      => 'prepaid',
            'source'              => 'shopify',
            'total'               => 149.00,
        ], $overrides));
    }

    /** A store with one requestable fulfillment order of ours, then an accepted submit. */
    private function fakeRequestableThenSubmit(): void
    {
        $this->fakeGraphql(
            ['data' => ['order' => ['fulfillmentOrders' => ['nodes' => [[
                'id' => 'gid://shopify/FulfillmentOrder/77',
                'status' => 'OPEN',
                'requestStatus' => 'UNSUBMITTED',
                'assignedLocation' => ['location' => ['id' => self::LOCATION]],
            ]]]]]],
            ['data' => ['fulfillmentOrderSubmitFulfillmentRequest' => [
                'submittedFulfillmentOrder' => ['id' => 'gid://shopify/FulfillmentOrder/77'],
                'userErrors' => [],
            ]]],
        );
    }

    private function fakeGraphql(array ...$responses): void
    {
        $sequence = Http::sequence();

        foreach ($responses as $body) {
            $sequence->pushResponse(Http::response($body));
        }

        Http::fake(['https://' . self::SHOP . '/admin/api/*/graphql.json' => $sequence]);
    }

    private function service(): ShopifyFulfillmentService
    {
        return app(ShopifyFulfillmentService::class);
    }

    public function test_it_submits_a_fulfillment_request_for_a_paid_order(): void
    {
        $connection = $this->connection();
        $order = $this->order($connection);

        $this->fakeRequestableThenSubmit();

        $this->assertSame(ShopifyFulfillmentService::REQUEST_SENT, $this->service()->requestFulfillment($order));

        Http::assertSent(fn (Request $r) => str_contains($r->body(), 'fulfillmentOrderSubmitFulfillmentRequest'));

        $order->refresh();
        $this->assertSame('gid://shopify/FulfillmentOrder/77', $order->shopify_fulfillment_order_id);
        $this->assertSame('requested', $order->shopify_fulfillment_status);
        $this->assertNotNull($order->shopify_fulfillment_requested_at);
    }

    public function test_an_unpaid_card_order_is_never_requested(): void
    {
        Http::fake();

        $connection = $this->connection();
        $order = $this->order($connection, ['financial_status' => 'pending', 'payment_method' => 'prepaid']);

        // Requirement 5.5.5: an order still awaiting payment is not fulfilled.
        $this->assertSame(ShopifyFulfillmentService::REQUEST_AWAITING_PAYMENT, $this->service()->requestFulfillment($order));

        Http::assertNothingSent();
        $this->assertNull($order->fresh()->shopify_fulfillment_order_id);
    }

    public function test_a_cod_order_is_requested_even_though_shopify_calls_it_pending(): void
    {
        $connection = $this->connection();
        $order = $this->order($connection, ['financial_status' => 'pending', 'payment_method' => 'cod']);

        $this->fakeRequestableThenSubmit();

        // The money arrives with the courier, so waiting for `paid` would mean
        // never shipping a COD order at all.
        $this->assertSame(ShopifyFulfillmentService::REQUEST_SENT, $this->service()->requestFulfillment($order));
    }

    public function test_a_refunded_order_is_never_requested_even_on_cod(): void
    {
        Http::fake();

        $connection = $this->connection();
        $order = $this->order($connection, ['financial_status' => 'refunded', 'payment_method' => 'cod']);

        $this->assertSame(ShopifyFulfillmentService::REQUEST_UNAVAILABLE, $this->service()->requestFulfillment($order));

        Http::assertNothingSent();
    }

    public function test_another_suppliers_fulfillment_order_is_left_alone(): void
    {
        $connection = $this->connection();
        $order = $this->order($connection);

        $this->fakeGraphql(
            ['data' => ['order' => ['fulfillmentOrders' => ['nodes' => [[
                'id' => 'gid://shopify/FulfillmentOrder/88',
                'status' => 'OPEN',
                'requestStatus' => 'UNSUBMITTED',
                // A different dropshipper's location on the same order.
                'assignedLocation' => ['location' => ['id' => 'gid://shopify/Location/404']],
            ]]]]]],
        );

        $this->assertSame(
            ShopifyFulfillmentService::REQUEST_NO_FULFILLMENT_ORDER,
            $this->service()->requestFulfillment($order)
        );

        Http::assertNotSent(fn (Request $r) => str_contains($r->body(), 'fulfillmentOrderSubmitFulfillmentRequest'));
    }

    public function test_a_second_request_is_not_submitted(): void
    {
        Http::fake();

        $connection = $this->connection();
        $order = $this->order($connection, ['shopify_fulfillment_order_id' => 'gid://shopify/FulfillmentOrder/77']);

        // Four entry points can reach requestFulfillment for one order; only
        // the first may produce a request.
        $this->assertSame(ShopifyFulfillmentService::REQUEST_ALREADY_SENT, $this->service()->requestFulfillment($order));

        Http::assertNothingSent();
    }

    public function test_a_store_without_the_scopes_is_skipped(): void
    {
        Http::fake();

        $connection = $this->connection(['scope' => 'read_orders,read_customers']);
        $order = $this->order($connection);

        $this->assertSame(ShopifyFulfillmentService::REQUEST_UNAVAILABLE, $this->service()->requestFulfillment($order));

        Http::assertNothingSent();
    }

    public function test_the_kill_switch_stops_requests(): void
    {
        Http::fake();
        config(['services.shopify.fulfillment' => false]);

        $connection = $this->connection();
        $order = $this->order($connection);

        $this->assertSame(ShopifyFulfillmentService::REQUEST_UNAVAILABLE, $this->service()->requestFulfillment($order));

        Http::assertNothingSent();
    }

    public function test_approving_a_pending_order_requests_fulfillment(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $connection = $this->connection(['sync_mode' => 'manual_approval']);
        $order = $this->order($connection, ['shopify_sync_status' => 'pending_review']);

        $user = Client::find($connection->client_id)->user;

        $this->actingAs($user)
            ->postJson("/portal/api/shopify/pending/{$order->id}/submit")
            ->assertOk();

        // Under manual approval the order is not ours to move until the client
        // accepts it, so this — not the import — is when we ask Shopify.
        \Illuminate\Support\Facades\Queue::assertPushed(RequestShopifyFulfillmentJob::class);
    }

    public function test_dismissing_a_requested_order_tells_shopify(): void
    {
        $connection = $this->connection(['sync_mode' => 'manual_approval']);
        $order = $this->order($connection, [
            'shopify_sync_status'          => 'pending_review',
            'shopify_fulfillment_order_id' => 'gid://shopify/FulfillmentOrder/77',
        ]);

        $this->fakeGraphql(['data' => ['fulfillmentOrderRejectFulfillmentRequest' => [
            'fulfillmentOrder' => ['id' => 'gid://shopify/FulfillmentOrder/77', 'status' => 'OPEN', 'requestStatus' => 'REJECTED'],
            'userErrors' => [],
        ]]]);

        $user = Client::find($connection->client_id)->user;

        $this->actingAs($user)
            ->deleteJson("/portal/api/shopify/pending/{$order->id}/dismiss")
            ->assertOk();

        // Otherwise the merchant's admin shows the order as requested forever
        // and they learn we are not shipping it from their own customer.
        Http::assertSent(fn (Request $r) => str_contains($r->body(), 'fulfillmentOrderRejectFulfillmentRequest'));
        $this->assertSame('rejected', $order->fresh()->shopify_fulfillment_status);
    }
}

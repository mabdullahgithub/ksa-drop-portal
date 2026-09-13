<?php

namespace Tests\Feature;

use App\Jobs\CancelShopifyFulfillmentJob;
use App\Models\Client;
use App\Models\ClientShopifyConnection;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Shipping\Enums\ShipmentStatus;
use App\Services\ShopifyFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reporting the courier's waybill back to Shopify — the half of the integration
 * that did not exist before, and the reason merchants' orders used to sit
 * unfulfilled in their admin forever while we delivered them.
 *
 * "Fulfilled" here means shipped, which is Shopify's meaning and not ours: our
 * own fulfillment_status stays unfulfilled until delivery. The two are allowed
 * to disagree because each is right in its own system.
 */
class ShopifyFulfillmentPushTest extends TestCase
{
    use RefreshDatabase;

    private const SHOP = 'mystore.myshopify.com';

    private const FO = 'gid://shopify/FulfillmentOrder/77';

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

    private function connection(): ClientShopifyConnection
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $client = Client::create([
            'user_id' => $user->id, 'company_name' => 'Test Client', 'short_id' => 'TST',
            'client_types' => ['dropshipper'], 'portal_features' => ['orders'],
        ]);

        return ClientShopifyConnection::create([
            'client_id'                => $client->id,
            'shop_domain'              => self::SHOP,
            'access_token'             => 'tok-123',
            'token_expires_at'         => now()->addHour(),
            'scope'                    => 'read_orders,write_fulfillments,write_third_party_fulfillment_orders',
            'status'                   => 'active',
            'fulfillment_service_id'   => 'gid://shopify/FulfillmentService/1',
            'fulfillment_location_id'  => 'gid://shopify/Location/9',
        ]);
    }

    private function shipment(ClientShopifyConnection $connection, array $orderOverrides = [], array $shipmentOverrides = []): Shipment
    {
        $order = Order::create(array_merge([
            'client_id'                    => $connection->client_id,
            'order_number'                 => '1001',
            'shopify_order_id'             => '5555',
            'shopify_shop_domain'          => self::SHOP,
            'customer_name'                => 'Sara',
            'financial_status'             => 'paid',
            'fulfillment_status'           => 'unfulfilled',
            'payment_method'               => 'cod',
            'source'                       => 'shopify',
            'total'                        => 149.00,
            'shopify_fulfillment_order_id' => self::FO,
            'shopify_fulfillment_status'   => 'accepted',
        ], $orderOverrides));

        return Shipment::create(array_merge([
            'order_id'        => $order->id,
            'courier'         => 'imile',
            'tracking_number' => 'IM123456789',
            'txlogistic_id'   => 'TX-1001',
            'status'          => ShipmentStatus::INFO_RECEIVED->value,
            'shipped_at'      => now(),
        ], $shipmentOverrides));
    }

    private function fakeGraphql(array ...$responses): void
    {
        $sequence = Http::sequence();

        foreach ($responses as $body) {
            $sequence->pushResponse(Http::response($body));
        }

        Http::fake(['https://' . self::SHOP . '/admin/api/*/graphql.json' => $sequence]);
    }

    public function test_it_reports_the_waybill_to_shopify(): void
    {
        $connection = $this->connection();
        $shipment = $this->shipment($connection);

        $this->fakeGraphql(['data' => ['fulfillmentCreate' => [
            'fulfillment' => ['id' => 'gid://shopify/Fulfillment/500', 'status' => 'SUCCESS'],
            'userErrors' => [],
        ]]]);

        $this->assertTrue(app(ShopifyFulfillmentService::class)->fulfillFromShipment($shipment));

        Http::assertSent(function (Request $r) {
            $body = $r->body();

            return str_contains($body, 'fulfillmentCreate')
                && str_contains($body, 'IM123456789')
                && str_contains($body, 'iMile')
                // Our own tracking page, not a hand-written courier URL: this
                // link goes to the merchant's customer in Shopify's email.
                && str_contains($body, 'portal.test\/track?q=IM123456789');
        });

        $this->assertSame('gid://shopify/Fulfillment/500', $shipment->fresh()->shopify_fulfillment_id);
        $this->assertSame('fulfilled', $shipment->order->fresh()->shopify_fulfillment_status);
    }

    public function test_it_never_creates_a_second_fulfillment(): void
    {
        Http::fake();

        $connection = $this->connection();
        $shipment = $this->shipment($connection, [], ['shopify_fulfillment_id' => 'gid://shopify/Fulfillment/500']);

        // A retried job or a re-saved shipment would otherwise send the
        // customer a second shipping email.
        $this->assertTrue(app(ShopifyFulfillmentService::class)->fulfillFromShipment($shipment));

        Http::assertNothingSent();
    }

    public function test_an_order_we_were_never_asked_to_fulfil_is_left_alone(): void
    {
        Http::fake();

        $connection = $this->connection();
        $shipment = $this->shipment($connection, ['shopify_fulfillment_order_id' => null]);

        $this->assertFalse(app(ShopifyFulfillmentService::class)->fulfillFromShipment($shipment));

        Http::assertNothingSent();
    }

    public function test_a_shopify_outage_leaves_the_booking_alone(): void
    {
        $connection = $this->connection();
        $shipment = $this->shipment($connection);

        Http::fake(['https://' . self::SHOP . '/admin/api/*/graphql.json' => Http::response('boom', 500)]);

        // The courier already has the parcel and has charged us. Reporting it
        // can fail and be retried; the booking itself must stand.
        $this->assertFalse(app(ShopifyFulfillmentService::class)->fulfillFromShipment($shipment));
        $this->assertNull($shipment->fresh()->shopify_fulfillment_id);
    }

    public function test_a_returned_parcel_takes_the_fulfillment_back(): void
    {
        Queue::fake();

        $connection = $this->connection();
        $shipment = $this->shipment($connection, [], ['shopify_fulfillment_id' => 'gid://shopify/Fulfillment/500']);

        $shipment->markReturned('Customer refused delivery');

        // Otherwise the merchant's order stays Fulfilled with a tracking number
        // their customer can still follow.
        Queue::assertPushed(CancelShopifyFulfillmentJob::class);
        $this->assertSame('cancelled', $shipment->order->fresh()->fulfillment_status);
    }

    public function test_cancelling_a_fulfillment_calls_shopify(): void
    {
        $connection = $this->connection();
        $shipment = $this->shipment($connection, [], ['shopify_fulfillment_id' => 'gid://shopify/Fulfillment/500']);

        $this->fakeGraphql(['data' => ['fulfillmentCancel' => [
            'fulfillment' => ['id' => 'gid://shopify/Fulfillment/500', 'status' => 'CANCELLED'],
            'userErrors' => [],
        ]]]);

        $this->assertTrue(app(ShopifyFulfillmentService::class)->cancelFulfillment($shipment));

        Http::assertSent(fn (Request $r) => str_contains($r->body(), 'fulfillmentCancel'));
        $this->assertSame('cancelled', $shipment->order->fresh()->shopify_fulfillment_status);
    }

    public function test_a_cancellation_is_accepted_before_the_courier_has_it(): void
    {
        $connection = $this->connection();

        Order::create([
            'client_id' => $connection->client_id, 'order_number' => '1002',
            'shopify_order_id' => '5556', 'shopify_shop_domain' => self::SHOP,
            'customer_name' => 'Sara', 'financial_status' => 'paid', 'fulfillment_status' => 'unfulfilled',
            'source' => 'shopify', 'total' => 100, 'shopify_fulfillment_order_id' => self::FO,
        ]);

        $this->fakeGraphql(['data' => ['fulfillmentOrderAcceptCancellationRequest' => [
            'fulfillmentOrder' => ['id' => self::FO, 'status' => 'CANCELLED', 'requestStatus' => 'CANCELLATION_ACCEPTED'],
            'userErrors' => [],
        ]]]);

        \App\Jobs\ProcessShopifyWebhookJob::dispatchSync(
            self::SHOP,
            'fulfillment_orders/cancellation_request_submitted',
            ['fulfillment_order' => ['id' => 77]],
        );

        Http::assertSent(fn (Request $r) => str_contains($r->body(), 'fulfillmentOrderAcceptCancellationRequest'));
    }

    public function test_a_cancellation_is_refused_once_the_parcel_is_moving(): void
    {
        $connection = $this->connection();
        $this->shipment($connection);

        $this->fakeGraphql(['data' => ['fulfillmentOrderRejectCancellationRequest' => [
            'fulfillmentOrder' => ['id' => self::FO, 'status' => 'IN_PROGRESS', 'requestStatus' => 'CANCELLATION_REJECTED'],
            'userErrors' => [],
        ]]]);

        \App\Jobs\ProcessShopifyWebhookJob::dispatchSync(
            self::SHOP,
            'fulfillment_orders/cancellation_request_submitted',
            ['fulfillment_order' => ['id' => 77]],
        );

        // Saying yes would tell the merchant an order was stopped while it is
        // still on its way to their customer.
        Http::assertSent(fn (Request $r) => str_contains($r->body(), 'fulfillmentOrderRejectCancellationRequest'));
    }
}

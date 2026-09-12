<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientShopifyConnection;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ShopifyFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Registering the app as a Shopify fulfillment service — the step App Store
 * requirement 5.5.1 rests on, because fulfillmentOrderSubmitFulfillmentRequest
 * submits to the service assigned to a fulfillment order and we have to be that
 * service before there is anything to submit.
 *
 * The case worth the most care here is the second one. fulfillmentServiceCreate
 * is not idempotent: called twice it leaves the merchant with two "KSADrop"
 * locations and their stock split between them, which is painful to unpick and
 * easy to trigger — a reinstall, a token repair and an app load all reach this
 * code.
 */
class ShopifyFulfillmentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const SHOP = 'mystore.myshopify.com';

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

    private function makeConnection(array $overrides = []): ClientShopifyConnection
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

        return ClientShopifyConnection::create(array_merge([
            'client_id'        => $client->id,
            'shop_domain'      => self::SHOP,
            'access_token'     => 'tok-123',
            'refresh_token'    => 'ref-123',
            'token_expires_at' => now()->addHour(),
            'scope'            => self::SCOPES,
            'status'           => 'active',
            'connected_at'     => now(),
        ], $overrides));
    }

    /** Every GraphQL call is one POST to the same URL, so fakes answer in order. */
    private function fakeGraphql(array ...$responses): void
    {
        $sequence = Http::sequence();

        foreach ($responses as $body) {
            $sequence->pushResponse(Http::response($body));
        }

        Http::fake(["https://" . self::SHOP . "/admin/api/*/graphql.json" => $sequence]);
    }

    public function test_it_registers_a_service_and_stores_the_ids(): void
    {
        Warehouse::create([
            'name' => 'Riyadh DC', 'contact_name' => 'Ops', 'phone' => '0500000000',
            'province' => 'Riyadh', 'city' => 'Riyadh', 'address' => 'Street 1',
            'post_code' => '12345', 'country_code' => 'SA',
            'is_default' => true, 'is_active' => true,
        ]);

        $connection = $this->makeConnection();

        $this->fakeGraphql(
            ['data' => ['shop' => ['fulfillmentServices' => []]]],
            ['data' => ['fulfillmentServiceCreate' => [
                'fulfillmentService' => [
                    'id'          => 'gid://shopify/FulfillmentService/1',
                    'serviceName' => 'KSADrop',
                    'location'    => ['id' => 'gid://shopify/Location/9'],
                ],
                'userErrors' => [],
            ]]],
            ['data' => ['locationEdit' => ['location' => ['id' => 'gid://shopify/Location/9', 'name' => 'KSADrop'], 'userErrors' => []]]],
        );

        $this->assertTrue(app(ShopifyFulfillmentService::class)->ensureRegistered($connection));

        $connection->refresh();
        $this->assertSame('gid://shopify/FulfillmentService/1', $connection->fulfillment_service_id);
        $this->assertSame('gid://shopify/Location/9', $connection->fulfillment_location_id);
        $this->assertNotNull($connection->fulfillment_registered_at);

        // The location must be told where it actually is: Shopify creates it at
        // the merchant's own address, which would bill shipping and tax from
        // the wrong origin.
        Http::assertSent(fn (Request $r) => str_contains($r->body(), 'locationEdit')
            && str_contains($r->body(), 'Riyadh'));
    }

    public function test_it_adopts_an_existing_service_instead_of_creating_a_second(): void
    {
        $connection = $this->makeConnection();

        $this->fakeGraphql(
            ['data' => ['shop' => ['fulfillmentServices' => [
                // Another app's service, on the same store — must be ignored.
                [
                    'id' => 'gid://shopify/FulfillmentService/77', 'serviceName' => 'OtherDropshipper',
                    'callbackUrl' => 'https://other.example.com/callback', 'location' => ['id' => 'gid://shopify/Location/77'],
                ],
                [
                    'id' => 'gid://shopify/FulfillmentService/1', 'serviceName' => 'KSADrop',
                    'callbackUrl' => 'https://portal.test/webhooks/shopify/fulfillment', 'location' => ['id' => 'gid://shopify/Location/9'],
                ],
            ]]]],
            ['data' => ['locationEdit' => ['location' => null, 'userErrors' => []]]],
        );

        $this->assertTrue(app(ShopifyFulfillmentService::class)->ensureRegistered($connection));

        $this->assertSame('gid://shopify/FulfillmentService/1', $connection->fresh()->fulfillment_service_id);

        Http::assertNotSent(fn (Request $r) => str_contains($r->body(), 'fulfillmentServiceCreate'));
    }

    public function test_an_already_registered_connection_makes_no_api_call(): void
    {
        Http::fake();

        $connection = $this->makeConnection([
            'fulfillment_service_id'  => 'gid://shopify/FulfillmentService/1',
            'fulfillment_location_id' => 'gid://shopify/Location/9',
        ]);

        $this->assertTrue(app(ShopifyFulfillmentService::class)->ensureRegistered($connection));

        Http::assertNothingSent();
    }

    public function test_it_skips_a_connection_that_has_not_been_granted_the_scopes(): void
    {
        Http::fake();

        $connection = $this->makeConnection(['scope' => 'read_orders,read_customers']);

        $this->assertFalse(app(ShopifyFulfillmentService::class)->ensureRegistered($connection));

        // Not an error — the store simply predates the scopes and is waiting on
        // managed installation to re-prompt the merchant.
        Http::assertNothingSent();
        $this->assertNull($connection->fresh()->fulfillment_service_id);
    }

    public function test_the_kill_switch_stops_every_call(): void
    {
        Http::fake();
        config(['services.shopify.fulfillment' => false]);

        $connection = $this->makeConnection();

        $this->assertFalse(app(ShopifyFulfillmentService::class)->ensureRegistered($connection));

        Http::assertNothingSent();
    }

    public function test_a_rejected_registration_is_reported_not_thrown(): void
    {
        $connection = $this->makeConnection();

        $this->fakeGraphql(
            ['data' => ['shop' => ['fulfillmentServices' => []]]],
            ['data' => ['fulfillmentServiceCreate' => [
                'fulfillmentService' => null,
                'userErrors' => [['field' => ['name'], 'message' => 'Name has already been taken']],
            ]]],
        );

        // Whatever called us — OAuth, an app load — must carry on regardless.
        $this->assertFalse(app(ShopifyFulfillmentService::class)->ensureRegistered($connection));
        $this->assertNull($connection->fresh()->fulfillment_service_id);
    }

    public function test_a_shopify_outage_does_not_throw(): void
    {
        $connection = $this->makeConnection();

        Http::fake(["https://" . self::SHOP . "/admin/api/*/graphql.json" => Http::response('gateway timeout', 504)]);

        $this->assertFalse(app(ShopifyFulfillmentService::class)->ensureRegistered($connection));
    }

    public function test_the_callback_endpoints_answer(): void
    {
        // Shopify is handed this prefix at registration and appends the paths
        // itself, so all three have to exist from that moment.
        $this->getJson('/webhooks/shopify/fulfillment/fetch_stock')->assertOk();
        $this->getJson('/webhooks/shopify/fulfillment/fetch_tracking_numbers')->assertOk();

        $this->postJson('/webhooks/shopify/fulfillment/fulfillment_order_notification', ['kind' => 'FULFILLMENT_REQUEST'])
            ->assertStatus(401);
    }
}

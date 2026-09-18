<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientShopifyConnection;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Real stock on the KSADrop location.
 *
 * Shopify ignores the product CSV's quantity column on any store with more
 * than one location, and a store with a KSADrop location always has two — so
 * every import landed at 0 and the product showed "Sold out". With the service
 * set to inventoryManagement, Shopify asks /fetch_stock instead and writes what
 * we return onto the location.
 */
class ShopifyFulfillmentStockSyncTest extends TestCase
{
    use RefreshDatabase;

    private const SHOP = 'mystore.myshopify.com';

    private const SECRET = 'test-shopify-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');

        config([
            'services.shopify.key'         => 'test-api-key',
            'services.shopify.secret'      => self::SECRET,
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
            'scope'                      => 'read_orders,write_fulfillments,write_third_party_fulfillment_orders,read_locations',
            'status'                     => 'active',
            'fulfillment_service_id'     => 'gid://shopify/FulfillmentService/1',
            'fulfillment_service_handle' => 'ksadrop',
            'fulfillment_location_id'    => 'gid://shopify/Location/9',
        ], $overrides));
    }

    private function product(string $sku, $qty): Product
    {
        return Product::create([
            'handle' => strtolower($sku), 'title' => $sku, 'variant_sku' => $sku,
            'variant_price' => 10, 'variant_inventory_qty' => $qty, 'published' => true,
        ]);
    }

    public function test_it_reports_one_sku_when_shopify_asks_for_one(): void
    {
        $this->connection();
        $this->product('KSA-283', 99);
        $this->product('KSA-292', 500);

        $this->getJson('/webhooks/shopify/fulfillment/fetch_stock?shop=' . self::SHOP . '&sku=KSA-283')
            ->assertOk()
            ->assertExactJson(['KSA-283' => 99]);
    }

    public function test_it_reports_every_sku_on_the_hourly_sweep(): void
    {
        $this->connection();
        $this->product('KSA-283', 99);
        $this->product('KSA-292', 500);
        // A catalogue row that has gone negative is reported as none, not as a
        // figure Shopify would have to make sense of.
        $this->product('KSA-300', -4);

        $this->getJson('/webhooks/shopify/fulfillment/fetch_stock?shop=' . self::SHOP)
            ->assertOk()
            ->assertExactJson(['KSA-283' => 99, 'KSA-292' => 500, 'KSA-300' => 0]);
    }

    public function test_the_json_suffix_is_answered_too(): void
    {
        $this->connection();
        $this->product('KSA-283', 99);

        $this->getJson('/webhooks/shopify/fulfillment/fetch_stock.json?shop=' . self::SHOP . '&sku=KSA-283')
            ->assertOk()
            ->assertExactJson(['KSA-283' => 99]);
    }

    public function test_an_unknown_shop_gets_nothing(): void
    {
        $this->product('KSA-283', 99);

        $this->getJson('/webhooks/shopify/fulfillment/fetch_stock?shop=stranger.myshopify.com')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_a_store_without_a_ksadrop_service_gets_nothing(): void
    {
        $this->connection(['fulfillment_service_id' => null, 'fulfillment_location_id' => null]);
        $this->product('KSA-283', 99);

        $this->getJson('/webhooks/shopify/fulfillment/fetch_stock?shop=' . self::SHOP)
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_a_signed_request_must_verify(): void
    {
        $this->connection();
        $this->product('KSA-283', 99);

        $this->getJson('/webhooks/shopify/fulfillment/fetch_stock?shop=' . self::SHOP . '&sku=KSA-283&hmac=forged')
            ->assertStatus(401);

        $params = ['shop' => self::SHOP, 'sku' => 'KSA-283', 'timestamp' => (string) time()];
        ksort($params);
        $message = collect($params)->map(fn ($v, $k) => "{$k}={$v}")->implode('&');
        $query = $message . '&hmac=' . hash_hmac('sha256', $message, self::SECRET);

        $this->getJson('/webhooks/shopify/fulfillment/fetch_stock?' . $query)
            ->assertOk()
            ->assertExactJson(['KSA-283' => 99]);
    }

    public function test_the_command_turns_inventory_management_on(): void
    {
        $this->connection();

        Http::fake(['https://' . self::SHOP . '/admin/api/*/graphql.json' => Http::response(['data' => [
            'fulfillmentServiceUpdate' => ['fulfillmentService' => ['id' => 'gid://shopify/FulfillmentService/1'], 'userErrors' => []],
        ]])]);

        $this->artisan('shopify:fulfillment-stock-sync', ['--enable' => true, '--shop' => self::SHOP])
            ->expectsOutputToContain('stock sync enabled')
            ->assertSuccessful();

        Http::assertSent(fn (Request $r) => str_contains($r->body(), 'fulfillmentServiceUpdate')
            && (json_decode($r->body(), true)['variables']['inventoryManagement'] ?? null) === true);
    }

    public function test_the_command_turns_it_back_off(): void
    {
        $this->connection();

        Http::fake(['https://' . self::SHOP . '/admin/api/*/graphql.json' => Http::response(['data' => [
            'fulfillmentServiceUpdate' => ['fulfillmentService' => ['id' => 'gid://shopify/FulfillmentService/1'], 'userErrors' => []],
        ]])]);

        $this->artisan('shopify:fulfillment-stock-sync', ['--disable' => true])->assertSuccessful();

        Http::assertSent(fn (Request $r) => (json_decode($r->body(), true)['variables']['inventoryManagement'] ?? null) === false);
    }

    public function test_the_command_needs_exactly_one_direction(): void
    {
        Http::fake();

        $this->artisan('shopify:fulfillment-stock-sync')->assertExitCode(2);

        Http::assertNothingSent();
    }

    public function test_new_registrations_follow_the_stock_sync_setting(): void
    {
        config(['services.shopify.stock_sync' => true]);

        $connection = $this->connection([
            'fulfillment_service_id' => null, 'fulfillment_service_handle' => null, 'fulfillment_location_id' => null,
        ]);

        $sequence = Http::sequence()
            ->push(['data' => ['shop' => ['fulfillmentServices' => []]]])
            ->push(['data' => ['fulfillmentServiceCreate' => [
                'fulfillmentService' => ['id' => 'gid://shopify/FulfillmentService/2', 'serviceName' => 'KSADrop', 'handle' => 'ksadrop', 'location' => ['id' => 'gid://shopify/Location/10']],
                'userErrors' => [],
            ]]])
            ->push(['data' => ['locationEdit' => ['location' => null, 'userErrors' => []]]]);

        Http::fake(['https://' . self::SHOP . '/admin/api/*/graphql.json' => $sequence]);

        app(\App\Services\ShopifyFulfillmentService::class)->ensureRegistered($connection);

        Http::assertSent(fn (Request $r) => str_contains($r->body(), 'fulfillmentServiceCreate')
            && (json_decode($r->body(), true)['variables']['inventoryManagement'] ?? null) === true);
    }
}

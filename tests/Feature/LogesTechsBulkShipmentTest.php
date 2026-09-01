<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * LogesTechs rejects any shipment without a destination district and a Saudi
 * National Address, and an order carries neither — which is what made bulk
 * create unusable for this courier. ShipmentController::bulkStore now assigns
 * both automatically: a district drawn at random from LogesTechs' own lookup,
 * and a generated National Address code.
 */
class LogesTechsBulkShipmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('edit orders');

        config()->set('services.logestechs', [
            'company_id' => '722',
            'email' => 'test@example.com',
            'password' => 'secret',
            'base_url' => 'https://apisv2.logestechs.com/api',
            'integration_source' => 'ksadrop_portal',
        ]);
    }

    private function actor(): User
    {
        $role = Role::create(['name' => 'role-' . uniqid()]);
        $role->givePermissionTo(['edit orders']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeOrder(): Order
    {
        $order = Order::create([
            'order_number' => (string) random_int(100000, 999999),
            'customer_name' => 'abdullah - ksadrop',
            'customer_phone' => '0500000000',
            'shipping_name' => 'abdullah - ksadrop',
            'shipping_phone' => '0500000000',
            'shipping_province' => 'Riyadh',
            'shipping_city' => 'Riyadh',
            'shipping_address1' => 'Al Suwaidi District',
            'shipping_country' => 'KSA',
            'currency' => 'SAR',
            'total' => 100.0,
            'payment_method' => 'prepaid',
            'financial_status' => 'paid',
        ]);

        $order->items()->create([
            'lineitem_name' => 'Test item',
            'lineitem_quantity' => 1,
            'lineitem_price' => 100.0,
        ]);

        return $order->fresh('items');
    }

    private function warehouse(): Warehouse
    {
        return Warehouse::create([
            'name' => 'Riyadh WH',
            'contact_name' => 'KSA',
            'phone' => '0501112229',
            'province' => 'Riyadh',
            'city' => 'Riyadh',
            'address' => 'Al Suwaidi',
            'post_code' => '12345',
            'country_code' => 'KSA',
        ]);
    }

    public function test_bulk_create_assigns_a_district_and_national_address_to_every_logestechs_shipment(): void
    {
        $orders = [$this->makeOrder(), $this->makeOrder(), $this->makeOrder()];

        $created = 0;

        Http::fake([
            '*addresses/villages*' => Http::response([
                ['id' => 11, 'name' => 'الرياض', 'englishName' => 'Riyadh', 'cityName' => 'Riyadh'],
                ['id' => 22, 'name' => 'جدة', 'englishName' => 'Jeddah', 'cityName' => 'Jeddah'],
                // No id — LogesTechs can't resolve a bare name, so this one
                // must never be picked.
                ['id' => null, 'name' => 'Unusable'],
            ]),
            '*ship/request/by-email*' => Http::sequence()
                ->push(['id' => 1, 'barcode' => 'KSA1'])
                ->push(['id' => 2, 'barcode' => 'KSA2'])
                ->push(['id' => 3, 'barcode' => 'KSA3']),
        ]);

        $response = $this->actingAs($this->actor())->postJson('/api/shipments/bulk', [
            'order_ids' => array_map(fn (Order $o) => $o->id, $orders),
            'warehouse_id' => $this->warehouse()->id,
            'courier' => 'logestechs',
            'weight' => 0.5,
        ]);

        $response->assertOk();
        $this->assertCount(3, $response->json('created'), 'All three orders should ship: ' . json_encode($response->json('failed')));

        // The district lookup is fetched once per batch, not once per order.
        $lookups = 0;

        Http::assertSent(function (ClientRequest $request) use (&$lookups, &$created) {
            if (str_contains($request->url(), 'addresses/villages')) {
                $lookups++;

                return true;
            }

            if (! str_contains($request->url(), 'ship/request/by-email')) {
                return false;
            }

            $created++;
            $destination = $request->data()['destinationAddress'] ?? [];

            $this->assertContains($destination['villageId'] ?? null, [11, 22]);
            $this->assertContains($destination['village'] ?? null, ['الرياض', 'جدة']);
            $this->assertMatchesRegularExpression('/^[A-Z]{4}\d{4}$/', $destination['nationalAddress'] ?? '');

            return true;
        });

        $this->assertSame(1, $lookups);
        $this->assertSame(3, $created);
    }

    public function test_bulk_create_fails_cleanly_when_no_districts_can_be_loaded(): void
    {
        $order = $this->makeOrder();

        Http::fake([
            '*addresses/villages*' => Http::response([], 500),
            '*' => Http::response([], 200),
        ]);

        $this->actingAs($this->actor())
            ->postJson('/api/shipments/bulk', [
                'order_ids' => [$order->id],
                'warehouse_id' => $this->warehouse()->id,
                'courier' => 'logestechs',
            ])
            ->assertStatus(422)
            ->assertJsonPath('created', []);

        Http::assertNotSent(fn (ClientRequest $request) => str_contains($request->url(), 'ship/request'));
    }

    public function test_bulk_create_for_other_couriers_does_not_hit_the_district_lookup(): void
    {
        $order = $this->makeOrder();

        config()->set('services.jnt_express', [
            'api_account' => 'TEST_ACCOUNT',
            'private_key' => 'TEST_KEY',
            'customer_code' => 'TEST_CODE',
            'customer_password' => 'TEST_PASS',
            'sandbox_uuid' => null,
            'base_url' => 'https://openapi.jtjms-sa.com',
        ]);

        Http::fake(['*' => Http::response(['code' => '1', 'data' => json_encode(['billCode' => 'JT1'])])]);

        $this->actingAs($this->actor())
            ->postJson('/api/shipments/bulk', [
                'order_ids' => [$order->id],
                'warehouse_id' => $this->warehouse()->id,
                'courier' => 'jnt_express',
            ])
            ->assertOk();

        Http::assertNotSent(fn (ClientRequest $request) => str_contains($request->url(), 'addresses/villages'));
    }
}

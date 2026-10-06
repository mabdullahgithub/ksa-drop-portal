<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\StockScan;
use App\Models\User;
use App\Services\Inventory\StockAlerts;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Concerns\MakesRiders;
use Tests\TestCase;

/**
 * The stock warning cards (a fulfilment client's product at 3, 2, 1 or none
 * left) and the "No stock" mark on orders waiting for a product with none.
 */
class StockAlertsTest extends TestCase
{
    use MakesRiders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRiderPermissions();
        \Spatie\Permission\Models\Role::findOrCreate('client');
    }

    private function client(string $shortId, array $types = ['fulfilment']): array
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $client = Client::create([
            'user_id' => $user->id,
            'company_name' => "Client {$shortId}",
            'short_id' => $shortId,
            'client_types' => $types,
            'portal_features' => ['orders', 'inventory'],
        ]);

        return [$user, $client];
    }

    private function product(Client $client, string $sku, int $quantity, array $attributes = []): ClientProduct
    {
        return $client->clientProducts()->create($attributes + [
            'product_code' => $client->short_id . '-' . Str::upper(Str::random(5)),
            'name' => "Product {$sku}",
            'sku' => $sku,
            'quantity' => $quantity,
            'verification_status' => 'verified',
        ]);
    }

    private function order(Client $client, string $sku, array $attributes = []): Order
    {
        $order = Order::create($attributes + [
            'client_id' => $client->id,
            'order_number' => (string) random_int(100000, 999999),
            'customer_name' => 'Zain',
            'customer_phone' => '0500000000',
            'shipping_city' => 'Riyadh',
            'shipping_country' => 'KSA',
            'currency' => 'SAR',
            'total' => 100,
        ]);

        $order->items()->create(['lineitem_name' => "Item {$sku}", 'lineitem_quantity' => 1, 'lineitem_price' => 100, 'lineitem_sku' => $sku]);

        return $order;
    }

    private function shipmentFor(Order $order, ShipmentStatus $status): Shipment
    {
        $shipment = $this->ksaShipment(['status' => $status->value]);
        $placeholder = $shipment->order;
        $shipment->update(['order_id' => $order->id]);
        $placeholder->forceDelete();

        return $shipment;
    }

    public function test_a_client_is_warned_at_three_two_one_and_none(): void
    {
        [, $client] = $this->client('ACME');
        $this->product($client, 'PLENTY', 4);
        $three = $this->product($client, 'THREE', 3);
        $this->product($client, 'ONE', 1);
        $this->product($client, 'NONE', 0);
        $this->product($client, 'BELOW', -2);
        $this->product($client, 'MARKED', 9, ['is_out_of_stock' => true]);
        $this->product($client, 'REJECTED', 0, ['verification_status' => 'rejected']);

        $left = collect(app(StockAlerts::class)->forClient($client))->pluck('left', 'sku')->all();

        $this->assertEquals(['BELOW' => 0, 'NONE' => 0, 'ONE' => 1, 'THREE' => 3, 'MARKED' => 0], $left);

        $three->update(['quantity' => 10]);
        $this->assertArrayNotHasKey('THREE', collect(app(StockAlerts::class)->forClient($client))->pluck('left', 'sku')->all());
    }

    public function test_only_fulfilment_clients_are_warned(): void
    {
        [, $dropshipper] = $this->client('DROP', ['dropshipper']);
        $this->product($dropshipper, 'NONE', 0);

        $this->assertSame([], app(StockAlerts::class)->forClient($dropshipper));
        $this->assertSame([], app(StockAlerts::class)->forStaff());
    }

    public function test_the_warning_reaches_the_client_and_the_team_but_not_other_clients(): void
    {
        [$user, $client] = $this->client('ACME');
        [$other] = $this->client('OTHR');
        $this->product($client, 'LOW', 2);

        $this->actingAs($user)->get('/portal')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stockAlerts.audience', 'client')
            ->where('stockAlerts.products.0.sku', 'LOW')
            ->where('stockAlerts.products.0.left', 2)
            ->missing('stockAlerts.products.0.client'));

        $this->actingAs($other)->get('/portal')->assertInertia(fn (AssertableInertia $page) => $page->where('stockAlerts', null));

        $this->actingAs($this->staff(['view orders']))->get('/orders')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stockAlerts.audience', 'staff')
            ->where('stockAlerts.products.0.left', 2)
            ->where('stockAlerts.products.0.client.company_name', 'Client ACME'));
    }

    public function test_the_warning_comes_back_at_the_next_sign_in(): void
    {
        [$user, $client] = $this->client('ACME');
        $this->product($client, 'NONE', 0);

        $session = fn () => $this->actingAs($user)->get('/portal')->viewData('page')['props']['stockAlerts']['session'];

        $first = $session();
        $this->assertSame($first, $session());

        $this->post('/logout');
        $this->assertNotSame($first, $session());
    }

    public function test_orders_waiting_on_a_product_with_none_left_are_marked(): void
    {
        [$user, $client] = $this->client('ACME');
        $this->product($client, 'NONE', 0);
        $this->product($client, 'LOW', 1);

        $waiting = $this->order($client, 'NONE');
        $inStock = $this->order($client, 'LOW');
        $cancelled = $this->order($client, 'NONE', ['fulfillment_status' => 'cancelled']);
        $booked = $this->order($client, 'NONE');
        $this->shipmentFor($booked, ShipmentStatus::PENDING);
        $onItsWay = $this->order($client, 'NONE');
        $this->shipmentFor($onItsWay, ShipmentStatus::IN_TRANSIT);
        $scannedOut = $this->order($client, 'NONE');
        $shipment = $this->shipmentFor($scannedOut, ShipmentStatus::PENDING);
        StockScan::create([
            'shipment_id' => $shipment->id,
            'order_id' => $scannedOut->id,
            'direction' => StockScan::OUT,
            'rider_id' => $this->makeInventoryManager()->id,
            'pieces' => 1,
            'occurred_at' => now(),
            'client_uuid' => (string) Str::uuid(),
        ]);

        $marked = fn ($response) => collect($response->json('data'))
            ->filter(fn ($order) => $order['out_of_stock_items'] !== [])
            ->pluck('id')->sort()->values()->all();

        $portal = $this->actingAs($user)->getJson('/portal/api/orders?per_page=50')->assertOk();
        $this->assertSame([$waiting->id, $booked->id], $marked($portal));
        $this->assertSame('Item NONE', collect($portal->json('data'))->firstWhere('id', $waiting->id)['out_of_stock_items'][0]['name']);

        $admin = $this->actingAs($this->staff(['view orders']))->getJson('/api/orders?per_page=50')->assertOk();
        $this->assertSame([$waiting->id, $booked->id], $marked($admin));

        $this->actingAs($user)->getJson("/portal/api/orders/{$waiting->id}")->assertJsonCount(1, 'out_of_stock_items');
        $this->actingAs($this->staff(['view orders']))->getJson("/api/orders/{$inStock->id}")->assertJsonCount(0, 'out_of_stock_items');

        // Stock arrives: the mark clears by itself.
        ClientProduct::where('sku', 'NONE')->update(['quantity' => 5]);
        $this->assertSame([], $marked($this->actingAs($user)->getJson('/portal/api/orders?per_page=50')));
    }

    public function test_another_clients_product_with_the_same_sku_does_not_mark_the_order(): void
    {
        [$user, $client] = $this->client('ACME');
        [, $other] = $this->client('OTHR');
        $this->product($client, 'SAME', 5);
        $this->product($other, 'SAME', 0);
        $this->order($client, 'SAME');

        $this->actingAs($user)->getJson('/portal/api/orders')->assertJsonPath('data.0.out_of_stock_items', []);
    }
}

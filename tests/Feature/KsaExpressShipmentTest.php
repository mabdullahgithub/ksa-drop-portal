<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * KSA Express (ksadrop_express) is our own courier: no external API, the
 * waybill number is minted locally as KSD + YYMM + 6-digit sequence.
 */
class KsaExpressShipmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('edit orders');
        Permission::findOrCreate('view orders');

        Queue::fake();
        Http::preventStrayRequests();
    }

    private function actor(): User
    {
        $role = Role::create(['name' => 'role-' . uniqid()]);
        $role->givePermissionTo(['edit orders', 'view orders']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeOrder(string $paymentMethod = 'COD'): Order
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
            'shipping_country' => 'SA',
            'currency' => 'SAR',
            'total' => 100.0,
            'payment_method' => $paymentMethod,
            'financial_status' => 'pending',
        ]);

        $order->items()->create([
            'lineitem_name' => 'Test item',
            'lineitem_quantity' => 2,
            'lineitem_price' => 50.0,
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

    private function prefix(): string
    {
        return 'KSD' . now()->format('ym');
    }

    public function test_single_create_mints_a_ksd_tracking_number_and_keeps_the_confirmed_receiver(): void
    {
        $order = $this->makeOrder();

        $response = $this->actingAs($this->actor())->postJson('/api/shipments', [
            'order_id' => $order->id,
            'warehouse_id' => $this->warehouse()->id,
            'courier' => 'ksadrop_express',
            'receiver_name' => 'Edited Name',
            'receiver_phone' => '0555555555',
        ]);

        $response->assertCreated();

        $shipment = Shipment::firstOrFail();
        $this->assertSame('ksadrop_express', $shipment->courier);
        $this->assertSame($this->prefix() . '000001', $shipment->tracking_number);
        $this->assertSame(ShipmentStatus::INFO_RECEIVED->value, $shipment->status);
        $this->assertSame('Edited Name', $shipment->api_response['receiver']['name']);
        $this->assertSame('0555555555', $shipment->api_response['receiver']['phone']);
    }

    public function test_bulk_create_assigns_consecutive_unique_numbers(): void
    {
        $orders = [$this->makeOrder(), $this->makeOrder(), $this->makeOrder()];

        $response = $this->actingAs($this->actor())->postJson('/api/shipments/bulk', [
            'order_ids' => array_map(fn (Order $o) => $o->id, $orders),
            'warehouse_id' => $this->warehouse()->id,
            'courier' => 'ksadrop_express',
        ]);

        $response->assertOk();
        $this->assertCount(3, $response->json('created'), json_encode($response->json('failed')));

        $this->assertSame(
            [$this->prefix() . '000001', $this->prefix() . '000002', $this->prefix() . '000003'],
            Shipment::orderBy('tracking_number')->pluck('tracking_number')->all(),
        );
    }

    public function test_sequence_continues_from_the_highest_existing_number(): void
    {
        $order = $this->makeOrder();
        Shipment::create([
            'order_id' => $order->id,
            'courier' => 'ksadrop_express',
            'tracking_number' => $this->prefix() . '000041',
            'txlogistic_id' => 'seed-1',
            'status' => ShipmentStatus::DELIVERED->value,
        ]);

        $this->actingAs($this->actor())->postJson('/api/shipments', [
            'order_id' => $this->makeOrder()->id,
            'warehouse_id' => $this->warehouse()->id,
            'courier' => 'ksadrop_express',
        ])->assertCreated();

        $this->assertTrue(Shipment::where('tracking_number', $this->prefix() . '000042')->exists());
    }

    public function test_tracking_refresh_keeps_stored_status_and_history(): void
    {
        $order = $this->makeOrder();
        $history = [[
            'status' => 'info_received',
            'description' => 'Shipment created',
            'location' => 'Riyadh',
            'timestamp' => now()->toDateTimeString(),
            'raw_status' => 'info_received',
        ]];

        $shipment = Shipment::create([
            'order_id' => $order->id,
            'courier' => 'ksadrop_express',
            'tracking_number' => $this->prefix() . '000001',
            'txlogistic_id' => $order->order_number,
            'status' => ShipmentStatus::INFO_RECEIVED->value,
            'tracking_history' => $history,
        ]);

        $this->actingAs($this->actor())->postJson("/api/shipments/{$shipment->id}/track")->assertOk();

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::INFO_RECEIVED->value, $shipment->status);
        $this->assertCount(1, $shipment->tracking_history);
    }

    public function test_cancel_succeeds_without_calling_any_api(): void
    {
        $this->actingAs($this->actor())->postJson('/api/shipments', [
            'order_id' => $this->makeOrder()->id,
            'warehouse_id' => $this->warehouse()->id,
            'courier' => 'ksadrop_express',
        ])->assertCreated();

        $shipment = Shipment::firstOrFail();

        $this->actingAs($this->actor())
            ->postJson("/api/shipments/{$shipment->id}/cancel", ['reason' => 'Customer request'])
            ->assertOk();

        $this->assertSame(ShipmentStatus::CANCELLED->value, $shipment->fresh()->status);
    }

    public function test_waybill_renders_as_a_4x6_label(): void
    {
        Storage::fake('local');

        $this->actingAs($this->actor())->postJson('/api/shipments', [
            'order_id' => $this->makeOrder()->id,
            'warehouse_id' => $this->warehouse()->id,
            'courier' => 'ksadrop_express',
        ])->assertCreated();

        $shipment = Shipment::firstOrFail();

        $response = $this->actingAs($this->actor())->postJson("/api/shipments/{$shipment->id}/invoice");
        $response->assertCreated();

        $pdf = Storage::disk('local')->get($response->json('invoice.file_path'));
        // 4in x 6in = 288 x 432 pt.
        $this->assertMatchesRegularExpression('#/MediaBox \[0\.0+ 0\.0+ 288\.0+ 432\.0+\]#', $pdf);
    }

    public function test_waybill_stays_on_one_page_with_worst_case_data(): void
    {
        Storage::fake('local');

        $long = str_repeat('Very Long Text ', 30);
        $order = $this->makeOrder();
        $order->update(['order_number' => 'ORD-' . str_repeat('9', 40), 'total' => 1234567.89]);

        for ($i = 0; $i < 40; $i++) {
            $order->items()->create([
                'lineitem_name' => "Product {$i} {$long}",
                'variant_name' => 'Variant ' . $long,
                'lineitem_quantity' => 3,
                'lineitem_price' => 10.0,
            ]);
        }

        $warehouse = $this->warehouse();
        $warehouse->update(['address' => mb_substr('Warehouse ' . $long, 0, 255), 'phone' => '+966 50 111 2229 ext 12345']);

        $this->actingAs($this->actor())->postJson('/api/shipments', [
            'order_id' => $order->id,
            'warehouse_id' => $warehouse->id,
            'courier' => 'ksadrop_express',
            'receiver_name' => mb_substr($long, 0, 255),
            'receiver_phone' => '+966 55 555 5555',
            'receiver_address' => mb_substr($long . $long, 0, 500),
            'receiver_province' => mb_substr('Province ' . $long, 0, 255),
            'receiver_city' => mb_substr($long, 0, 255),
            'receiver_area' => mb_substr($long, 0, 255),
            'remark' => mb_substr($long, 0, 200),
        ])->assertCreated();

        $shipment = Shipment::firstOrFail();

        $response = $this->actingAs($this->actor())->postJson("/api/shipments/{$shipment->id}/invoice");
        $response->assertCreated();

        $pdf = Storage::disk('local')->get($response->json('invoice.file_path'));
        $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf), 'Waybill must fit on a single page.');
    }

    /**
     * Long but real-world data (long Saudi addresses, big orders, a full
     * note) must print in full — shrunk to fit, never cut — on one page.
     */
    public function test_waybill_prints_long_real_world_data_on_one_page(): void
    {
        Storage::fake('local');

        $order = $this->makeOrder();
        $order->update(['order_number' => 'SHOPIFY-10293847561-KSA-001', 'total' => 18450.75]);

        foreach (range(1, 12) as $i) {
            $order->items()->create([
                'lineitem_name' => "Premium Stainless Steel Insulated Water Bottle {$i}",
                'variant_name' => '1 Litre / Matte Black',
                'lineitem_quantity' => 2,
                'lineitem_price' => 99.0,
            ]);
        }

        $warehouse = $this->warehouse();
        $warehouse->update([
            'address' => 'Building 7420, King Fahd Road, Al Olaya District, Unit 12, Warehouse Complex B',
            'phone' => '+966 50 111 2229',
        ]);

        $this->actingAs($this->actor())->postJson('/api/shipments', [
            'order_id' => $order->id,
            'warehouse_id' => $warehouse->id,
            'courier' => 'ksadrop_express',
            'receiver_name' => 'Abdulrahman Mohammed Abdullah Al Qahtani Al Dosari',
            'receiver_phone' => '+966 55 555 5555',
            'receiver_address' => 'Building No. 8228, Prince Mohammed Bin Abdulaziz Road (Tahlia Street), Additional No. 3344, '
                . 'Unit 17, Third Floor, Apartment 12, Near Al Andalus Mall and the King Abdullah Sports City Main Gate, Opposite Jarir Bookstore',
            'receiver_province' => 'Makkah Al Mukarramah Region',
            'receiver_city' => 'Jeddah Al Rawdah District',
            'receiver_area' => 'Al Rawdah',
            'receiver_post_code' => '23435',
            'remark' => 'Please call the customer 30 minutes before arrival. Deliver only between 4 PM and 9 PM. '
                . 'Gate code 4471. Leave with the building security if the customer is not answering the phone.',
        ])->assertCreated();

        $shipment = Shipment::firstOrFail();

        $response = $this->actingAs($this->actor())->postJson("/api/shipments/{$shipment->id}/invoice");
        $response->assertCreated();

        $pdf = Storage::disk('local')->get($response->json('invoice.file_path'));
        $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf), 'Waybill must fit on a single page.');
    }
    /**
     * Arabic fields are wrapped and shaped by hand (ArabicShaper); the label
     * must still fit on one page.
     */
    public function test_waybill_prints_arabic_joined_and_on_one_page(): void
    {
        Storage::fake('local');

        $order = $this->makeOrder();
        $order->items()->create([
            'lineitem_name' => 'مصباح تخييم متعدد الوظائف قابل للتمديد والطي',
            'lineitem_quantity' => 1,
            'lineitem_price' => 169.0,
        ]);

        $this->actingAs($this->actor())->postJson('/api/shipments', [
            'order_id' => $order->id,
            'warehouse_id' => $this->warehouse()->id,
            'courier' => 'ksadrop_express',
            'receiver_name' => 'AlsahliGhalab - الامين البدراني',
            'receiver_phone' => '+966555494463',
            'receiver_address' => str_repeat('حي الخليج، شارع الأمير محمد بن عبدالعزيز 15، ', 6),
            'receiver_city' => 'الرياض',
            'remark' => 'الرجاء الاتصال قبل الوصول',
        ])->assertCreated();

        $shipment = Shipment::firstOrFail();

        $response = $this->actingAs($this->actor())->postJson("/api/shipments/{$shipment->id}/invoice");
        $response->assertCreated();

        $pdf = Storage::disk('local')->get($response->json('invoice.file_path'));
        $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf), 'Waybill must fit on a single page.');
    }
}

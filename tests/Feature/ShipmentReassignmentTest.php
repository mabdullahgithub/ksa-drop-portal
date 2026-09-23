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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Handing a cancelled order to a different courier. This went wrong on
 * MARK00005: the order was booked with KSA Express, cancelled, then booked
 * with iMile — iMile printed a waybill, our insert died on the old row's
 * unique txlogistic_id, and iMile's scans then landed on the cancelled KSA
 * Express shipment, which also kept the order on the KSA Express tab.
 */
class ShipmentReassignmentTest extends TestCase
{
    use RefreshDatabase;

    private const IMILE_PARTNER_CODE = 'C21063665436054';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('edit orders');
        Permission::findOrCreate('view orders');

        config()->set('services.imile', [
            'customer_id' => self::IMILE_PARTNER_CODE,
            'api_key'     => 'TEST_API_KEY',
            'base_url'    => 'https://openapi.imile.com',
            'time_zone'   => '+3',
        ]);

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

    private function makeOrder(): Order
    {
        $order = Order::create([
            'order_number'      => 'MARK' . random_int(10000, 99999),
            'customer_name'     => 'abdullah - ksadrop',
            'customer_phone'    => '0500000000',
            'shipping_name'     => 'abdullah - ksadrop',
            'shipping_phone'    => '0500000000',
            'shipping_province' => 'Riyadh',
            'shipping_city'     => 'Riyadh',
            'shipping_address1' => 'Al Suwaidi District',
            'shipping_country'  => 'SA',
            'currency'          => 'SAR',
            'total'             => 130.0,
            'payment_method'    => 'COD',
            'financial_status'  => 'pending',
        ]);

        $order->items()->create([
            'lineitem_name'     => 'Hair straightner joy',
            'lineitem_quantity' => 1,
            'lineitem_price'    => 130.0,
        ]);

        return $order->fresh('items');
    }

    private function warehouse(): Warehouse
    {
        return Warehouse::create([
            'name'         => 'Riyadh WH',
            'contact_name' => 'KSA',
            'phone'        => '0501112229',
            'province'     => 'Riyadh',
            'city'         => 'Riyadh',
            'address'      => 'Al Suwaidi',
            'post_code'    => '12345',
            'country_code' => 'KSA',
        ]);
    }

    private function cancelledShipment(Order $order, string $courier, string $trackingNumber): Shipment
    {
        return Shipment::create([
            'order_id'        => $order->id,
            'courier'         => $courier,
            'tracking_number' => $trackingNumber,
            'txlogistic_id'   => $order->order_number,
            'status'          => ShipmentStatus::CANCELLED->value,
            'cancelled_at'    => now(),
        ]);
    }

    public function test_cancelled_order_can_be_booked_with_another_courier(): void
    {
        $order = $this->makeOrder();
        $this->cancelledShipment($order, 'ksadrop_express', 'KSD2609000001');

        $this->actingAs($this->actor())->postJson('/api/shipments', [
            'order_id'     => $order->id,
            'warehouse_id' => $this->warehouse()->id,
            'courier'      => 'ksadrop_express',
        ])->assertCreated();

        $shipments = Shipment::where('order_id', $order->id)->get();
        $this->assertCount(2, $shipments);

        // Same courier twice, so the second booking needs its own order number.
        $new = $shipments->firstWhere('status', ShipmentStatus::INFO_RECEIVED->value);
        $this->assertSame($order->order_number . '-R2', $new->txlogistic_id);
    }

    public function test_a_different_courier_keeps_the_plain_order_number(): void
    {
        $order = $this->makeOrder();
        $this->cancelledShipment($order, 'imile', '6092226331889');

        $this->actingAs($this->actor())->postJson('/api/shipments', [
            'order_id'     => $order->id,
            'warehouse_id' => $this->warehouse()->id,
            'courier'      => 'ksadrop_express',
        ])->assertCreated();

        $new = Shipment::where('order_id', $order->id)->where('courier', 'ksadrop_express')->firstOrFail();
        $this->assertSame($order->order_number, $new->txlogistic_id);
    }

    public function test_imile_push_does_not_land_on_another_couriers_cancelled_shipment(): void
    {
        $order = $this->makeOrder();
        $cancelled = $this->cancelledShipment($order, 'ksadrop_express', 'KSD2609000001');

        $this->postJson('/webhooks/imile/tracking', [
            'partnerCode' => self::IMILE_PARTNER_CODE,
            'param'       => [
                'billNo'           => '6092226331889',
                'orderNo'          => $order->order_number,
                'latestStatus'     => 'RecevieOrder',
                'latestStatusTime' => '2026-09-23T00:42:18+08:00',
                'locus'            => [[
                    'latestSite'       => 'Riyadh Distribution Center',
                    'latestStatus'     => 'RecevieOrder',
                    'latestStatusTime' => '2026-09-23T00:42:18+08:00',
                    'locusDetailed'    => 'Received.',
                    'locusType'        => 'scan',
                ]],
            ],
            'sign'      => 'NOT_VERIFIED_PER_IMILE_SUPPORT',
            'timestamp' => '2026-09-23T00:42:18+08:00',
        ])->assertOk();

        $cancelled->refresh();
        $this->assertSame(ShipmentStatus::CANCELLED->value, $cancelled->status);
        $this->assertEmpty($cancelled->tracking_history ?? []);
    }

    public function test_an_order_whose_only_shipment_is_cancelled_counts_as_unassigned(): void
    {
        $order = $this->makeOrder();
        $this->cancelledShipment($order, 'ksadrop_express', 'KSD2609000001');

        $actor = $this->actingAs($this->actor());

        $ksaExpressTab = $actor->getJson('/api/orders?has_shipment=1&assigned_to=ksa_express')->assertOk();
        $this->assertEmpty($ksaExpressTab->json('data'));

        $unassignedTab = $actor->getJson('/api/orders?has_shipment=0')->assertOk();
        $this->assertSame([$order->id], array_column($unassignedTab->json('data'), 'id'));

        $stats = $actor->getJson('/api/orders/statistics')->assertOk();
        $this->assertSame(0, $stats->json('ksa_express_orders'));
        $this->assertSame(1, $stats->json('unassigned_orders'));
    }
}

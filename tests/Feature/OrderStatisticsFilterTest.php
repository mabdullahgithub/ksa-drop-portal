<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The stat cards on the orders page follow the filter bar: each card group
 * ignores only the filter it drives, and none follow the All / Assigned tab.
 */
class OrderStatisticsFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $viewOrders = Permission::findOrCreate('view orders');
        Role::findOrCreate('admin')->givePermissionTo($viewOrders);

        Tag::create(['name' => 'VIP', 'color' => '#ff0000']);
        Tag::create(['name' => 'عاجل', 'color' => '#00ff00']);

        // Riyadh: 2 delivered (one VIP), 1 unassigned VIP + عاجل. Jeddah: 1 in transit.
        $this->order('riyadh', 100, ['VIP'], ['delivered']);
        $this->order('الرياض', 50, [], ['cancelled', 'delivered']); // re-shipped: counts once per status
        $this->order('RIYADH', 30, ['VIP', 'عاجل']);
        $this->order('Jeddah', 20, ['VIP'], ['in_transit']);
    }

    private function order(string $city, float $total, array $tags = [], array $shipmentStatuses = []): Order
    {
        $order = Order::create([
            'order_number'     => (string) random_int(100000, 999999),
            'customer_name'    => 'Zain',
            'customer_phone'   => '0500000000',
            'shipping_city'    => $city,
            'shipping_country' => 'KSA',
            'currency'         => 'SAR',
            'total'            => $total,
            'tags'             => $tags,
        ]);

        foreach ($shipmentStatuses as $status) {
            $order->shipments()->create(['courier' => 'jnt', 'status' => $status, 'txlogistic_id' => uniqid('TX')]);
        }

        return $order;
    }

    private function stats(string $query = ''): array
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $this->actingAs($admin)
            ->getJson('/api/orders/statistics'.($query ? "?{$query}" : ''))
            ->assertOk()
            ->json();
    }

    private function statusCounts(array $stats): array
    {
        return collect($stats['by_shipment_status'])->pluck('count', 'status')->map(fn ($c) => (int) $c)->all();
    }

    private function tagCounts(array $stats): array
    {
        return collect($stats['by_tag'])->pluck('count', 'name')->all();
    }

    public function test_unfiltered_stats(): void
    {
        $stats = $this->stats();

        $this->assertSame(4, $stats['total_orders']);
        $this->assertSame(3, $stats['assigned_orders']);
        $this->assertSame(1, $stats['unassigned_orders']);
        $this->assertEquals(200, $stats['total_revenue']);
        $this->assertEqualsCanonicalizing(['delivered' => 2, 'cancelled' => 1, 'in_transit' => 1], $this->statusCounts($stats));
        $this->assertSame(['VIP' => 3, 'عاجل' => 1], $this->tagCounts($stats));
    }

    public function test_city_filter_narrows_every_card(): void
    {
        $stats = $this->stats('cities=Riyadh');

        $this->assertSame(3, $stats['total_orders']);
        $this->assertSame(2, $stats['assigned_orders']);
        $this->assertSame(1, $stats['unassigned_orders']);
        $this->assertEqualsCanonicalizing(['delivered' => 2, 'cancelled' => 1], $this->statusCounts($stats));
        $this->assertSame(['VIP' => 2, 'عاجل' => 1], $this->tagCounts($stats));
    }

    public function test_cards_ignore_their_own_filter_and_the_tab(): void
    {
        $stats = $this->stats('shipment_status=delivered&tags=VIP&has_shipment=0');

        // Totals: delivered AND VIP → only the first Riyadh order; the tab is ignored.
        $this->assertSame(1, $stats['total_orders']);
        $this->assertSame(1, $stats['assigned_orders']);

        // Status cards drop the status filter but keep the tag filter.
        $this->assertEqualsCanonicalizing(['delivered' => 1, 'in_transit' => 1], $this->statusCounts($stats));

        // Tag cards drop the tag filter but keep the status filter.
        $this->assertSame(['VIP' => 1, 'عاجل' => 0], $this->tagCounts($stats));
    }
}

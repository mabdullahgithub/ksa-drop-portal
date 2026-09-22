<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The stat cards on the client portal orders page follow the filter bar: each
 * card group ignores only the filter it drives, none follow the All / Assigned
 * tab, and every number stays scoped to the client's own orders.
 */
class PortalOrderStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');

        Tag::create(['name' => 'VIP', 'color' => '#ff0000']);
        Tag::create(['name' => 'عاجل', 'color' => '#00ff00']);

        [$this->user, $this->client] = $this->makeClient('AAA');

        // Riyadh: 1 delivered VIP, 1 delivered (re-shipped), 1 unassigned VIP + عاجل.
        // Jeddah: 1 in transit VIP.
        $this->order($this->client, 'riyadh', 100, ['VIP'], ['delivered']);
        $this->order($this->client, 'الرياض', 50, [], ['cancelled', 'delivered']);
        $this->order($this->client, 'RIYADH', 30, ['VIP', 'عاجل']);
        $this->order($this->client, 'Jeddah', 20, ['VIP'], ['in_transit']);
    }

    private function makeClient(string $shortId): array
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $client = Client::create([
            'user_id'         => $user->id,
            'company_name'    => "Client {$shortId}",
            'short_id'        => $shortId,
            'client_types'    => ['fulfilment'],
            'portal_features' => ['orders'],
        ]);

        return [$user, $client];
    }

    private function order(Client $client, string $city, float $total, array $tags = [], array $shipmentStatuses = []): Order
    {
        $order = Order::create([
            'client_id'        => $client->id,
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
        return $this->actingAs($this->user)
            ->getJson('/portal/api/orders/statistics'.($query ? "?{$query}" : ''))
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

    public function test_unfiltered_stats_cover_only_this_clients_orders(): void
    {
        [, $other] = $this->makeClient('BBB');
        $this->order($other, 'Dammam', 500, ['VIP'], ['delivered']);

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

    public function test_tag_cards_ignore_the_single_tag_filter_they_drive(): void
    {
        // Clicking a tag card sends ?tag=VIP; the other cards must keep their counts.
        $stats = $this->stats('tag=VIP');

        $this->assertSame(3, $stats['total_orders']);
        $this->assertSame(['VIP' => 3, 'عاجل' => 1], $this->tagCounts($stats));
    }

    public function test_a_client_without_the_orders_feature_is_denied(): void
    {
        $this->client->update(['portal_features' => []]);

        $this->actingAs($this->user)
            ->getJson('/portal/api/orders/statistics')
            ->assertForbidden();
    }
}

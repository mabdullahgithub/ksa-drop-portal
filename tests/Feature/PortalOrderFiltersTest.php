<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Date and City filters on the client portal orders page, scoped to the
 * client's own orders.
 */
class PortalOrderFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('client');
    }

    private function client(string $shortId): array
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

    private function order(Client $client, string $city, string $createdAt = '2026-09-10 12:00:00'): Order
    {
        $order = Order::create([
            'client_id'        => $client->id,
            'order_number'     => (string) random_int(100000, 999999),
            'customer_name'    => 'Zain',
            'customer_phone'   => '0500000000',
            'shipping_city'    => $city,
            'shipping_country' => 'KSA',
            'currency'         => 'SAR',
            'total'            => 100,
        ]);
        $order->forceFill(['created_at' => $createdAt])->save();

        return $order;
    }

    public function test_city_options_only_list_the_clients_own_cities(): void
    {
        [$user, $client] = $this->client('AAA');
        [, $other] = $this->client('BBB');

        $this->order($client, 'riyadh');
        $this->order($client, 'الرياض');
        $this->order($other, 'Dammam');

        $cities = $this->actingAs($user)
            ->getJson(route('portal.api.orders.filter-options'))
            ->assertOk()
            ->json('cities');

        $this->assertCount(1, $cities);
        $this->assertSame('Riyadh', $cities[0]['value']);
        $this->assertSame(2, $cities[0]['count']);
    }

    public function test_city_and_date_filters(): void
    {
        [$user, $client] = $this->client('AAA');
        [, $other] = $this->client('BBB');

        $inRange = $this->order($client, 'RIYADH', '2026-09-10 12:00:00');
        $this->order($client, 'Riyadh', '2026-08-01 12:00:00'); // outside the dates
        $this->order($client, 'Jeddah', '2026-09-10 12:00:00');  // other city
        $this->order($other, 'riyadh', '2026-09-10 12:00:00');   // other client

        $ids = $this->actingAs($user)
            ->getJson(route('portal.api.orders', [
                'cities'     => 'Riyadh',
                'start_date' => '2026-09-01',
                'end_date'   => '2026-09-30',
                'tz'         => 'Asia/Riyadh',
            ]))
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$inRange->id], $ids);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The orders City filter groups every spelling of a city — case, spacing,
 * "Al"/"ال" prefixes, English and Arabic — under one option.
 */
class OrderCityFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $viewOrders = Permission::findOrCreate('view orders');
        Role::findOrCreate('admin')->givePermissionTo($viewOrders);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function makeOrder(?string $city): Order
    {
        return Order::create([
            'order_number'     => (string) random_int(100000, 999999),
            'customer_name'    => 'Zain',
            'customer_phone'   => '0500000000',
            'shipping_city'    => $city,
            'shipping_country' => 'KSA',
            'currency'         => 'SAR',
            'total'            => 100,
        ]);
    }

    public function test_riyadh_matches_every_english_and_arabic_spelling(): void
    {
        $riyadh = collect(['riyadh', 'RIYADH', 'Riyadh', 'Ar-Riyadh', 'الرياض', 'Riyadh City'])
            ->map(fn ($city) => $this->makeOrder($city)->id);

        $this->makeOrder('Jeddah');
        $this->makeOrder('جدة');
        $this->makeOrder('Riyadh Al Khabra'); // a different town
        $this->makeOrder(null);

        $ids = $this->actingAs($this->admin())
            ->getJson('/api/orders?cities=Riyadh&per_page=100')
            ->assertOk()
            ->json('data.*.id');

        $this->assertEqualsCanonicalizing($riyadh->all(), $ids);
    }

    public function test_multiple_cities_and_arabic_input(): void
    {
        $this->makeOrder('RIYADH');
        $this->makeOrder('jeddah');
        $this->makeOrder('Dammam');

        $this->actingAs($this->admin())
            ->getJson('/api/orders?cities='.urlencode('الرياض,Jeddah'))
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_filter_options_group_cities_with_counts(): void
    {
        $this->makeOrder('riyadh');
        $this->makeOrder('RIYADH');
        $this->makeOrder('الرياض');
        $this->makeOrder('Jeddah');
        $this->makeOrder('-');

        $cities = $this->actingAs($this->admin())
            ->getJson('/api/orders/filter-options')
            ->assertOk()
            ->json('cities');

        $this->assertCount(2, $cities);
        $this->assertSame('Riyadh', $cities[0]['value']);
        $this->assertSame('الرياض', $cities[0]['ar']);
        $this->assertSame(3, $cities[0]['count']);
        $this->assertEqualsCanonicalizing(['riyadh', 'RIYADH', 'الرياض'], $cities[0]['keywords']);
        $this->assertSame('Jeddah', $cities[1]['value']);
    }
}

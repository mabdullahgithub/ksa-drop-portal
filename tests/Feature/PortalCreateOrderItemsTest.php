<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalCreateOrderItemsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('client');
    }

    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        Client::create([
            'user_id'         => $user->id,
            'company_name'    => 'Test Client',
            'short_id'        => 'TST',
            'client_types'    => ['fulfilment'],
            'portal_features' => ['orders'],
        ]);

        return $user;
    }

    private function base(array $extra = []): array
    {
        return array_merge(['customer_name' => 'Ali', 'customer_phone' => '+966500000000'], $extra);
    }

    public function test_order_can_have_several_products_and_skus(): void
    {
        $response = $this->actingAs($this->clientUser())->postJson('/portal/api/orders', $this->base([
            'total' => 300,
            'items' => [
                ['name' => 'Perfume A', 'quantity' => 2, 'sku' => 'PA-1', 'unit_price' => 100],
                ['name' => 'Perfume B', 'quantity' => 1, 'sku' => 'PB-1', 'unit_price' => 100],
            ],
        ]));

        $response->assertOk()->assertJson(['success' => true]);

        $items = Order::firstOrFail()->items()->orderBy('id')->get();
        $this->assertCount(2, $items);
        $this->assertSame(['PA-1', 'PB-1'], $items->pluck('lineitem_sku')->all());
        $this->assertSame([2, 1], $items->pluck('lineitem_quantity')->all());
    }

    public function test_line_prices_add_back_up_to_the_order_total(): void
    {
        $this->actingAs($this->clientUser())->postJson('/portal/api/orders', $this->base([
            'total' => 100,
            'items' => [
                ['name' => 'A', 'quantity' => 1],
                ['name' => 'B', 'quantity' => 1],
                ['name' => 'C', 'quantity' => 1],
            ],
        ]))->assertOk();

        $sum = Order::firstOrFail()->items->sum(fn ($i) => $i->lineitem_price * $i->lineitem_quantity);
        $this->assertEqualsWithDelta(100.0, $sum, 0.001);
    }

    public function test_single_lineitem_fields_still_work(): void
    {
        $this->actingAs($this->clientUser())->postJson('/portal/api/orders', $this->base([
            'total'           => 250,
            'lineitem_name'   => 'Blue T-Shirt',
            'lineitem_quantity' => 2,
            'lineitem_sku'    => 'TS-BL',
        ]))->assertOk();

        $item = Order::firstOrFail()->items()->sole();
        $this->assertSame('Blue T-Shirt', $item->lineitem_name);
        $this->assertSame(2, $item->lineitem_quantity);
        $this->assertEqualsWithDelta(125.0, (float) $item->lineitem_price, 0.001);
    }

    public function test_order_without_products_still_creates(): void
    {
        $this->actingAs($this->clientUser())->postJson('/portal/api/orders', $this->base())->assertOk();

        $this->assertSame(0, Order::firstOrFail()->items()->count());
    }

    public function test_each_item_needs_a_name_and_a_valid_quantity(): void
    {
        $this->actingAs($this->clientUser())->postJson('/portal/api/orders', $this->base([
            'items' => [['sku' => 'X'], ['name' => 'B', 'quantity' => 0]],
        ]))->assertStatus(422)->assertJsonValidationErrors(['items.0.name', 'items.1.quantity']);

        $this->assertSame(0, Order::count());
    }
}

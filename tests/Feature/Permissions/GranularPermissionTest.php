<?php

namespace Tests\Feature\Permissions;

use App\Models\Connector;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\MakesInventory;
use Tests\Feature\Concerns\MakesStaff;
use Tests\TestCase;

/**
 * One permission per button: holding one opens that button and none of the
 * ones that used to come with it.
 */
class GranularPermissionTest extends TestCase
{
    use MakesInventory, MakesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpStaff();
    }

    // ----------------------------------------------------------------- orders

    public function test_a_call_agent_saves_call_outcomes_and_nothing_else(): void
    {
        $agent = $this->staffWith(['view orders', 'view order details', 'update order call status']);
        $order = $this->makeOrder();

        $this->actingAs($agent)->getJson('/api/orders')->assertOk();
        $this->actingAs($agent)->getJson("/api/orders/{$order->id}")->assertOk();
        $this->actingAs($agent)->postJson("/api/orders/{$order->id}/call-status", ['call_status' => 'confirmed'])->assertOk();

        // Everything 'edit orders' used to bring along.
        $this->actingAs($agent)->postJson("/api/orders/{$order->id}/fulfillment-status", ['fulfillment_status' => 'fulfilled'])->assertForbidden();
        $this->actingAs($agent)->postJson("/api/orders/{$order->id}/financial-status", ['financial_status' => 'paid'])->assertForbidden();
        $this->actingAs($agent)->putJson("/api/orders/{$order->id}", ['customer_name' => 'Changed'])->assertForbidden();
        $this->actingAs($agent)->postJson('/api/shipments', ['order_id' => $order->id])->assertForbidden();
        $this->actingAs($agent)->get('/api/orders/export')->assertForbidden();
        $this->actingAs($agent)->deleteJson("/api/orders/{$order->id}")->assertForbidden();
        $this->actingAs($agent)->getJson('/api/orders/statistics')->assertForbidden();
    }

    public function test_the_order_form_checks_each_kind_of_field(): void
    {
        $order = $this->makeOrder();

        $tagger = $this->staffWith(['view orders', 'tag orders']);
        $this->actingAs($tagger)->putJson("/api/orders/{$order->id}", ['tags' => ['urgent']])->assertOk();
        $this->actingAs($tagger)->putJson("/api/orders/{$order->id}", ['customer_name' => 'Changed'])->assertForbidden();
        $this->actingAs($tagger)->putJson("/api/orders/{$order->id}", ['tags' => ['urgent'], 'fulfillment_status' => 'fulfilled'])->assertForbidden();

        $editor = $this->staffWith(['view orders', 'view order details', 'edit orders']);
        $this->actingAs($editor)->putJson("/api/orders/{$order->id}", ['customer_name' => 'Changed'])->assertOk();
        $this->actingAs($editor)->putJson("/api/orders/{$order->id}", ['tags' => ['other']])->assertForbidden();

        $this->assertSame('Changed', $order->fresh()->customer_name);
        $this->assertSame(['urgent'], $order->fresh()->tags);
    }

    public function test_each_bulk_button_is_the_permission_of_its_single_twin(): void
    {
        $order = $this->makeOrder();
        $agent = $this->staffWith(['view orders', 'update order call status']);

        $this->actingAs($agent)->postJson('/api/orders/bulk-update', [
            'order_ids' => [$order->id], 'action' => 'update_call_status', 'call_status' => 'confirmed',
        ])->assertOk();

        foreach ([
            ['action' => 'cancel'],
            ['action' => 'add_tags', 'tags' => ['urgent']],
            ['action' => 'update_fulfillment', 'fulfillment_status' => 'fulfilled'],
            ['action' => 'update_financial', 'financial_status' => 'paid'],
        ] as $payload) {
            $this->actingAs($agent)->postJson('/api/orders/bulk-update', ['order_ids' => [$order->id]] + $payload)->assertForbidden();
        }

        $this->assertSame('confirmed', $order->fresh()->call_status);
        $this->assertNotSame('cancelled', $order->fresh()->fulfillment_status);
    }

    public function test_an_order_is_sent_without_the_panels_the_person_cannot_open(): void
    {
        $order = $this->makeOrder();
        $this->makeShipment($order);

        $this->actingAs($this->staffWith(['view orders', 'view order details']))
            ->getJson("/api/orders/{$order->id}")->assertOk()
            ->assertJsonPath('latest_shipment', null);

        $this->actingAs($this->staffWith(['view orders', 'view order details', 'view shipments']))
            ->getJson("/api/orders/{$order->id}")->assertOk()
            ->assertJsonPath('latest_shipment.order_id', $order->id);
    }

    public function test_each_block_of_numbers_is_sent_only_to_those_who_may_see_it(): void
    {
        $this->makeOrder(null, ['total' => 250]);

        $counts = $this->actingAs($this->staffWith(['view dashboard', 'view dashboard order stats']))
            ->getJson('/api/orders/statistics')->assertOk();
        $counts->assertJsonPath('total_orders', 1)
            ->assertJsonPath('total_revenue', null)
            ->assertJsonPath('by_tag', null)
            ->assertJsonPath('by_shipment_status', null);

        $revenue = $this->actingAs($this->staffWith(['view dashboard', 'view dashboard revenue']))
            ->getJson('/api/orders/statistics')->assertOk();
        $revenue->assertJsonPath('total_orders', null);
        $this->assertEquals(250, $revenue->json('total_revenue'));
    }

    public function test_each_dashboard_chart_is_sent_only_to_those_who_may_see_it(): void
    {
        $client = $this->makeClient('Noor Store');
        $riyadh = $this->makeOrder($client, ['shipping_city' => 'الرياض', 'total' => 300]);
        $this->makeOrder($client, ['shipping_city' => 'riyadh', 'total' => 200]);
        $this->makeOrder($client, ['shipping_city' => 'Jeddah', 'total' => 100]);
        $this->makeShipment($riyadh, 'delivered');

        $this->actingAs($this->staffWith(['view dashboard', 'view dashboard order stats']))
            ->getJson('/api/orders/dashboard-insights')->assertForbidden();

        $cities = $this->actingAs($this->staffWith(['view dashboard', 'view dashboard city heatmap']))
            ->getJson('/api/orders/dashboard-insights')->assertOk()
            ->assertJsonPath('couriers', null)
            ->assertJsonPath('stores', null)
            ->assertJsonPath('cities.total_orders', 3)
            // Both spellings of Riyadh fold into one city; no money without the revenue permission.
            ->assertJsonPath('cities.items.0.name', 'Riyadh')
            ->assertJsonPath('cities.items.0.orders', 2)
            ->assertJsonPath('cities.items.0.delivered', 1)
            ->assertJsonPath('cities.items.0.revenue', null);

        $couriers = $this->actingAs($this->staffWith(['view dashboard', 'view dashboard courier ranking']))
            ->getJson('/api/orders/dashboard-insights')->assertOk()
            ->assertJsonPath('cities', null)
            ->assertJsonPath('couriers.0.courier', 'jnt_express')
            ->assertJsonPath('couriers.0.delivered', 1);
        $this->assertEquals(100, $couriers->json('couriers.0.delivery_rate'));

        $rhythm = $this->actingAs($this->staffWith(['view dashboard', 'view dashboard order rhythm']))
            ->getJson('/api/orders/dashboard-insights')->assertOk()
            ->assertJsonPath('couriers', null)
            ->assertJsonCount(24, 'rhythm.hours')
            ->assertJsonCount(7, 'rhythm.weekdays');
        $this->assertSame(3, array_sum($rhythm->json('rhythm.hours')));
        $this->assertSame(3, array_sum($rhythm->json('rhythm.weekdays')));

        $stores = $this->actingAs($this->staffWith(['view dashboard', 'view dashboard store ranking', 'view dashboard revenue']))
            ->getJson('/api/orders/dashboard-insights?range=7')->assertOk()
            ->assertJsonPath('stores.0.name', 'Noor Store')
            ->assertJsonPath('stores.0.orders', 3)
            ->assertJsonCount(7, 'stores.0.trend');
        $this->assertEquals(600, $stores->json('stores.0.revenue'));
        $this->assertSame(3, array_sum($stores->json('stores.0.trend')));
    }

    // -------------------------------------------------------------- shipments

    public function test_each_shipment_button_is_its_own_permission(): void
    {
        $shipment = $this->makeShipment($this->makeOrder());
        $viewer = $this->staffWith(['view orders', 'view order details', 'view shipments']);

        $this->actingAs($viewer)->getJson("/api/shipments/{$shipment->id}")->assertOk();

        foreach (['cancel', 'escalate', 'return', 'receive-at-hub', 'unassign-rider', 'track', 'invoice'] as $action) {
            $this->actingAs($viewer)->postJson("/api/shipments/{$shipment->id}/{$action}")->assertForbidden();
        }
    }

    // ---------------------------------------------------------------- clients

    public function test_the_client_form_checks_each_section(): void
    {
        $client = $this->makeClient();
        $client->update(['charges' => ['delivery' => 10], 'notes' => 'old']);

        $editor = $this->staffWith(['view client', 'edit client']);

        // Sending a section unchanged is fine: the form always sends it.
        $this->actingAs($editor)->putJson("/api/clients/{$client->id}", [
            'company_name' => 'Renamed', 'charges' => ['delivery' => '10'], 'notes' => 'old',
        ])->assertOk();
        $this->assertSame('Renamed', $client->fresh()->company_name);

        $this->actingAs($editor)->putJson("/api/clients/{$client->id}", ['charges' => ['delivery' => 99]])->assertForbidden();
        $this->actingAs($editor)->putJson("/api/clients/{$client->id}", ['notes' => 'new'])->assertForbidden();
        $this->actingAs($editor)->putJson("/api/clients/{$client->id}", ['portal_features' => ['orders', 'finance']])->assertForbidden();
        $this->assertEquals(10, $client->fresh()->charges['delivery']);

        $this->actingAs($this->staffWith(['view client', 'edit client', 'edit client charges']))
            ->putJson("/api/clients/{$client->id}", ['charges' => ['delivery' => 99]])->assertOk();
        $this->assertEquals(99, $client->fresh()->charges['delivery']);
    }

    public function test_the_buttons_on_a_client_are_each_their_own_permission(): void
    {
        $client = $this->makeClient();
        $editor = $this->staffWith(['view client', 'view client details', 'edit client']);

        $this->actingAs($editor)->patchJson("/api/clients/{$client->id}/status", ['status' => 'suspended'])->assertForbidden();
        $this->actingAs($editor)->postJson("/api/clients/{$client->id}/send-reset-link")->assertForbidden();
        $this->actingAs($editor)->getJson("/api/clients/{$client->id}/payments")->assertForbidden();
        $this->actingAs($editor)->getJson("/api/clients/{$client->id}/products")->assertForbidden();
        $this->actingAs($editor)->get('/api/clients/export')->assertForbidden();
        $this->actingAs($editor)->deleteJson("/api/clients/{$client->id}")->assertForbidden();

        $this->actingAs($this->staffWith(['view client', 'change client status']))
            ->patchJson("/api/clients/{$client->id}/status", ['status' => 'suspended'])->assertOk();
    }

    public function test_a_clients_revenue_is_only_sent_to_those_who_may_see_it(): void
    {
        $client = $this->makeClient();
        $this->makeOrder($client, ['total' => 400]);

        $this->actingAs($this->staffWith(['view client', 'view client details']))
            ->get("/client/{$client->id}")
            ->assertInertia(fn (Assert $page) => $page->where('client.total_revenue', null)->where('client.orders_count', null));

        $this->actingAs($this->staffWith(['view client', 'view client details', 'view client revenue']))
            ->get("/client/{$client->id}")
            ->assertInertia(fn (Assert $page) => $page->where('client.total_revenue', 400)->where('client.orders_count', 1));
    }

    // -------------------------------------------------------------- inventory

    public function test_the_product_form_checks_each_kind_of_field(): void
    {
        $product = Product::create(['handle' => 'shirt', 'title' => 'Shirt', 'status' => 'active', 'published' => true]);
        $editor = $this->staffWith(['view inventory', 'edit inventory']);

        // Status and published ride along unchanged with the edit form.
        $this->actingAs($editor)->putJson("/api/products/{$product->id}", ['title' => 'Blue shirt', 'status' => 'active', 'published' => true])->assertOk();
        $this->actingAs($editor)->putJson("/api/products/{$product->id}", ['status' => 'archived'])->assertForbidden();
        $this->actingAs($editor)->putJson("/api/products/{$product->id}", ['published' => false])->assertForbidden();

        $this->actingAs($this->staffWith(['view inventory', 'publish products']))
            ->putJson("/api/products/{$product->id}", ['published' => false])->assertOk();
        $this->actingAs($this->staffWith(['view inventory', 'change product status']))
            ->putJson("/api/products/{$product->id}", ['status' => 'archived'])->assertOk();

        $product->refresh();
        $this->assertSame('Blue shirt', $product->title);
        $this->assertSame('archived', $product->status);
        $this->assertFalse((bool) $product->published);
    }

    // --------------------------------------------------------------- requests

    public function test_each_queue_of_the_requests_page_is_its_own_permission(): void
    {
        $this->unlockRequests();

        [, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $this->stockRequest($product, 300);
        $this->detailsRequest($product, ['name' => 'Navy Mug']);
        $this->makeProduct($client, ['verification_status' => 'pending', 'sku' => 'NEW-1']);

        $clerk = $this->staffWith(['view requests', 'view stock requests']);

        $this->actingAs($clerk)->get('/requests')->assertOk();
        $this->actingAs($clerk)->getJson('/api/requests/stock')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($clerk)->getJson('/api/requests/details')->assertForbidden();
        $this->actingAs($clerk)->getJson('/api/requests/products')->assertForbidden();

        // The counts leave out the queues they cannot open.
        $this->actingAs($clerk)->getJson('/api/requests/counts')
            ->assertOk()
            ->assertJsonPath('counts.stock', 1)
            ->assertJsonPath('counts.details', null)
            ->assertJsonPath('counts.products', null);

        // The page alone opens no queue.
        $visitor = $this->staffWith(['view requests']);
        $this->actingAs($visitor)->get('/requests')->assertOk();
        $this->actingAs($visitor)->getJson('/api/requests/counts')->assertForbidden();

        // And a queue without the page is not a way in.
        $this->actingAs($this->staffWith(['view stock requests']))->get('/requests')->assertForbidden();
    }

    public function test_each_button_on_a_request_is_its_own_permission(): void
    {
        $this->unlockRequests();

        [, $client] = $this->fulfilmentClient();
        $product = $this->makeProduct($client);
        $pending = $this->makeProduct($client, ['verification_status' => 'pending', 'sku' => 'NEW-1']);

        // Each button's route, what it is sent, and the one permission that opens it.
        $buttons = [
            'review product requests' => fn () => ["/api/requests/products/{$pending->id}/review", ['action' => 'pending']],
            'approve stock requests' => fn () => ['/api/requests/stock/' . $this->stockRequest($product, 300)->id . '/approve', ['units' => 1]],
            'reject stock requests' => fn () => ['/api/requests/stock/' . $this->stockRequest($product, 300)->id . '/reject', ['reason' => 'No']],
            'bulk approve stock requests' => fn () => ['/api/requests/stock/bulk-approve', ['ids' => [$this->stockRequest($product, 300)->id]]],
            'bulk reject stock requests' => fn () => ['/api/requests/stock/bulk-reject', ['ids' => [$this->stockRequest($product, 300)->id], 'reason' => 'No']],
            'approve detail requests' => fn () => ['/api/requests/details/' . $this->detailsRequest($product, ['notes' => 'Fragile'])->id . '/approve', []],
            'reject detail requests' => fn () => ['/api/requests/details/' . $this->detailsRequest($product, ['notes' => 'Fragile'])->id . '/reject', ['reason' => 'No']],
            'bulk approve detail requests' => fn () => ['/api/requests/details/bulk-approve', ['ids' => [$this->detailsRequest($product, ['notes' => 'Fragile'])->id]]],
            'bulk reject detail requests' => fn () => ['/api/requests/details/bulk-reject', ['ids' => [$this->detailsRequest($product, ['notes' => 'Fragile'])->id], 'reason' => 'No']],
        ];

        foreach (array_keys($buttons) as $held) {
            $person = $this->staffWith(['view requests', 'view product requests', 'view stock requests', 'view detail requests', $held]);

            foreach ($buttons as $permission => $button) {
                [$url, $payload] = $button();
                $response = $this->actingAs($person)->postJson($url, $payload);

                $permission === $held
                    ? $response->assertOk()
                    : $response->assertForbidden();
            }
        }
    }

    // ------------------------------------------------------------- connectors

    public function test_each_connectors_settings_are_their_own_permission(): void
    {
        $imile = Connector::where('key', 'imile')->firstOrFail();
        $jnt = Connector::where('key', 'jnt_express')->firstOrFail();

        $user = $this->staffWith(['view apps', 'configure imile connector']);

        $this->actingAs($user)->getJson("/api/connectors/{$imile->id}/settings")->assertOk();
        $this->actingAs($user)->getJson("/api/connectors/{$jnt->id}/settings")->assertForbidden();
        $this->actingAs($user)->putJson("/api/connectors/{$jnt->id}/settings", ['settings' => [['key' => 'a', 'value' => 'b']]])->assertForbidden();
        // Testing and revealing are permissions of their own on top.
        $this->actingAs($user)->postJson("/api/connectors/{$imile->id}/test")->assertForbidden();
        $this->actingAs($user)->postJson("/api/connectors/{$imile->id}/settings/reveal", ['key' => 'api_key'])->assertForbidden();
        $this->actingAs($user)->getJson('/api/warehouses')->assertForbidden();
    }

    public function test_a_connector_without_a_settings_page_is_left_to_full_access(): void
    {
        $shopify = Connector::where('key', 'shopify')->firstOrFail();

        $this->actingAs($this->staffWith(['view apps', 'configure jnt connector', 'configure imile connector']))
            ->getJson("/api/connectors/{$shopify->id}/settings")->assertForbidden();
        $this->actingAs($this->fullAccessUser('developer'))
            ->getJson("/api/connectors/{$shopify->id}/settings")->assertOk();
    }

    // ---------------------------------------------------------- notifications

    public function test_the_bell_is_a_permission_for_staff_and_free_for_clients(): void
    {
        $noBell = $this->staffWith(['view orders']);

        $this->actingAs($noBell)->getJson('/api/notifications')->assertForbidden();
        $this->actingAs($noBell)->get('/notifications')->assertForbidden();
        // Polled from every page: a zero, not an error.
        $this->actingAs($noBell)->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('count', 0);
        $this->actingAs($noBell)->postJson('/api/notifications/read-all')->assertForbidden();

        $viewer = $this->staffWith(['view notifications']);
        $this->actingAs($viewer)->getJson('/api/notifications')->assertOk();
        $this->actingAs($viewer)->postJson('/api/notifications/read-all')->assertForbidden();

        $client = User::factory()->create();
        $client->assignRole('client');
        $this->actingAs($client)->getJson('/api/notifications')->assertOk();
        $this->actingAs($client)->postJson('/api/notifications/read-all')->assertOk();
    }

    // --------------------------------------------------------------- settings

    public function test_each_part_of_a_persons_own_account_is_a_permission(): void
    {
        $user = $this->staffWith(['view settings', 'edit profile']);

        $this->actingAs($user)->get('/settings')->assertOk();
        $this->actingAs($user)->put('/settings/password', [])->assertForbidden();
        $this->actingAs($user)->post('/settings/two-factor/enable')->assertForbidden();
        $this->actingAs($user)->put('/settings/notifications', [])->assertForbidden();
        $this->actingAs($user)->delete('/settings/avatar')->assertForbidden();

        $this->actingAs($this->staffWith(['view orders']))->get('/settings')->assertForbidden();
    }

    public function test_someone_with_nothing_lands_on_a_page_that_needs_nothing(): void
    {
        $this->actingAs($this->staffWith([]))->get('/')->assertRedirect(route('errors.forbidden'));
        $this->actingAs($this->staffWith(['view riders']))->get('/')->assertRedirect(route('riders'));
    }
}

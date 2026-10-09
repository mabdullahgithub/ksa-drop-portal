<?php

namespace Tests\Feature\Permissions;

use App\Models\Connector;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\MakesStaff;
use Tests\TestCase;

/**
 * One permission per button: holding one opens that button and none of the
 * ones that used to come with it.
 */
class GranularPermissionTest extends TestCase
{
    use MakesStaff, RefreshDatabase;

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

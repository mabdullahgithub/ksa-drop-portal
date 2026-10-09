<?php

namespace Tests\Feature\Permissions;

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\ClientProduct;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Services\Shipping\Drivers\KsaDropExpressDriver;
use App\Support\ClientAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MakesRiders;
use Tests\Feature\Concerns\MakesStaff;
use Tests\TestCase;

/**
 * A team member can be limited to the clients they handle: they then see only
 * those clients' orders, shipments, products and payments, everywhere.
 */
class ClientAccessTest extends TestCase
{
    use MakesRiders, MakesStaff, RefreshDatabase;

    private Client $mine;

    private Client $theirs;

    private Order $myOrder;

    private Order $theirOrder;

    private Order $nobodysOrder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpStaff();

        $this->mine = $this->makeClient('Mine Trading');
        $this->theirs = $this->makeClient('Theirs Trading');
        $this->myOrder = $this->makeOrder($this->mine, ['total' => 100]);
        $this->theirOrder = $this->makeOrder($this->theirs, ['total' => 900]);
        $this->nobodysOrder = $this->makeOrder(null, ['total' => 50]);
    }

    /** Someone who handles only `mine`. */
    private function limited(array $permissions): User
    {
        return $this->limitTo($this->staffWith($permissions), $this->mine);
    }

    // ----------------------------------------------------------------- orders

    public function test_the_orders_list_holds_only_their_clients_orders(): void
    {
        $ids = $this->actingAs($this->limited(['view orders']))
            ->getJson('/api/orders')->assertOk()->json('data.*.id');

        $this->assertSame([$this->myOrder->id], $ids);
    }

    public function test_someone_not_limited_sees_every_order(): void
    {
        $ids = $this->actingAs($this->staffWith(['view orders']))
            ->getJson('/api/orders')->assertOk()->json('data.*.id');

        $this->assertEqualsCanonicalizing(
            [$this->myOrder->id, $this->theirOrder->id, $this->nobodysOrder->id],
            $ids,
        );
    }

    public function test_full_access_is_never_limited(): void
    {
        $developer = $this->limitTo($this->fullAccessUser('developer'), $this->mine);

        $this->actingAs($developer)->getJson('/api/orders')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_another_clients_order_is_not_found(): void
    {
        $user = $this->limited(['view orders', 'view order details']);

        $this->actingAs($user)->getJson("/api/orders/{$this->myOrder->id}")->assertOk();
        $this->actingAs($user)->getJson("/api/orders/{$this->theirOrder->id}")->assertNotFound();
        $this->actingAs($user)->getJson("/api/orders/{$this->nobodysOrder->id}")->assertNotFound();
    }

    public function test_the_numbers_count_only_their_clients_orders(): void
    {
        $stats = $this->actingAs($this->limited(['view orders', 'view order stats', 'view dashboard revenue']))
            ->getJson('/api/orders/statistics')->assertOk();

        $stats->assertJsonPath('total_orders', 1);
        $this->assertEquals(100, $stats->json('total_revenue'));
    }

    public function test_the_export_holds_only_their_clients_orders(): void
    {
        $csv = $this->actingAs($this->limited(['view orders', 'export orders']))
            ->get('/api/orders/export')->assertOk()->streamedContent();

        $this->assertStringContainsString($this->myOrder->order_number, $csv);
        $this->assertStringNotContainsString($this->theirOrder->order_number, $csv);
    }

    public function test_a_bulk_action_cannot_reach_another_clients_order(): void
    {
        $this->actingAs($this->limited(['view orders', 'cancel orders']))
            ->postJson('/api/orders/bulk-update', [
                'order_ids' => [$this->myOrder->id, $this->theirOrder->id],
                'action' => 'cancel',
            ])->assertOk()->assertJsonPath('updated_count', 1);

        $this->assertSame('cancelled', $this->myOrder->fresh()->fulfillment_status);
        $this->assertNotSame('cancelled', $this->theirOrder->fresh()->fulfillment_status);
    }

    public function test_another_clients_order_cannot_be_deleted(): void
    {
        $this->actingAs($this->limited(['view orders', 'delete orders']))
            ->postJson('/api/orders/bulk-delete', ['order_ids' => [$this->theirOrder->id]]);

        $this->assertNull($this->theirOrder->fresh()->deleted_at);
    }

    // -------------------------------------------------------------- shipments

    public function test_another_clients_shipment_and_waybill_are_not_found(): void
    {
        $mine = $this->makeShipment($this->myOrder);
        $theirs = $this->makeShipment($this->theirOrder);

        $user = $this->limited(['view orders', 'view order details', 'view shipments', 'cancel shipments']);

        $this->actingAs($user)->getJson("/api/shipments/{$mine->id}")->assertOk();
        $this->actingAs($user)->getJson("/api/shipments/{$theirs->id}")->assertNotFound();
        $this->actingAs($user)->postJson("/api/shipments/{$theirs->id}/cancel")->assertNotFound();

        $ids = $this->actingAs($user)->getJson('/api/shipments')->assertOk()->json('data.*.id');
        $this->assertSame([$mine->id], $ids);
    }

    // ---------------------------------------------------------------- clients

    public function test_the_client_list_holds_only_their_clients(): void
    {
        $user = $this->limited(['view client', 'view client details', 'view client stats']);

        $ids = $this->actingAs($user)->getJson('/api/clients')->assertOk()->json('data.*.id');
        $this->assertSame([$this->mine->id], $ids);

        $this->actingAs($user)->getJson('/api/clients/statistics')->assertOk()->assertJsonPath('total_clients', 1);
        $this->actingAs($user)->get("/client/{$this->mine->id}")->assertOk();
        $this->actingAs($user)->get("/client/{$this->theirs->id}")->assertNotFound();
    }

    public function test_another_clients_products_and_payments_are_out_of_reach(): void
    {
        ClientProduct::create(['client_id' => $this->theirs->id, 'product_code' => 'T-001', 'name' => 'Their product']);
        ClientPayment::create(['client_id' => $this->theirs->id, 'amount' => 10, 'paid_at' => now(), 'created_by' => User::factory()->create()->id]);

        $user = $this->limited(['view client', 'view client details', 'view client products', 'view client payments', 'record client payments']);

        $this->actingAs($user)->getJson("/api/clients/{$this->theirs->id}/products")->assertNotFound();
        $this->actingAs($user)->getJson("/api/clients/{$this->theirs->id}/payments")->assertNotFound();
        $this->actingAs($user)->postJson("/api/clients/{$this->theirs->id}/payments", ['amount' => 5, 'paid_at' => now()->toDateString()])->assertNotFound();
    }

    public function test_another_clients_portal_cannot_be_opened(): void
    {
        $user = $this->limited(['view client', 'impersonate client']);

        $this->actingAs($user)->post("/impersonate/{$this->theirs->id}")->assertNotFound();
        $this->actingAs($user)->post("/impersonate/{$this->mine->id}")->assertRedirect('/portal');
    }

    public function test_a_client_they_create_becomes_one_of_theirs(): void
    {
        $user = $this->limited(['view client', 'create client']);

        $id = $this->actingAs($user)->postJson('/api/clients', [
            'client_types' => ['dropshipper'],
            'company_name' => 'Brand New',
            'email' => 'brand-new@example.com',
            'name' => 'Brand New Owner',
        ])->assertCreated()->json('client.id');

        $this->assertEqualsCanonicalizing([$this->mine->id, $id], ClientAccess::idsFor($user->fresh()));
    }

    // ---------------------------------------------------------------- nothing

    public function test_with_no_client_assigned_they_see_no_orders_at_all(): void
    {
        $user = $this->limitTo($this->staffWith(['view orders']));

        $this->actingAs($user)->getJson('/api/orders')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_work_outside_a_staff_request_is_never_limited(): void
    {
        // A queue job or a console command: nobody switched the limit on.
        $this->assertNull(app(ClientAccess::class)->ids());
        $this->assertSame(3, Order::count());
    }

    public function test_numbers_in_a_sequence_never_repeat_one_taken_by_another_client(): void
    {
        // Another client's waybill, KSA Express parcel and client code.
        $shipment = $this->makeShipment($this->theirOrder);
        $shipment->update(['courier' => KsaDropExpressDriver::KEY, 'tracking_number' => 'KSD' . now()->format('ym') . '000007']);
        Invoice::create([
            'order_id' => $this->theirOrder->id, 'shipment_id' => $shipment->id, 'type' => 'shipping',
            'invoice_number' => 'SHP-' . date('Y') . '-00041', 'status' => 'issued',
        ]);
        $this->theirs->update(['short_id' => 'ZETA']);

        // What someone limited to `mine` is working under.
        app(ClientAccess::class)->restrictTo([$this->mine->id]);
        $this->assertSame(0, Invoice::count());

        $this->assertSame('SHP-' . date('Y') . '-00042', Invoice::generateInvoiceNumber('shipping'));
        $this->assertSame('KSD' . now()->format('ym') . '000008', app(KsaDropExpressDriver::class)->generateTrackingNumber());
        $this->assertNotSame('ZETA', Client::generateClientId('Zeta'));
    }

    public function test_riders_are_the_same_for_everyone(): void
    {
        $rider = $this->makeRider();
        $this->makeShipment($this->theirOrder, 'out_for_delivery')->update(['rider_id' => $rider->id, 'courier' => 'ksadrop_express']);

        $held = $this->actingAs($this->limited(['view riders', 'view rider stats']))
            ->getJson('/api/riders')->assertOk()->json('riders.0.stats.held');

        // The parcel belongs to a client they do not handle, and still counts.
        $this->assertSame(1, $held);
        $this->assertNull(app(ClientAccess::class)->ids());
    }

    // -------------------------------------------------------------- assigning

    public function test_an_admin_chooses_which_clients_someone_handles(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->fullAccessUser())
            ->put("/team-management/users/{$target->id}", ['client_access' => 'assigned', 'client_ids' => [$this->mine->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame([$this->mine->id], ClientAccess::idsFor($target->fresh()));

        $this->actingAs($this->fullAccessUser())
            ->put("/team-management/users/{$target->id}", ['client_access' => 'all'])
            ->assertSessionHasNoErrors();

        $this->assertNull(ClientAccess::idsFor($target->fresh()));
    }

    public function test_assigning_clients_is_its_own_permission(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->staffWith(['view users', 'edit users', 'assign user roles']))
            ->put("/team-management/users/{$target->id}", ['client_access' => 'assigned', 'client_ids' => [$this->mine->id]])
            ->assertSessionHasNoErrors();

        $this->assertNull(ClientAccess::idsFor($target->fresh()));
    }

    public function test_someone_limited_can_only_hand_out_their_own_clients(): void
    {
        $manager = $this->limited(['view users', 'edit users', 'assign client access']);
        $target = User::factory()->create();

        $this->actingAs($manager)
            ->put("/team-management/users/{$target->id}", ['client_access' => 'all'])
            ->assertSessionHasErrors('client_access');
        $this->actingAs($manager)
            ->put("/team-management/users/{$target->id}", ['client_access' => 'assigned', 'client_ids' => [$this->theirs->id]])
            ->assertSessionHasErrors('client_ids');
        $this->assertNull(ClientAccess::idsFor($target->fresh()));

        $this->actingAs($manager)
            ->put("/team-management/users/{$target->id}", ['client_access' => 'assigned', 'client_ids' => [$this->mine->id]])
            ->assertSessionHasNoErrors();
        $this->assertSame([$this->mine->id], ClientAccess::idsFor($target->fresh()));
    }

    public function test_someone_limited_does_not_take_away_clients_they_cannot_see(): void
    {
        $manager = $this->limited(['view users', 'edit users', 'assign client access']);
        $target = $this->limitTo(User::factory()->create(), $this->theirs);

        $this->actingAs($manager)
            ->put("/team-management/users/{$target->id}", ['client_access' => 'assigned', 'client_ids' => [$this->mine->id]])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing([$this->mine->id, $this->theirs->id], ClientAccess::idsFor($target->fresh()));
    }

    public function test_a_user_made_by_someone_limited_starts_with_no_clients(): void
    {
        $this->actingAs($this->limited(['view users', 'create users']))
            ->post('/team-management/users', ['name' => 'New Person', 'email' => 'new@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame([], ClientAccess::idsFor(User::where('email', 'new@example.com')->firstOrFail()));
    }

    public function test_nobody_changes_their_own_clients(): void
    {
        $manager = $this->limited(['view users', 'edit users', 'assign client access']);

        $this->actingAs($manager)
            ->put("/team-management/users/{$manager->id}", ['client_access' => 'assigned', 'client_ids' => []])
            ->assertSessionHasErrors('client_access');

        $this->assertSame([$this->mine->id], ClientAccess::idsFor($manager->fresh()));
    }
}

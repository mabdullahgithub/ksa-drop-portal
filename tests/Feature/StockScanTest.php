<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\Product;
use App\Models\Rider;
use App\Models\Shipment;
use App\Models\StockScan;
use App\Models\User;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\MakesRiders;
use Tests\TestCase;

/**
 * An inventory manager scans parcels OUT of the warehouse (stock goes down)
 * and IN again (stock goes back up). The parcel itself, and the rider app,
 * are left as they are.
 */
class StockScanTest extends TestCase
{
    use MakesRiders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRiderPermissions();
        Storage::fake('local');
        Notification::fake();
    }

    private function scan(string $token, string $direction, string $code, ?string $uuid = null)
    {
        return $this->asRider($token)->postJson('/rider/api/stock/scan', [
            'direction' => $direction,
            'code' => $code,
            'client_uuid' => $uuid ?? (string) Str::uuid(),
            'entry_method' => 'camera',
        ]);
    }

    private function catalogProduct(string $sku, int $stock): Product
    {
        return Product::create([
            'handle' => 'product-' . Str::lower($sku),
            'title' => "Product {$sku}",
            'variant_sku' => $sku,
            'variant_inventory_qty' => $stock,
        ]);
    }

    /**
     * A parcel whose one item (2 units) carries this SKU.
     */
    private function parcelOf(string $sku, array $attributes = []): Shipment
    {
        $shipment = $this->ksaShipment($attributes);
        $shipment->order->items()->first()->update(['lineitem_sku' => $sku]);

        return $shipment;
    }

    public function test_the_admin_picks_the_role_and_a_rider_is_the_default(): void
    {
        $admin = $this->staff();

        $this->actingAs($admin)->postJson('/api/riders', ['name' => 'Ahmed Khan', 'phone' => '0551234567'])
            ->assertCreated()
            ->assertJsonPath('rider.role', 'rider')
            ->assertJsonPath('rider.stock_today', null);

        $this->actingAs($admin)->postJson('/api/riders', ['name' => 'Khalid Stock', 'phone' => '0557654321', 'role' => 'inventory_manager'])
            ->assertCreated()
            ->assertJsonPath('rider.role', 'inventory_manager')
            ->assertJsonPath('rider.stock_today.out.parcels', 0)
            ->assertJsonPath('rider.stock_today.in.parcels', 0);

        $this->actingAs($admin)->postJson('/api/riders', ['name' => 'X', 'phone' => '0550000001', 'role' => 'boss'])
            ->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_a_rider_holding_parcels_cannot_become_an_inventory_manager(): void
    {
        $admin = $this->staff();
        $rider = $this->makeRider(['phone' => '+966551234567']);
        $shipment = $this->ksaShipment(['rider_id' => $rider->id, 'status' => ShipmentStatus::OUT_FOR_DELIVERY->value]);

        $payload = ['name' => $rider->name, 'phone' => '0551234567', 'role' => 'inventory_manager'];

        $this->actingAs($admin)->putJson("/api/riders/{$rider->id}", $payload)
            ->assertStatus(422)->assertJsonValidationErrors('role');

        $shipment->update(['rider_id' => null]);

        $this->actingAs($admin)->putJson("/api/riders/{$rider->id}", $payload)
            ->assertOk()->assertJsonPath('rider.role', 'inventory_manager');
    }

    public function test_each_role_is_kept_to_its_own_half_of_the_app(): void
    {
        $manager = $this->signedInDevice($this->makeInventoryManager());
        $rider = $this->signedInDevice($this->makeRider());
        $shipment = $this->ksaShipment();

        // The page tells the app which half to show.
        $this->asRider($manager)->get('/rider/app')->assertOk()->assertSee('"role":"inventory_manager"', false);
        $this->asRider($rider)->get('/rider/app')->assertOk()->assertSee('"role":"rider"', false);
        $this->asRider($manager)->getJson('/rider/api/me')->assertOk()->assertJsonPath('rider.role', 'inventory_manager');

        // A manager can't take a parcel…
        $this->asRider($manager)->postJson('/rider/api/claim', ['code' => $shipment->tracking_number, 'client_uuid' => (string) Str::uuid()])
            ->assertStatus(403)->assertJsonPath('code', 'wrong_role');
        $this->asRider($manager)->getJson('/rider/api/parcels')->assertStatus(403);
        $this->assertNull($shipment->fresh()->rider_id);

        // …and a rider can't move stock.
        $this->scan($rider, 'out', $shipment->tracking_number)->assertStatus(403)->assertJsonPath('code', 'wrong_role');
        $this->asRider($rider)->getJson('/rider/api/stock')->assertStatus(403);
        $this->assertSame(0, StockScan::count());
    }

    public function test_out_takes_the_matched_items_off_stock_and_flags_the_rest(): void
    {
        $token = $this->signedInDevice($this->makeInventoryManager());

        $client = Client::create([
            'user_id' => User::factory()->create()->id,
            'company_name' => 'Test Company',
            'short_id' => 'TEST',
            'client_types' => ['fulfilment'],
        ]);
        $catalog = $this->catalogProduct('CAT-1', 10);
        $linked = ClientProduct::create(['client_id' => $client->id, 'product_code' => 'TEST-001', 'name' => 'Linked', 'quantity' => 20]);
        $own = ClientProduct::create(['client_id' => $client->id, 'product_code' => 'TEST-002', 'name' => 'Own SKU', 'sku' => 'OWN-1', 'quantity' => 5]);
        // The same SKU in the catalogue: the client's own stock wins.
        $this->catalogProduct('OWN-1', 50);

        $shipment = $this->parcelOf('CAT-1');
        $shipment->order->forceFill(['client_id' => $client->id])->save();
        $shipment->order->items()->createMany([
            ['lineitem_name' => 'Linked item', 'lineitem_quantity' => 3, 'lineitem_price' => 10, 'client_product_id' => $linked->id],
            ['lineitem_name' => 'Own item', 'lineitem_quantity' => 1, 'lineitem_price' => 10, 'lineitem_sku' => 'OWN-1'],
            ['lineitem_name' => 'Mystery item', 'lineitem_quantity' => 4, 'lineitem_price' => 10, 'lineitem_sku' => 'NOPE'],
            ['lineitem_name' => 'Gift card', 'lineitem_quantity' => 1, 'lineitem_price' => 10, 'lineitem_requires_shipping' => false],
        ]);

        $this->scan($token, 'out', $shipment->tracking_number)
            ->assertOk()
            ->assertJsonPath('result', 'out')
            ->assertJsonPath('scan.direction', 'out')
            ->assertJsonPath('scan.pieces', 10)
            ->assertJsonPath('scan.unmatched', 1)
            ->assertJsonPath('scan.scanned_by', 'Khalid Stock')
            ->assertJsonPath('scan.parcel.tracking_number', $shipment->tracking_number)
            ->assertJsonPath('scan.parcel.courier_label', 'KSA Express')
            ->assertJsonCount(4, 'scan.items')
            ->assertJsonPath('scan.items.0.sku', 'CAT-1')
            ->assertJsonPath('scan.items.0.quantity', 2)
            ->assertJsonPath('scan.items.0.matched', true)
            ->assertJsonPath('scan.items.0.stock_after', 8)
            ->assertJsonPath('scan.items.3.name', 'Mystery item')
            ->assertJsonPath('scan.items.3.matched', false)
            ->assertJsonPath('scan.items.3.stock_after', null);

        $this->assertSame(8, $catalog->fresh()->variant_inventory_qty);
        $this->assertSame(17, $linked->fresh()->quantity);
        $this->assertSame(4, $own->fresh()->quantity);
        $this->assertSame(50, Product::where('variant_sku', 'OWN-1')->value('variant_inventory_qty'));
    }

    public function test_a_parcel_goes_out_once_and_a_retried_scan_counts_once(): void
    {
        $token = $this->signedInDevice($this->makeInventoryManager());
        $product = $this->catalogProduct('CAT-1', 10);
        $shipment = $this->parcelOf('CAT-1');
        $uuid = (string) Str::uuid();

        $first = $this->scan($token, 'out', $shipment->tracking_number, $uuid)->assertOk()->assertJsonPath('result', 'out');

        // The answer was lost on a weak signal and the app sent it again.
        $this->scan($token, 'out', $shipment->tracking_number, $uuid)
            ->assertOk()
            ->assertJsonPath('result', 'out')
            ->assertJsonPath('scan.id', $first->json('scan.id'));

        // Scanned again later, by mistake.
        $this->scan($token, 'out', $shipment->tracking_number)
            ->assertOk()
            ->assertJsonPath('result', 'already_out')
            ->assertJsonPath('parcel.tracking_number', $shipment->tracking_number)
            ->assertJsonPath('last_scan.scanned_by', 'Khalid Stock');

        $this->assertSame(1, StockScan::count());
        $this->assertSame(8, $product->fresh()->variant_inventory_qty);
    }

    public function test_a_parcel_whose_delivery_is_over_is_not_handed_out(): void
    {
        $token = $this->signedInDevice($this->makeInventoryManager());
        $product = $this->catalogProduct('CAT-1', 10);
        $shipment = $this->parcelOf('CAT-1', ['status' => ShipmentStatus::CANCELLED->value]);

        $this->scan($token, 'out', $shipment->tracking_number)
            ->assertOk()
            ->assertJsonPath('result', 'finished')
            ->assertJsonPath('message', 'This parcel is Cancelled. Do not hand it out.');

        $this->assertSame(0, StockScan::count());
        $this->assertSame(10, $product->fresh()->variant_inventory_qty);
    }

    public function test_in_puts_back_exactly_what_went_out(): void
    {
        $token = $this->signedInDevice($this->makeInventoryManager());
        $product = $this->catalogProduct('CAT-1', 10);
        $other = $this->catalogProduct('CAT-2', 10);
        $shipment = $this->parcelOf('CAT-1');

        $this->scan($token, 'out', $shipment->tracking_number)->assertJsonPath('result', 'out');
        $this->assertSame(8, $product->fresh()->variant_inventory_qty);

        // The order is edited while the parcel is out.
        $shipment->order->items()->first()->update(['lineitem_sku' => 'CAT-2', 'lineitem_quantity' => 9]);

        $this->scan($token, 'in', $shipment->tracking_number)
            ->assertOk()
            ->assertJsonPath('result', 'in')
            ->assertJsonPath('scan.pieces', 2)
            ->assertJsonPath('scan.items.0.sku', 'CAT-1')
            ->assertJsonPath('scan.items.0.stock_after', 10);

        $this->assertSame(10, $product->fresh()->variant_inventory_qty);
        $this->assertSame(10, $other->fresh()->variant_inventory_qty);

        $this->scan($token, 'in', $shipment->tracking_number)->assertOk()->assertJsonPath('result', 'already_in');
        $this->assertSame(10, $product->fresh()->variant_inventory_qty);

        // Back on the shelf, it can go out again — as the order stands now.
        $this->scan($token, 'out', $shipment->tracking_number)->assertJsonPath('result', 'out')->assertJsonPath('scan.pieces', 9);
        $this->assertSame(1, $other->fresh()->variant_inventory_qty);
    }

    public function test_in_takes_any_parcel_whatever_became_of_it(): void
    {
        $token = $this->signedInDevice($this->makeInventoryManager());
        $product = $this->catalogProduct('CAT-1', 10);

        foreach ([ShipmentStatus::DELIVERED, ShipmentStatus::RETURNED, ShipmentStatus::CANCELLED, ShipmentStatus::ATTEMPT_FAIL, ShipmentStatus::INFO_RECEIVED] as $status) {
            // Never scanned out: it left before the warehouse started scanning.
            $shipment = $this->parcelOf('CAT-1', ['status' => $status->value]);

            $this->scan($token, 'in', $shipment->tracking_number)->assertOk()->assertJsonPath('result', 'in');
        }

        $this->assertSame(20, $product->fresh()->variant_inventory_qty);
    }

    public function test_stock_can_go_below_zero_rather_than_hold_a_parcel_up(): void
    {
        $token = $this->signedInDevice($this->makeInventoryManager());
        $product = $this->catalogProduct('CAT-1', 1);
        $shipment = $this->parcelOf('CAT-1');

        $this->scan($token, 'out', $shipment->tracking_number)
            ->assertJsonPath('result', 'out')
            ->assertJsonPath('scan.items.0.stock_after', -1);

        $this->assertSame(-1, $product->fresh()->variant_inventory_qty);
    }

    public function test_any_couriers_parcel_is_found_by_its_tracking_or_order_number(): void
    {
        $token = $this->signedInDevice($this->makeInventoryManager());
        $product = $this->catalogProduct('CAT-1', 10);

        $jnt = $this->parcelOf('CAT-1', ['courier' => 'jnt_express', 'tracking_number' => 'JT0001234567']);
        $ksa = $this->parcelOf('CAT-1');

        $this->scan($token, 'out', 'jt0001234567')
            ->assertOk()
            ->assertJsonPath('result', 'out')
            ->assertJsonPath('scan.parcel.id', $jnt->id)
            ->assertJsonPath('scan.parcel.courier_label', 'J&T Express');

        $this->scan($token, 'out', '#' . $ksa->order->order_number)
            ->assertOk()
            ->assertJsonPath('result', 'out')
            ->assertJsonPath('scan.parcel.id', $ksa->id);

        $this->scan($token, 'out', 'NOT-A-PARCEL')->assertStatus(404)->assertJsonPath('result', 'not_found');

        $this->assertSame(6, $product->fresh()->variant_inventory_qty);
    }

    public function test_scans_leave_the_parcel_and_its_rider_alone(): void
    {
        $manager = $this->signedInDevice($this->makeInventoryManager());
        $rider = $this->makeRider(['name' => 'Ahmed Khan']);
        $riderToken = $this->signedInDevice($rider);
        $this->catalogProduct('CAT-1', 10);
        $shipment = $this->parcelOf('CAT-1');

        $this->scan($manager, 'out', $shipment->tracking_number)
            ->assertJsonPath('result', 'out')
            ->assertJsonPath('scan.parcel_rider', null);

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::INFO_RECEIVED->value, $shipment->status);
        $this->assertNull($shipment->rider_id);
        $this->assertSame(0, $shipment->events()->count());

        // The rider still takes it by scanning it in their own app.
        $this->asRider($riderToken)->postJson('/rider/api/claim', ['code' => $shipment->tracking_number, 'client_uuid' => (string) Str::uuid()])
            ->assertOk()->assertJsonPath('result', 'claimed');

        // Brought back undelivered: stock is back, the parcel is still theirs.
        $this->scan($manager, 'in', $shipment->tracking_number)
            ->assertJsonPath('result', 'in')
            ->assertJsonPath('scan.parcel_rider', 'Ahmed Khan');

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::OUT_FOR_DELIVERY->value, $shipment->status);
        $this->assertSame($rider->id, $shipment->rider_id);
        $this->assertNull($shipment->hub_received_at);
    }

    public function test_the_manager_sees_todays_scans_and_stays_off_the_riders_numbers(): void
    {
        $manager = $this->makeInventoryManager();
        $token = $this->signedInDevice($manager);
        $this->catalogProduct('CAT-1', 10);
        $first = $this->parcelOf('CAT-1');
        $second = $this->parcelOf('CAT-1');

        $this->scan($token, 'out', $first->tracking_number);
        $this->scan($token, 'out', $second->tracking_number);
        $this->scan($token, 'in', $first->tracking_number);

        $this->asRider($token)->getJson('/rider/api/stock')
            ->assertOk()
            ->assertJsonPath('today.out', ['parcels' => 2, 'pieces' => 4])
            ->assertJsonPath('today.in', ['parcels' => 1, 'pieces' => 2]);

        $this->asRider($token)->getJson('/rider/api/stock/scans')
            ->assertOk()
            ->assertJsonCount(3, 'scans')
            ->assertJsonPath('next_page', null)
            ->assertJsonPath('scans.0.direction', 'in')
            ->assertJsonPath('scans.0.parcel.tracking_number', $first->tracking_number);

        $staff = $this->staff();

        $row = collect($this->actingAs($staff)->getJson('/api/riders')->assertOk()->json('riders'))->firstWhere('id', $manager->id);
        $this->assertSame('inventory_manager', $row['role']);
        $this->assertSame(2, $row['stock_today']['out']['parcels']);
        $this->assertSame(0, $row['stats']['held']);

        $today = now(config('app.business_timezone', 'Asia/Riyadh'))->toDateString();
        $this->actingAs($staff)->getJson("/api/riders/performance?from={$today}&to={$today}")
            ->assertOk()->assertJsonCount(0, 'riders');
    }

    public function test_the_managers_log_is_their_own_in_pages_and_searched_by_order_number(): void
    {
        $token = $this->signedInDevice($this->makeInventoryManager());
        $other = $this->signedInDevice($this->makeInventoryManager(['name' => 'Other Manager']));
        $parcels = collect(range(1, 23))->map(fn () => $this->ksaShipment());

        $parcels->each(fn ($parcel) => $this->scan($token, 'out', $parcel->tracking_number));
        $this->scan($token, 'in', $parcels[0]->tracking_number);
        $this->scan($other, 'out', $this->ksaShipment()->tracking_number);

        // 24 of mine, newest first, twenty to a page.
        $this->asRider($token)->getJson('/rider/api/stock/scans')
            ->assertOk()
            ->assertJsonCount(20, 'scans')
            ->assertJsonPath('next_page', 2)
            ->assertJsonPath('scans.0.direction', 'in');

        $this->asRider($token)->getJson('/rider/api/stock/scans?page=2')
            ->assertOk()
            ->assertJsonCount(4, 'scans')
            ->assertJsonPath('next_page', null)
            ->assertJsonPath('scans.3.parcel.tracking_number', $parcels[0]->tracking_number);

        $this->asRider($token)->getJson('/rider/api/stock/scans?direction=in')->assertOk()->assertJsonCount(1, 'scans');

        // The order number, with or without its "#", or part of it.
        $orderNumber = $parcels[0]->order->order_number;
        $this->asRider($token)->getJson('/rider/api/stock/scans?search=' . urlencode('#' . $orderNumber))
            ->assertOk()
            ->assertJsonCount(2, 'scans')
            ->assertJsonPath('scans.0.parcel.order_number', $orderNumber);
        $this->asRider($token)->getJson('/rider/api/stock/scans?search=' . $parcels[5]->tracking_number)->assertOk()->assertJsonCount(1, 'scans');
        $this->asRider($token)->getJson('/rider/api/stock/scans?search=NOSUCHORDER')->assertOk()->assertJsonCount(0, 'scans');

        $this->asRider($other)->getJson('/rider/api/stock/scans')->assertOk()->assertJsonCount(1, 'scans');
        $this->asRider($this->signedInDevice($this->makeRider()))->getJson('/rider/api/stock/scans')->assertStatus(403);
    }

    public function test_the_portal_lists_the_scans_and_finds_the_ones_with_unlinked_items(): void
    {
        $token = $this->signedInDevice($this->makeInventoryManager());
        $this->catalogProduct('CAT-1', 10);
        $linked = $this->parcelOf('CAT-1');
        $unlinked = $this->ksaShipment();

        $this->scan($token, 'out', $linked->tracking_number);
        $this->scan($token, 'out', $unlinked->tracking_number);
        $this->scan($token, 'in', $linked->tracking_number);

        $this->actingAs($this->staff(['view riders']))->getJson('/api/stock-scans')->assertStatus(403);

        $staff = $this->staff(['view inventory']);

        $this->actingAs($staff)->getJson('/api/stock-scans')
            ->assertOk()
            ->assertJsonCount(3, 'scans')
            ->assertJsonPath('next_page', null)
            ->assertJsonPath('scans.0.direction', 'in')
            ->assertJsonPath('scans.0.scanned_by', 'Khalid Stock');

        $this->actingAs($staff)->getJson('/api/stock-scans?direction=out')->assertOk()->assertJsonCount(2, 'scans');

        $this->actingAs($staff)->getJson('/api/stock-scans?unmatched=1')
            ->assertOk()
            ->assertJsonCount(1, 'scans')
            ->assertJsonPath('scans.0.parcel.tracking_number', $unlinked->tracking_number)
            ->assertJsonPath('scans.0.unmatched', 1);

        $this->actingAs($staff)->getJson('/api/stock-scans?search=' . $linked->order->order_number)->assertOk()->assertJsonCount(2, 'scans');
        $this->actingAs($staff)->getJson('/api/stock-scans?search=CAT-1')->assertOk()->assertJsonCount(2, 'scans');
    }
}

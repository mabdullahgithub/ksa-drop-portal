<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use App\Models\StockMovement;
use App\Services\Inventory\InventoryService;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryStockMovementTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_number'      => (string) random_int(100000, 999999),
            'customer_name'     => 'Zain',
            'customer_phone'    => '0500000000',
            'shipping_name'     => 'Zain',
            'shipping_phone'    => '0500000000',
            'shipping_city'     => 'Riyadh',
            'shipping_address1' => 'Al Suwaidi District',
            'shipping_country'  => 'SA',
            'currency'          => 'SAR',
            'total'             => 150.0,
            'payment_method'    => 'cod',
            'financial_status'  => 'pending',
        ], $attributes));
    }

    private function makeShipment(Order $order, string $status = ShipmentStatus::PENDING->value): Shipment
    {
        return Shipment::create([
            'order_id'        => $order->id,
            'courier'         => 'jnt_express',
            'tracking_number' => 'TRK' . random_int(100000, 999999),
            'txlogistic_id'   => 'TX' . random_int(100000, 999999),
            'status'          => $status,
        ]);
    }

    private function makeCatalogueProduct(int $qty = 10, string $sku = 'SKU-CAT-1'): Product
    {
        return Product::create([
            'handle'                => 'handle-' . random_int(1000, 9999),
            'title'                 => 'Catalogue Widget',
            'variant_sku'           => $sku,
            'variant_price'         => 99.0,
            'variant_inventory_qty' => $qty,
        ]);
    }

    private function makeClientProduct(int $qty = 10, string $sku = 'SKU-CLI-1'): ClientProduct
    {
        $client = Client::create([
            'user_id'         => User::factory()->create()->id,
            'company_name'    => 'Fulfilment Co',
            'short_id'        => 'FC' . random_int(1000, 9999),
            'client_types'    => ['fulfilment'],
            'portal_features' => ['inventory'],
        ]);

        return ClientProduct::create([
            'client_id'           => $client->id,
            'product_code'        => 'CP-' . random_int(100000, 999999),
            'name'                => 'Client Widget',
            'sku'                 => $sku,
            'quantity'            => $qty,
            'verification_status' => 'verified',
        ]);
    }

    public function test_in_transit_deducts_linked_catalogue_stock(): void
    {
        $product = $this->makeCatalogueProduct(10);
        $order   = $this->makeOrder();

        OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Catalogue Widget',
            'lineitem_quantity' => 3,
            'lineitem_price'    => 99.0,
            'product_id'        => $product->id,
        ]);

        $shipment = $this->makeShipment($order);
        $shipment->update(['status' => ShipmentStatus::IN_TRANSIT->value]);

        $this->assertSame(7, $product->fresh()->variant_inventory_qty);
        $this->assertNotNull($shipment->fresh()->stock_deducted_at);

        $movement = StockMovement::where('shipment_id', $shipment->id)->sole();
        $this->assertSame(-3, $movement->quantity);
        $this->assertSame(7, $movement->balance_after);
        $this->assertSame(StockMovement::REASON_SHIPMENT_DISPATCHED, $movement->reason);
    }

    public function test_repeated_dispatch_statuses_deduct_only_once(): void
    {
        $product = $this->makeCatalogueProduct(10);
        $order   = $this->makeOrder();

        OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Catalogue Widget',
            'lineitem_quantity' => 2,
            'lineitem_price'    => 99.0,
            'product_id'        => $product->id,
        ]);

        $shipment = $this->makeShipment($order);

        // The same parcel walking the happy path, plus a duplicate push.
        $shipment->update(['status' => ShipmentStatus::IN_TRANSIT->value]);
        $shipment->update(['status' => ShipmentStatus::OUT_FOR_DELIVERY->value]);
        $shipment->update(['status' => ShipmentStatus::ATTEMPT_FAIL->value]);
        $shipment->update(['status' => ShipmentStatus::OUT_FOR_DELIVERY->value]);
        $shipment->update(['status' => ShipmentStatus::DELIVERED->value]);

        $this->assertSame(8, $product->fresh()->variant_inventory_qty);
        $this->assertSame(1, StockMovement::where('shipment_id', $shipment->id)->count());
    }

    public function test_return_restocks_what_was_dispatched(): void
    {
        $product = $this->makeCatalogueProduct(10);
        $order   = $this->makeOrder();

        OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Catalogue Widget',
            'lineitem_quantity' => 4,
            'lineitem_price'    => 99.0,
            'product_id'        => $product->id,
        ]);

        $shipment = $this->makeShipment($order);
        $shipment->update(['status' => ShipmentStatus::IN_TRANSIT->value]);
        $this->assertSame(6, $product->fresh()->variant_inventory_qty);

        $shipment->markReturned('Customer refused');

        $this->assertSame(10, $product->fresh()->variant_inventory_qty);
        $this->assertNotNull($shipment->fresh()->stock_restocked_at);

        $restock = StockMovement::where('shipment_id', $shipment->id)
            ->where('reason', StockMovement::REASON_SHIPMENT_RETURNED)
            ->sole();
        $this->assertSame(4, $restock->quantity);
        $this->assertSame(10, $restock->balance_after);
    }

    public function test_delivered_shipment_does_not_restock(): void
    {
        $product = $this->makeCatalogueProduct(10);
        $order   = $this->makeOrder();

        OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Catalogue Widget',
            'lineitem_quantity' => 5,
            'lineitem_price'    => 99.0,
            'product_id'        => $product->id,
        ]);

        $shipment = $this->makeShipment($order);
        $shipment->update(['status' => ShipmentStatus::IN_TRANSIT->value]);
        $shipment->markDelivered();

        $this->assertSame(5, $product->fresh()->variant_inventory_qty);
    }

    public function test_cancel_before_dispatch_restocks_nothing(): void
    {
        $product = $this->makeCatalogueProduct(10);
        $order   = $this->makeOrder();

        OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Catalogue Widget',
            'lineitem_quantity' => 5,
            'lineitem_price'    => 99.0,
            'product_id'        => $product->id,
        ]);

        $shipment = $this->makeShipment($order);
        $shipment->markCancelled('Merchant cancelled before pickup');

        $this->assertSame(10, $product->fresh()->variant_inventory_qty);
        $this->assertSame(0, StockMovement::where('shipment_id', $shipment->id)->count());
    }

    public function test_shopify_line_resolves_client_stock_by_sku(): void
    {
        $clientProduct = $this->makeClientProduct(20, 'SKU-CLI-9');
        $order = $this->makeOrder(['client_id' => $clientProduct->client_id]);

        // A Shopify-synced line: SKU only, no product link.
        OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Client Widget',
            'lineitem_quantity' => 6,
            'lineitem_price'    => 50.0,
            'lineitem_sku'      => 'SKU-CLI-9',
        ]);

        $shipment = $this->makeShipment($order);
        $shipment->update(['status' => ShipmentStatus::IN_TRANSIT->value]);

        $this->assertSame(14, $clientProduct->fresh()->quantity);

        $shipment->markReturned('Undeliverable');
        $this->assertSame(20, $clientProduct->fresh()->quantity);
    }

    public function test_client_sku_wins_over_a_colliding_catalogue_sku(): void
    {
        $clientProduct = $this->makeClientProduct(20, 'SHARED-SKU');
        $catalogue     = $this->makeCatalogueProduct(20, 'SHARED-SKU');
        $order = $this->makeOrder(['client_id' => $clientProduct->client_id]);

        OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Widget',
            'lineitem_quantity' => 2,
            'lineitem_price'    => 50.0,
            'lineitem_sku'      => 'SHARED-SKU',
        ]);

        $shipment = $this->makeShipment($order);
        $shipment->update(['status' => ShipmentStatus::IN_TRANSIT->value]);

        $this->assertSame(18, $clientProduct->fresh()->quantity);
        $this->assertSame(20, $catalogue->fresh()->variant_inventory_qty);
    }

    public function test_unmatched_sku_is_skipped_without_failing_the_update(): void
    {
        $order = $this->makeOrder();

        OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Mystery Item',
            'lineitem_quantity' => 1,
            'lineitem_price'    => 10.0,
            'lineitem_sku'      => 'NOT-A-REAL-SKU',
        ]);

        $shipment = $this->makeShipment($order);
        $shipment->update(['status' => ShipmentStatus::IN_TRANSIT->value]);

        $this->assertSame(ShipmentStatus::IN_TRANSIT->value, $shipment->fresh()->status);
        $this->assertSame(0, StockMovement::where('shipment_id', $shipment->id)->count());
        // Nothing to move is still a completed pass, not a pending retry.
        $this->assertNotNull($shipment->fresh()->stock_deducted_at);
    }

    public function test_restock_mirrors_the_dispatched_quantity_not_the_edited_order(): void
    {
        $product = $this->makeCatalogueProduct(10);
        $order   = $this->makeOrder();

        $item = OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Catalogue Widget',
            'lineitem_quantity' => 3,
            'lineitem_price'    => 99.0,
            'product_id'        => $product->id,
        ]);

        $shipment = $this->makeShipment($order);
        $shipment->update(['status' => ShipmentStatus::IN_TRANSIT->value]);

        // Someone edits the order after the parcel already left.
        $item->update(['lineitem_quantity' => 99]);

        $shipment->markReturned('Returned to sender');

        $this->assertSame(10, $product->fresh()->variant_inventory_qty);
    }

    public function test_manual_adjustment_records_the_delta(): void
    {
        $product = $this->makeCatalogueProduct(10);

        $movement = app(InventoryService::class)->adjust($product, 25, null, 'Stock count');

        $this->assertSame(25, $product->fresh()->variant_inventory_qty);
        $this->assertSame(15, $movement->quantity);
        $this->assertSame(25, $movement->balance_after);
        $this->assertSame(StockMovement::REASON_MANUAL_ADJUSTMENT, $movement->reason);

        // A no-op edit writes nothing.
        $this->assertNull(app(InventoryService::class)->adjust($product->fresh(), 25));
    }

    public function test_manual_adjustment_after_dispatch_is_preserved_by_a_return(): void
    {
        $product = $this->makeCatalogueProduct(10);
        $order   = $this->makeOrder();

        OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Catalogue Widget',
            'lineitem_quantity' => 4,
            'lineitem_price'    => 99.0,
            'product_id'        => $product->id,
        ]);

        $shipment = $this->makeShipment($order);
        $shipment->update(['status' => ShipmentStatus::IN_TRANSIT->value]);
        $this->assertSame(6, $product->fresh()->variant_inventory_qty);

        // Admin restocks the shelf while the parcel is out.
        app(InventoryService::class)->adjust($product->fresh(), 50, null, 'Restocked from supplier');

        $shipment->markReturned('Refused');

        // The return adds its 4 on top of the admin's count rather than
        // rewinding to a stale figure.
        $this->assertSame(54, $product->fresh()->variant_inventory_qty);
    }

    // ── Idempotency ─────────────────────────────────────────────────────────

    public function test_unique_dedupe_key_rejects_a_duplicate_automatic_movement(): void
    {
        // The guarantee, asserted directly: whatever the application code
        // believes, the database refuses the same movement twice.
        $product = $this->makeCatalogueProduct(10);
        $order   = $this->makeOrder();

        $item = OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Catalogue Widget',
            'lineitem_quantity' => 1,
            'lineitem_price'    => 99.0,
            'product_id'        => $product->id,
        ]);

        $shipment = $this->makeShipment($order);
        $key = StockMovement::dedupeKeyFor($shipment->id, StockMovement::REASON_SHIPMENT_DISPATCHED, $item->id);

        $row = [
            'stockable_type' => $product->getMorphClass(),
            'stockable_id'   => $product->id,
            'quantity'       => -1,
            'balance_after'  => 9,
            'reason'         => StockMovement::REASON_SHIPMENT_DISPATCHED,
            'dedupe_key'     => $key,
            'order_id'       => $order->id,
            'order_item_id'  => $item->id,
            'shipment_id'    => $shipment->id,
        ];

        StockMovement::create($row);

        $this->expectException(UniqueConstraintViolationException::class);
        StockMovement::create($row);
    }

    public function test_replay_with_the_fast_path_cleared_does_not_double_deduct(): void
    {
        // Simulates the race the flag cannot catch: a second process that read
        // the shipment before the first one committed its guard timestamp.
        $product = $this->makeCatalogueProduct(10);
        $order   = $this->makeOrder();

        OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Catalogue Widget',
            'lineitem_quantity' => 3,
            'lineitem_price'    => 99.0,
            'product_id'        => $product->id,
        ]);

        $shipment = $this->makeShipment($order);
        $shipment->update(['status' => ShipmentStatus::IN_TRANSIT->value]);
        $this->assertSame(7, $product->fresh()->variant_inventory_qty);

        $shipment->forceFill(['stock_deducted_at' => null])->saveQuietly();

        $applied = app(InventoryService::class)->deductForShipment($shipment->fresh());

        $this->assertSame(0, $applied);
        $this->assertSame(7, $product->fresh()->variant_inventory_qty);
        $this->assertSame(1, StockMovement::where('shipment_id', $shipment->id)->count());
    }

    public function test_replay_with_the_fast_path_cleared_does_not_double_restock(): void
    {
        $product = $this->makeCatalogueProduct(10);
        $order   = $this->makeOrder();

        OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Catalogue Widget',
            'lineitem_quantity' => 3,
            'lineitem_price'    => 99.0,
            'product_id'        => $product->id,
        ]);

        $shipment = $this->makeShipment($order);
        $shipment->update(['status' => ShipmentStatus::IN_TRANSIT->value]);
        $shipment->markReturned('Refused');
        $this->assertSame(10, $product->fresh()->variant_inventory_qty);

        $shipment->forceFill(['stock_restocked_at' => null])->saveQuietly();

        $applied = app(InventoryService::class)->restockForShipment($shipment->fresh());

        $this->assertSame(0, $applied);
        $this->assertSame(10, $product->fresh()->variant_inventory_qty);
    }

    public function test_unmatched_line_is_skipped_and_the_shipment_still_completes(): void
    {
        $known = $this->makeCatalogueProduct(10, 'SKU-KNOWN');
        $order = $this->makeOrder();

        OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Known Widget',
            'lineitem_quantity' => 2,
            'lineitem_price'    => 99.0,
            'lineitem_sku'      => 'SKU-KNOWN',
        ]);
        OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Not Catalogued',
            'lineitem_quantity' => 5,
            'lineitem_price'    => 20.0,
            'lineitem_sku'      => 'SKU-LATER',
        ]);

        $shipment = $this->makeShipment($order);
        $shipment->update(['status' => ShipmentStatus::IN_TRANSIT->value]);

        // The matched line moved; the unmatched one moved nothing, and the
        // shipment is marked done rather than left to retry.
        $this->assertSame(8, $known->fresh()->variant_inventory_qty);
        $this->assertNotNull($shipment->fresh()->stock_deducted_at);
        $this->assertSame(1, StockMovement::where('shipment_id', $shipment->id)->count());

        // Creating the product afterwards does not retroactively deduct it.
        $later = $this->makeCatalogueProduct(30, 'SKU-LATER');
        $shipment->update(['status' => ShipmentStatus::OUT_FOR_DELIVERY->value]);

        $this->assertSame(30, $later->fresh()->variant_inventory_qty);
        $this->assertSame(8, $known->fresh()->variant_inventory_qty);
        $this->assertSame(1, StockMovement::where('shipment_id', $shipment->id)->count());
    }

    public function test_return_gives_back_only_the_lines_that_were_deducted(): void
    {
        $known = $this->makeCatalogueProduct(10, 'SKU-KNOWN-2');
        $order = $this->makeOrder();

        OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Known Widget',
            'lineitem_quantity' => 2,
            'lineitem_price'    => 99.0,
            'lineitem_sku'      => 'SKU-KNOWN-2',
        ]);
        OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Never Catalogued',
            'lineitem_quantity' => 5,
            'lineitem_price'    => 20.0,
            'lineitem_sku'      => 'SKU-NEVER',
        ]);

        $shipment = $this->makeShipment($order);
        $shipment->update(['status' => ShipmentStatus::IN_TRANSIT->value]);
        $this->assertSame(8, $known->fresh()->variant_inventory_qty);
        $this->assertNotNull($shipment->fresh()->stock_deducted_at);

        $shipment->markReturned('Refused');

        // Restock mirrors what was actually taken, so the line skipped at
        // dispatch is skipped on the way back too.
        $this->assertSame(10, $known->fresh()->variant_inventory_qty);
        $this->assertSame(1, StockMovement::where('shipment_id', $shipment->id)
            ->where('reason', StockMovement::REASON_SHIPMENT_RETURNED)
            ->count());
        $this->assertNotNull($shipment->fresh()->stock_restocked_at);
    }

    public function test_every_dispatch_status_replayed_in_any_order_deducts_once(): void
    {
        $product = $this->makeCatalogueProduct(10);
        $order   = $this->makeOrder();

        OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Catalogue Widget',
            'lineitem_quantity' => 2,
            'lineitem_price'    => 99.0,
            'product_id'        => $product->id,
        ]);

        $shipment = $this->makeShipment($order);
        $service  = app(InventoryService::class);

        // Direct service calls, bypassing the status guard entirely, with the
        // fast path cleared each time — only the ledger stands in the way.
        for ($i = 0; $i < 5; $i++) {
            $shipment->forceFill(['stock_deducted_at' => null])->saveQuietly();
            $service->deductForShipment($shipment->fresh());
        }

        $this->assertSame(8, $product->fresh()->variant_inventory_qty);
        $this->assertSame(1, StockMovement::where('shipment_id', $shipment->id)->count());
    }

    public function test_manual_adjustments_are_never_deduplicated(): void
    {
        $product = $this->makeCatalogueProduct(10);
        $service = app(InventoryService::class);

        $service->adjust($product->fresh(), 20, null, 'Received stock');
        $service->adjust($product->fresh(), 10, null, 'Miscount corrected');
        $service->adjust($product->fresh(), 20, null, 'Received again');

        $this->assertSame(20, $product->fresh()->variant_inventory_qty);
        $this->assertSame(3, StockMovement::where('stockable_id', $product->id)
            ->where('reason', StockMovement::REASON_MANUAL_ADJUSTMENT)
            ->count());
    }

    public function test_losing_the_insert_race_skips_the_pool_and_does_not_poison_the_transaction(): void
    {
        // The pre-check that skips already-applied lines is only an
        // optimisation; the unique index is the guarantee. This drives the
        // path where the pre-check misses and the database does the rejecting,
        // and proves the surrounding work survives it.
        $collides = $this->makeCatalogueProduct(10, 'SKU-COLLIDES');
        $fresh    = $this->makeCatalogueProduct(10, 'SKU-FRESH');
        $order    = $this->makeOrder();

        $collidingItem = OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Already Moved Elsewhere',
            'lineitem_quantity' => 3,
            'lineitem_price'    => 99.0,
            'product_id'        => $collides->id,
        ]);
        OrderItem::create([
            'order_id'          => $order->id,
            'lineitem_name'     => 'Fresh Line',
            'lineitem_quantity' => 4,
            'lineitem_price'    => 99.0,
            'product_id'        => $fresh->id,
        ]);

        $shipment = $this->makeShipment($order);

        // Seed the key without the shipment_id, so the pre-check's
        // shipment-scoped lookup cannot see it — standing in for a row another
        // process committed after we ran that lookup.
        StockMovement::create([
            'stockable_type' => $collides->getMorphClass(),
            'stockable_id'   => $collides->id,
            'quantity'       => -3,
            'balance_after'  => 7,
            'reason'         => StockMovement::REASON_SHIPMENT_DISPATCHED,
            'dedupe_key'     => StockMovement::dedupeKeyFor(
                $shipment->id,
                StockMovement::REASON_SHIPMENT_DISPATCHED,
                $collidingItem->id,
            ),
        ]);

        $applied = app(InventoryService::class)->deductForShipment($shipment);

        // The colliding line was rejected by the index and left the pool alone.
        $this->assertSame(10, $collides->fresh()->variant_inventory_qty);
        // The violation was contained in its own savepoint, so the next line
        // still committed — on a driver that aborts a transaction on error,
        // an unscoped catch here would have lost this.
        $this->assertSame(6, $fresh->fresh()->variant_inventory_qty);
        $this->assertSame(1, $applied);
    }
}

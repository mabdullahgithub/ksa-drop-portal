<?php

namespace App\Services\Inventory;

use App\Models\ClientProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Moves stock between the two pools and the ledger.
 *
 * There are two independent pools and a line item draws from exactly one:
 *
 *  - `client_products.quantity`      — a fulfilment client's own goods held in
 *                                      our warehouse, scoped to that client.
 *  - `products.variant_inventory_qty` — the shared dropshipping catalogue.
 *
 * Stock leaves the pool when a shipment is first reported as dispatched and
 * returns when a dispatched shipment ends up back with us.
 *
 * ── Idempotency ──────────────────────────────────────────────────────────
 *
 * Courier status reaches us from five call sites (the J&T, iMile and
 * LogesTechs webhooks, the SyncShipmentTracking poller, and the on-demand
 * refresh), the same status is pushed repeatedly, pushes arrive out of order,
 * and a courier will re-send a webhook it believes failed even when our
 * transaction actually committed. Stock must move exactly once per line per
 * direction regardless.
 *
 * Three layers, outermost first — each is an optimisation over the next, and
 * only the last is a guarantee:
 *
 *  1. `shipments.stock_deducted_at` / `stock_restocked_at` short-circuit the
 *     common case so repeat pushes cost nothing. Not a guarantee: two
 *     concurrent readers can both see them unset.
 *  2. A `SELECT … FOR UPDATE` on the shipment serialises concurrent callers
 *     within a database that honours row locks. Not a guarantee either:
 *     locking is invisible to the check when the flag was set by a
 *     transaction that has not yet committed.
 *  3. `stock_movements.dedupe_key` is UNIQUE. Every automatic movement
 *     derives a deterministic key from (shipment, direction, order line), so
 *     a duplicate is refused by the database itself — across processes,
 *     across servers, and regardless of transaction interleaving. A rejected
 *     insert means "already applied", which is a success, not an error.
 *
 * A line that matches no product moves no stock and is simply skipped; the
 * shipment is still marked done, so the order processes normally and the line
 * is never revisited.
 */
class InventoryService
{
    /**
     * Deduct every line on the shipment's order from its pool, exactly once.
     *
     * @return int Number of lines this call actually moved (0 when it was a replay).
     */
    public function deductForShipment(Shipment $shipment): int
    {
        if ($shipment->stock_deducted_at !== null) {
            return 0;
        }

        return $this->applyForShipment($shipment, StockMovement::REASON_SHIPMENT_DISPATCHED);
    }

    /**
     * Return a dispatched shipment's stock to its pools, exactly once.
     *
     * A shipment that never dispatched restocks nothing — there is no
     * dispatch movement to mirror, so a cancellation before pickup cannot
     * invent inventory.
     *
     * @return int Number of lines this call actually moved (0 when it was a replay).
     */
    public function restockForShipment(Shipment $shipment): int
    {
        if ($shipment->stock_restocked_at !== null) {
            return 0;
        }

        return $this->applyForShipment($shipment, StockMovement::REASON_SHIPMENT_RETURNED);
    }

    /**
     * Shared body for both directions.
     *
     * Deducting reads the order's lines; restocking mirrors the dispatch
     * movements already recorded, so an order edited after the parcel left
     * cannot give back a different quantity than it took.
     */
    private function applyForShipment(Shipment $shipment, string $reason): int
    {
        $isDeduction = $reason === StockMovement::REASON_SHIPMENT_DISPATCHED;
        $flag        = $isDeduction ? 'stock_deducted_at' : 'stock_restocked_at';

        return DB::transaction(function () use ($shipment, $reason, $isDeduction, $flag): int {
            // Serialise concurrent callers and re-read the flag as of the
            // latest committed state rather than this instance's snapshot.
            $fresh = Shipment::whereKey($shipment->getKey())->lockForUpdate()->first();

            if ($fresh === null || $fresh->{$flag} !== null) {
                return 0;
            }

            $planned = $isDeduction
                ? $this->plannedDeductions($fresh)
                : $this->plannedRestocks($fresh);

            // Keys already written by an earlier pass. Checking up front turns
            // the unique index into a backstop for genuine races rather than
            // the everyday path, so the common replay costs one query instead
            // of an insert per line.
            $alreadyApplied = StockMovement::where('shipment_id', $fresh->id)
                ->where('reason', $reason)
                ->pluck('dedupe_key')
                ->filter()
                ->flip();

            $applied = 0;

            foreach ($planned as $movement) {
                $key = StockMovement::dedupeKeyFor($fresh->id, $reason, $movement['order_item_id']);

                if ($alreadyApplied->has($key)) {
                    continue;
                }

                if ($this->applyDelta($movement['stockable'], $movement['quantity'], $reason, $key, [
                    'order_id'      => $movement['order_id'],
                    'order_item_id' => $movement['order_item_id'],
                    'shipment_id'   => $fresh->id,
                ])) {
                    $applied++;
                }
            }

            // The shipment is done either way. A line we couldn't match to a
            // product contributes nothing and is not revisited — the order
            // itself processes normally, it simply moves no stock.
            //
            // forceFill because the guard columns are deliberately kept out of
            // $fillable (no request payload may reset them), and saveQuietly
            // so this write cannot re-enter ShipmentObserver.
            $fresh->forceFill([$flag => now()])->saveQuietly();
            $shipment->{$flag} = $fresh->{$flag};

            return $applied;
        });
    }

    /**
     * What a dispatch should take.
     *
     * Lines that resolve to no product — a SKU we don't stock, a free gift, a
     * one-off item typed into a manual order — are logged and skipped. They
     * move no stock in either direction, and because the shipment is marked
     * done regardless, they are not revisited if that product is created
     * later. Stock only ever tracks lines it could account for at dispatch.
     *
     * @return array<int, array{stockable: Model, quantity: int, order_id: int|null, order_item_id: int}>
     */
    private function plannedDeductions(Shipment $shipment): array
    {
        // withoutGlobalScopes: the Order model hides Shopify orders awaiting
        // client approval, and a shipment must still account for its stock.
        $order = $shipment->order()->withoutGlobalScopes()->with('items')->first();

        if ($order === null) {
            return [];
        }

        $movements = [];
        $unmatched = [];

        foreach ($order->items as $item) {
            $stockable = $this->resolveStockable($item, $order);

            if ($stockable === null) {
                $unmatched[] = $item->lineitem_sku ?: $item->lineitem_name;
                continue;
            }

            $movements[] = [
                'stockable'     => $stockable,
                'quantity'      => -1 * max(0, (int) $item->lineitem_quantity),
                'order_id'      => $item->order_id,
                'order_item_id' => $item->id,
            ];
        }

        if ($unmatched !== []) {
            Log::info('Stock skipped for unmatched order lines', [
                'shipment_id' => $shipment->id,
                'order_id'    => $order->id,
                'unmatched'   => $unmatched,
            ]);
        }

        return $movements;
    }

    /**
     * What a return should give back: the mirror of what was actually taken.
     *
     * Reading the recorded movements rather than the order means a line that
     * was skipped at dispatch is skipped here too, automatically — nothing can
     * be given back that was never taken.
     *
     * @return array<int, array{stockable: Model, quantity: int, order_id: int|null, order_item_id: int}>
     */
    private function plannedRestocks(Shipment $shipment): array
    {
        $dispatched = StockMovement::with('stockable')
            ->where('shipment_id', $shipment->id)
            ->where('reason', StockMovement::REASON_SHIPMENT_DISPATCHED)
            ->get();

        $movements = [];

        foreach ($dispatched as $movement) {
            if (! $movement->stockable instanceof Model || $movement->order_item_id === null) {
                continue;
            }

            $movements[] = [
                'stockable'     => $movement->stockable,
                'quantity'      => abs((int) $movement->quantity),
                'order_id'      => $movement->order_id,
                'order_item_id' => $movement->order_item_id,
            ];
        }

        return $movements;
    }

    /**
     * Record an admin setting a pool to an absolute quantity.
     *
     * The edit is authoritative — an admin counting the shelf overrides
     * whatever the ledger believes — so this writes the difference as a
     * movement rather than refusing it. Manual movements carry no dedupe key:
     * setting a count to 10, to 20, and back to 10 is three real events.
     *
     * @return StockMovement|null Null when the quantity did not actually change.
     */
    public function adjust(Model $stockable, int $newQuantity, ?int $userId = null, ?string $note = null): ?StockMovement
    {
        return DB::transaction(function () use ($stockable, $newQuantity, $userId, $note): ?StockMovement {
            $locked = $stockable->newQuery()->whereKey($stockable->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                return null;
            }

            $column  = $this->quantityColumn($locked);
            $current = (int) $locked->{$column};
            $delta   = $newQuantity - $current;

            if ($delta === 0) {
                return null;
            }

            $locked->forceFill([$column => $newQuantity])->save();
            $stockable->{$column} = $newQuantity;

            return StockMovement::create([
                'stockable_type' => $locked->getMorphClass(),
                'stockable_id'   => $locked->getKey(),
                'quantity'       => $delta,
                'balance_after'  => $newQuantity,
                'reason'         => StockMovement::REASON_MANUAL_ADJUSTMENT,
                'note'           => $note,
                'user_id'        => $userId,
                'dedupe_key'     => null,
            ]);
        });
    }

    /**
     * Resolve which pool an order line draws from.
     *
     * Portal-created orders carry an explicit link. Shopify-synced lines carry
     * only a SKU, so fall back to matching it — the client's own products
     * first (a fulfilment client's stock is theirs, and their SKU may collide
     * with a catalogue one), then the shared catalogue. This mirrors how
     * PortalController resolves dropshipper product costs by SKU.
     */
    public function resolveStockable(OrderItem $item, ?Order $order = null): Product|ClientProduct|null
    {
        if ($item->client_product_id) {
            return $item->clientProduct;
        }

        if ($item->product_id) {
            return $item->product;
        }

        $sku = trim((string) $item->lineitem_sku);

        if ($sku === '') {
            return null;
        }

        $order ??= $item->order;
        $clientId = $order?->client_id;

        if ($clientId) {
            $clientProduct = ClientProduct::where('client_id', $clientId)
                ->where('sku', $sku)
                ->first();

            if ($clientProduct) {
                return $clientProduct;
            }
        }

        return Product::where('variant_sku', $sku)->first();
    }

    /**
     * Apply a signed delta to a pool and write the matching ledger row.
     *
     * The ledger row is inserted *before* the pool is touched, inside its own
     * savepoint: if the unique dedupe key rejects it, another process already
     * applied this exact movement and we must leave the pool alone. Doing it
     * the other way round would move stock and then discover the duplicate.
     *
     * Pools are allowed to go negative: an oversold line is a real condition
     * an admin needs to see, and silently clamping at zero would quietly lose
     * the amount owed back when the goods return.
     *
     * @return bool Whether this call applied the movement (false = already applied elsewhere).
     */
    private function applyDelta(Model $stockable, int $delta, string $reason, string $dedupeKey, array $context): bool
    {
        if ($delta === 0) {
            return false;
        }

        $locked = $stockable->newQuery()->whereKey($stockable->getKey())->lockForUpdate()->first();

        if ($locked === null) {
            return false;
        }

        $column  = $this->quantityColumn($locked);
        $balance = (int) $locked->{$column} + $delta;

        try {
            DB::transaction(fn () => StockMovement::create([
                'stockable_type' => $locked->getMorphClass(),
                'stockable_id'   => $locked->getKey(),
                'quantity'       => $delta,
                'balance_after'  => $balance,
                'reason'         => $reason,
                'dedupe_key'     => $dedupeKey,
                ...$context,
            ]));
        } catch (UniqueConstraintViolationException) {
            // Lost the race — the movement is already recorded, and whoever
            // won it also moved the pool. Nothing left to do.
            Log::info('Stock movement already applied; skipping duplicate', [
                'dedupe_key' => $dedupeKey,
            ]);

            return false;
        }

        $locked->forceFill([$column => $balance])->save();
        $stockable->{$column} = $balance;

        return true;
    }

    /**
     * The column holding the on-hand count for each pool.
     */
    private function quantityColumn(Model $stockable): string
    {
        return $stockable instanceof ClientProduct ? 'quantity' : 'variant_inventory_qty';
    }
}

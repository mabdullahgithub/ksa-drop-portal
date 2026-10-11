<?php

namespace App\Services\Inventory;

use App\Models\ClientProduct;
use App\Models\InventoryRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The team's decision on a change a client asked for.
 *
 * Approving is the only place a client's edit of a verified product reaches
 * the product. A stock approval adds the count the warehouse actually
 * received to the stock as it stands at that moment, so parcels scanned out
 * or in while the request waited (StockScanRecorder) are kept.
 *
 * A request is decided once. Whoever comes second — a double click, a second
 * person, a client who changed the request meanwhile — is refused.
 */
class InventoryRequestReviewer
{
    /** Most images a product may hold. */
    private const MAX_IMAGES = 10;

    public function __construct(private InventoryRequestNotifier $notifier) {}

    /**
     * @param  int  $units  How many units really came in (or, when the client
     *                      asked for less stock, how many to take off). Always above zero.
     * @param  ?int  $revision  The revision the person was looking at, when known.
     *
     * @throws InventoryRequestRefused
     */
    public function approveStock(InventoryRequest $request, int $units, bool $markInStock = false, ?int $revision = null): InventoryRequest
    {
        $request = DB::transaction(function () use ($request, $units, $markInStock, $revision) {
            $request = $this->lock($request, InventoryRequest::STOCK, $revision);
            $product = $this->product($request);

            $by = $request->requestedChange() < 0 ? -$units : $units;
            $before = (int) $product->quantity;

            if ($before + $by < 0) {
                throw new InventoryRequestRefused('not_enough', "Only {$before} in stock; {$units} cannot be taken off.", 422);
            }

            $row = fn () => ClientProduct::acrossClients()->whereKey($product->id);
            $row()->increment('quantity', $by);

            if ($markInStock) {
                $row()->update(['is_out_of_stock' => false]);
            }

            $request->update($this->decided(InventoryRequest::APPROVED) + [
                'received_quantity' => $by,
                'stock_before' => $before,
                'stock_after' => (int) $row()->value('quantity'),
            ]);

            return $request;
        });

        $this->notifier->reviewed($request);

        return $request;
    }

    /**
     * @throws InventoryRequestRefused
     */
    public function approveDetails(InventoryRequest $request, ?int $revision = null): InventoryRequest
    {
        $request = DB::transaction(function () use ($request, $revision) {
            $request = $this->lock($request, InventoryRequest::DETAILS, $revision);
            $product = $this->product($request);

            $changes = $request->changes ?? [];
            $remove = $product->images()->whereIn('id', $changes['remove_image_ids'] ?? [])->get();
            $added = $request->images()->get();

            if ($product->images()->count() - $remove->count() + $added->count() > self::MAX_IMAGES) {
                throw new InventoryRequestRefused('too_many_images', 'Approving would give this product more than ' . self::MAX_IMAGES . ' images. Remove some from the product first, or reject the request.', 422);
            }

            $product->update(array_intersect_key($changes, array_flip(InventoryRequest::DETAIL_FIELDS)));

            foreach ($remove as $image) {
                Storage::disk('public')->delete($image->path);
                $image->delete();
            }

            $position = ($product->images()->max('position') ?? 0) + 1;

            foreach ($added as $image) {
                $path = "client-products/{$product->id}/" . basename($image->path);
                Storage::disk('public')->move($image->path, $path);
                $product->images()->create(['path' => $path, 'position' => $position++]);
            }

            // The files now belong to the product; only the rows and the
            // empty folder are left to clear.
            $request->deleteImages();
            $request->update($this->decided(InventoryRequest::APPROVED));

            return $request;
        });

        $this->notifier->reviewed($request);

        return $request;
    }

    /**
     * @throws InventoryRequestRefused
     */
    public function reject(InventoryRequest $request, string $type, string $reason, ?int $revision = null): InventoryRequest
    {
        $request = DB::transaction(function () use ($request, $type, $reason, $revision) {
            $request = $this->lock($request, $type, $revision);

            $request->deleteImages();
            $request->update($this->decided(InventoryRequest::REJECTED) + ['rejection_reason' => $reason]);

            return $request;
        });

        $this->notifier->reviewed($request);

        return $request;
    }

    /**
     * The request, held for this decision, or the reason it can't be decided.
     *
     * @throws InventoryRequestRefused
     */
    private function lock(InventoryRequest $request, string $type, ?int $revision): InventoryRequest
    {
        $request = InventoryRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

        // A stock permission must not decide a details request, or the other way round.
        abort_unless($request->type === $type, 404);

        if (! $request->isPending()) {
            throw new InventoryRequestRefused('already_decided', "This request was already {$request->status}.");
        }

        if ($revision !== null && $revision !== $request->revision) {
            throw new InventoryRequestRefused('changed', 'The client changed this request. Reload and check it again.');
        }

        return $request;
    }

    /**
     * @throws InventoryRequestRefused
     */
    private function product(InventoryRequest $request): ClientProduct
    {
        // Held too, so the count read here is the count the change lands on.
        $product = ClientProduct::acrossClients()->whereKey($request->client_product_id)->lockForUpdate()->first();

        if (! $product || $product->verification_status !== 'verified') {
            throw new InventoryRequestRefused('product_gone', 'This product is no longer in the client\'s verified inventory.', 422);
        }

        return $product;
    }

    /** @return array<string, mixed> */
    private function decided(string $status): array
    {
        return [
            'status' => $status,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ];
    }
}

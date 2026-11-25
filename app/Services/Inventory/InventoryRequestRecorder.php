<?php

namespace App\Services\Inventory;

use App\Models\ClientProduct;
use App\Models\InventoryRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * What saving a verified product does for a client.
 *
 * Changed details (name, SKU, description, price, notes, images) are applied
 * to the product at once. A changed quantity is not: it becomes a stock
 * request, which the team decides on the Requests page
 * (InventoryRequestReviewer).
 *
 * A product has one pending stock request at most. Saving again replaces it,
 * and putting the quantity back to what the product has withdraws it.
 */
class InventoryRequestRecorder
{
    /** Most images a product may hold. */
    private const MAX_IMAGES = 10;

    public const NOTHING = 'none';

    public const SENT = 'sent';

    public const WITHDRAWN = 'withdrawn';

    /**
     * @param  array<string, mixed>  $input  The validated product form.
     * @param  list<UploadedFile>  $images
     * @return array{outcome: string, details_updated: bool, created: list<InventoryRequest>, pending: Collection<int, InventoryRequest>}
     *         `outcome` is about the stock request. `created` are the new
     *         requests, for telling the team; a replaced one is not news.
     */
    public function record(ClientProduct $product, array $input, array $images, ?User $by): array
    {
        return DB::transaction(function () use ($product, $input, $images, $by) {
            // One save at a time per product, so two tabs can't both find
            // "no pending request" and each make one.
            $product = ClientProduct::whereKey($product->id)->lockForUpdate()->firstOrFail();

            // Details first: it can refuse the save (too many images).
            $detailsUpdated = $this->applyDetails($product, $input, $images);
            $stock = $this->stock($product, $input, $by);

            return [
                'outcome' => $stock['outcome'],
                'details_updated' => $detailsUpdated,
                'created' => array_values(array_filter([$stock['created']])),
                'pending' => $product->pendingRequests()->where('type', InventoryRequest::STOCK)->with('images')->get(),
            ];
        });
    }

    /**
     * The quantity field. It counts as changed when it differs from the stock
     * the form showed (`base_quantity`), not from the stock right now: a
     * parcel scanned out while the form was open is not something the client
     * asked for.
     *
     * @return array{outcome: string, created: ?InventoryRequest}
     */
    private function stock(ClientProduct $product, array $input, ?User $by): array
    {
        $none = ['outcome' => self::NOTHING, 'created' => null];

        if (! array_key_exists('quantity', $input) || $input['quantity'] === null) {
            return $none;
        }

        $quantity = (int) $input['quantity'];
        $shown = isset($input['base_quantity']) ? (int) $input['base_quantity'] : (int) $product->quantity;
        $pending = $this->pending($product, InventoryRequest::STOCK);

        // The form opens on what is already waiting; left alone, it stays.
        if ($pending && $quantity === $pending->requested_quantity) {
            return $none;
        }

        if ($quantity === $shown) {
            $pending?->cancel();

            return ['outcome' => $pending ? self::WITHDRAWN : self::NOTHING, 'created' => null];
        }

        if ($pending) {
            $pending->update([
                'requested_quantity' => $quantity,
                'stock_at_request' => $shown,
                'revision' => $pending->revision + 1,
                'requested_by' => $by?->id,
            ]);

            return ['outcome' => self::SENT, 'created' => null];
        }

        return ['outcome' => self::SENT, 'created' => $product->requests()->create([
            'client_id' => $product->client_id,
            'type' => InventoryRequest::STOCK,
            'requested_quantity' => $quantity,
            'stock_at_request' => $shown,
            'requested_by' => $by?->id,
        ])];
    }

    /**
     * Everything else on the form, saved to the product. Only what differs is
     * written.
     *
     * @param  list<UploadedFile>  $images
     */
    private function applyDetails(ClientProduct $product, array $input, array $images): bool
    {
        $changes = [];

        foreach (InventoryRequest::DETAIL_FIELDS as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $asked = self::normalise($field, $input[$field]);

            if ($asked !== self::normalise($field, $product->{$field})) {
                $changes[$field] = $asked;
            }
        }

        $remove = $product->images()
            ->whereIn('id', array_map('intval', $input['remove_image_ids'] ?? []))
            ->get();

        if ($product->images()->count() - $remove->count() + count($images) > self::MAX_IMAGES) {
            throw ValidationException::withMessages([
                'images' => 'A product can have up to ' . self::MAX_IMAGES . ' images.',
            ]);
        }

        if ($changes === [] && $remove->isEmpty() && $images === []) {
            return false;
        }

        if ($changes !== []) {
            $product->update($changes);
        }

        $position = ($product->images()->max('position') ?? 0) + 1;

        foreach ($images as $file) {
            $product->images()->create([
                'path' => $file->store("client-products/{$product->id}", 'public'),
                'position' => $position++,
            ]);
        }

        foreach ($remove as $image) {
            $image->delete();
            // Only once the save has gone through: a rollback keeps the row.
            DB::afterCommit(fn () => Storage::disk('public')->delete($image->path));
        }

        // A details request left from when these needed approval is out of
        // date now; approving it later would undo this save.
        $this->pending($product, InventoryRequest::DETAILS)?->cancel();

        return true;
    }

    private function pending(ClientProduct $product, string $type): ?InventoryRequest
    {
        return $product->pendingRequests()->where('type', $type)->with('images')->latest('id')->first();
    }

    /**
     * A field's value in the one form it is compared and stored in: text
     * trimmed with blank as null, the price as a two-decimal string.
     */
    public static function normalise(string $field, mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $field === 'unit_price'
            ? number_format((float) $value, 2, '.', '')
            : trim((string) $value);
    }
}

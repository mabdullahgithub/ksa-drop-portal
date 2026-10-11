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
 * What saving a verified product does for a client: nothing to the product.
 *
 * A changed quantity becomes a stock request, changed details become a
 * details request, and the team decides each on the Requests page
 * (InventoryRequestReviewer).
 *
 * A product has one pending request of each type at most. Saving again
 * replaces it, and putting a value back to what the product already has
 * withdraws it.
 */
class InventoryRequestRecorder
{
    /** Most images a product may hold, the ones waiting on approval included. */
    private const MAX_IMAGES = 10;

    public const NOTHING = 'none';

    public const SENT = 'sent';

    public const WITHDRAWN = 'withdrawn';

    /**
     * @param  array<string, mixed>  $input  The validated product form.
     * @param  list<UploadedFile>  $images
     * @return array{outcome: string, created: list<InventoryRequest>, pending: Collection<int, InventoryRequest>}
     *         `created` are the new ones, for telling the team; a replaced request is not news.
     */
    public function record(ClientProduct $product, array $input, array $images, ?User $by): array
    {
        return DB::transaction(function () use ($product, $input, $images, $by) {
            // One save at a time per product, so two tabs can't both find
            // "no pending request" and each make one.
            $product = ClientProduct::whereKey($product->id)->lockForUpdate()->firstOrFail();

            $results = [
                $this->stock($product, $input, $by),
                $this->details($product, $input, $images, $by),
            ];

            $outcomes = array_column($results, 'outcome');

            return [
                'outcome' => match (true) {
                    in_array(self::SENT, $outcomes, true) => self::SENT,
                    in_array(self::WITHDRAWN, $outcomes, true) => self::WITHDRAWN,
                    default => self::NOTHING,
                },
                'created' => array_values(array_filter(array_column($results, 'created'))),
                'pending' => $product->pendingRequests()->with('images')->get(),
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
     * Everything else on the form. Worked out from scratch against the
     * product on every save: the form sends all its fields, the ones already
     * waiting included, so what differs from the product is the request.
     *
     * @param  list<UploadedFile>  $images
     * @return array{outcome: string, created: ?InventoryRequest}
     */
    private function details(ClientProduct $product, array $input, array $images, ?User $by): array
    {
        $pending = $this->pending($product, InventoryRequest::DETAILS);

        $changes = [];
        $original = [];

        foreach (InventoryRequest::DETAIL_FIELDS as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $now = self::normalise($field, $product->{$field});
            $asked = self::normalise($field, $input[$field]);

            if ($asked !== $now) {
                $changes[$field] = $asked;
                $original[$field] = $now;
            }
        }

        $remove = $product->images()
            ->whereIn('id', array_map('intval', $input['remove_image_ids'] ?? []))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        if ($remove !== []) {
            $changes['remove_image_ids'] = $remove;
        }

        // Images from an earlier save of this request, less the ones the
        // client took back out.
        $dropped = array_map('intval', $input['remove_pending_image_ids'] ?? []);
        $kept = $pending ? $pending->images->reject(fn ($image) => in_array($image->id, $dropped, true)) : collect();

        if ($product->images()->count() - count($remove) + $kept->count() + count($images) > self::MAX_IMAGES) {
            throw ValidationException::withMessages([
                'images' => 'A product can have up to ' . self::MAX_IMAGES . ' images.',
            ]);
        }

        if ($changes === [] && $kept->isEmpty() && $images === []) {
            $pending?->cancel();

            return ['outcome' => $pending ? self::WITHDRAWN : self::NOTHING, 'created' => null];
        }

        if ($pending) {
            $same = $pending->changes == $changes && $images === [] && $kept->count() === $pending->images->count();

            if ($same) {
                return ['outcome' => self::NOTHING, 'created' => null];
            }

            $pending->images->diff($kept)->each(function ($image) {
                Storage::disk('public')->delete($image->path);
                $image->delete();
            });

            $pending->update([
                'changes' => $changes,
                'original' => $original,
                'revision' => $pending->revision + 1,
                'requested_by' => $by?->id,
            ]);

            $this->store($pending, $images);

            return ['outcome' => self::SENT, 'created' => null];
        }

        $request = $product->requests()->create([
            'client_id' => $product->client_id,
            'type' => InventoryRequest::DETAILS,
            'changes' => $changes,
            'original' => $original,
            'requested_by' => $by?->id,
        ]);

        $this->store($request, $images);

        return ['outcome' => self::SENT, 'created' => $request];
    }

    private function pending(ClientProduct $product, string $type): ?InventoryRequest
    {
        return $product->pendingRequests()->where('type', $type)->with('images')->latest('id')->first();
    }

    /** @param  list<UploadedFile>  $images */
    private function store(InventoryRequest $request, array $images): void
    {
        $position = ($request->images()->max('position') ?? 0) + 1;

        foreach ($images as $file) {
            $request->images()->create([
                'path' => $file->store(InventoryRequest::imageDirectory($request->id), 'public'),
                'position' => $position++,
            ]);
        }
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

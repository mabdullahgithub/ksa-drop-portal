<?php

namespace App\Services\Inventory;

use App\Models\ClientProduct;
use App\Models\InventoryRequest;
use App\Notifications\ProductRejectedNotification;
use App\Notifications\ProductVerifiedNotification;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * The decision on a product a client added: verified, rejected, or left
 * pending. Reached from a client's Inventory tab and from the Requests page;
 * both go through here so they cannot drift apart.
 */
class ClientProductReviewer
{
    /** What a review request may carry, for either endpoint to validate. */
    public const RULES = [
        'action' => 'required|in:verify,reject,pending',
        'rejection_reason' => 'nullable|string|max:1000',
        'is_out_of_stock' => 'nullable|boolean',
        // The count the warehouse actually received; replaces the quantity
        // the client stated. Only used when verifying.
        'received_quantity' => 'nullable|integer|min:0',
        'images' => 'nullable|array|max:10',
        'images.*' => 'image|max:5120',
    ];

    public function reviewFromRequest(Request $request, ClientProduct $product): ClientProduct
    {
        $request->validate(self::RULES);

        return $this->review(
            $product,
            $request->input('action'),
            (bool) $request->input('is_out_of_stock', false),
            $request->input('rejection_reason'),
            $request->filled('received_quantity') ? (int) $request->input('received_quantity') : null,
            $request->file('images') ?? [],
        );
    }

    /**
     * @param  list<UploadedFile>  $images  Photos the team took while checking; kept on verify.
     */
    public function review(
        ClientProduct $product,
        string $action,
        bool $isOutOfStock = false,
        ?string $rejectionReason = null,
        ?int $receivedQuantity = null,
        array $images = [],
    ): ClientProduct {
        $wasVerified = $product->verification_status === 'verified';

        if ($action === 'verify') {
            $product->update([
                'verification_status' => 'verified',
                'verified_at' => now(),
                'verified_by' => auth()->id(),
                'rejection_reason' => null,
                'is_out_of_stock' => $isOutOfStock,
            ] + ($receivedQuantity !== null ? ['quantity' => $receivedQuantity] : []));

            $nextPosition = ($product->images()->max('position') ?? 0) + 1;
            foreach ($images as $file) {
                $path = $file->store("client-products/{$product->id}", 'public');
                $product->images()->create(['path' => $path, 'position' => $nextPosition++]);
            }

            try {
                $product->client->user?->notify(new ProductVerifiedNotification($product->fresh()));
            } catch (\Throwable) {
            }
        } elseif ($action === 'reject') {
            $product->update([
                'verification_status' => 'rejected',
                'rejection_reason' => $rejectionReason,
                'verified_at' => null,
                'verified_by' => null,
                'is_out_of_stock' => $isOutOfStock,
            ]);

            try {
                $product->client->user?->notify(new ProductRejectedNotification($product->fresh()));
            } catch (\Throwable) {
            }
        } else {
            // keep pending
            $product->update([
                'verification_status' => 'pending',
                'rejection_reason' => null,
                'verified_at' => null,
                'verified_by' => null,
                'is_out_of_stock' => $isOutOfStock,
            ]);
        }

        // No longer verified, so the client edits it directly again: what
        // they had waiting on it has nothing left to wait for.
        if ($wasVerified && $action !== 'verify') {
            $product->pendingRequests()->get()->each(fn (InventoryRequest $request) => $request->cancel());
        }

        return $product->fresh()->load('images');
    }
}

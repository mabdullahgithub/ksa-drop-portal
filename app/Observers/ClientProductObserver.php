<?php

namespace App\Observers;

use App\Models\ClientProduct;
use App\Models\InventoryRequest;
use App\Observers\Concerns\RecordsDeletionAudit;
use Illuminate\Support\Facades\Storage;

class ClientProductObserver
{
    use RecordsDeletionAudit {
        deleted as recordDeletion;
    }

    /**
     * A product in the recycle bin can't be changed, so whatever the client
     * had waiting on it is over. It stays over if the product is restored.
     */
    public function deleted(ClientProduct $product): void
    {
        $this->recordDeletion($product);

        // On a purge the requests go with the row (FK cascade).
        if ($product->isForceDeleting()) {
            return;
        }

        $product->pendingRequests()->get()->each(fn (InventoryRequest $request) => $request->cancel());
    }

    /**
     * Images a client proposed in a request still waiting live in the
     * request's own folder, and the cascade that removes the request rows
     * fires no events. Only a product purged without passing through the
     * recycle bin still has any.
     */
    public function forceDeleting(ClientProduct $product): void
    {
        foreach ($product->requests()->pluck('id') as $requestId) {
            Storage::disk('public')->deleteDirectory(InventoryRequest::imageDirectory($requestId));
        }
    }

    /**
     * client_product_images rows are removed by the FK cascade, but the files
     * they point at are not. Uploads go to client-products/{id} on the public
     * disk (see ClientController::storeProductImages and
     * PortalController::updateInventory), so drop the whole directory.
     *
     * Runs on forceDeleted rather than forceDeleting so the files only go once
     * the row is actually gone -- a failed delete must not leave a product row
     * pointing at images that no longer exist.
     */
    public function forceDeleted(ClientProduct $product): void
    {
        Storage::disk('public')->deleteDirectory("client-products/{$product->id}");
    }
}

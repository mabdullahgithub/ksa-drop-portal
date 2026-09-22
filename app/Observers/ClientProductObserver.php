<?php

namespace App\Observers;

use App\Models\ClientProduct;
use App\Observers\Concerns\RecordsDeletionAudit;
use Illuminate\Support\Facades\Storage;

class ClientProductObserver
{
    use RecordsDeletionAudit;

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

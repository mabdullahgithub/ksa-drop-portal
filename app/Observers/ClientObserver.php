<?php

namespace App\Observers;

use App\Models\Client;
use App\Observers\Concerns\RecordsDeletionAudit;
use Illuminate\Support\Facades\Storage;

class ClientObserver
{
    use RecordsDeletionAudit;

    /**
     * Hard-deleting a client cascades into client_products (and from there
     * into client_product_images) at the database level, which fires no model
     * events -- so ClientProductObserver never runs and those image
     * directories would be left behind. Remove them here, along with the
     * client's own logo.
     *
     * The linked users row is deliberately left alone: clients.user_id points
     * at users, so the cascade runs the other way, and removing the user would
     * hit the RESTRICT on client_payments.created_by.
     */
    public function forceDeleting(Client $client): void
    {
        $productIds = $client->clientProducts()->withTrashed()->pluck('id');

        foreach ($productIds as $productId) {
            Storage::disk('public')->deleteDirectory("client-products/{$productId}");
        }

        if ($client->logo) {
            Storage::disk('public')->delete($client->logo);
        }
    }
}

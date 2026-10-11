<?php

namespace Tests\Feature\Concerns;

use App\Http\Middleware\EnsureRequestsUnlocked;
use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\InventoryRequest;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Fulfilment clients, their products, and the changes they asked for on
 * them, for the tests of My Inventory and the Requests page.
 */
trait MakesInventory
{
    /**
     * The Requests page sits behind a PIN. Tests about what it does once
     * open start unlocked; the lock itself is covered in RequestsLockTest.
     */
    protected function unlockRequests(): void
    {
        $this->withSession([EnsureRequestsUnlocked::SESSION_KEY => now()]);
    }

    /**
     * A fulfilment client with My Inventory switched on, and the user who
     * signs in to its portal.
     *
     * @return array{0: User, 1: Client}
     */
    protected function fulfilmentClient(string $name = 'Acme Trading', array $attributes = []): array
    {
        Role::findOrCreate('client');

        $user = User::factory()->create();
        $user->assignRole('client');

        $client = Client::create($attributes + [
            'user_id' => $user->id,
            'company_name' => $name,
            'short_id' => 'C' . strtoupper(substr(uniqid(), -5)),
            'client_types' => ['fulfilment'],
            'portal_features' => ['inventory'],
            'status' => 'active',
        ]);

        return [$user, $client];
    }

    /** A product of the client's, verified with 100 in stock unless told otherwise. */
    protected function makeProduct(Client $client, array $attributes = []): ClientProduct
    {
        return ClientProduct::create($attributes + [
            'client_id' => $client->id,
            'product_code' => 'P-' . strtoupper(substr(uniqid(), -6)),
            'name' => 'Blue Mug',
            'sku' => 'MUG-BLUE',
            'quantity' => 100,
            'unit_price' => 25,
            'verification_status' => 'verified',
        ]);
    }

    /** The client typed `$quantity` on a form that showed `$shown` (the stock now, unless given). */
    protected function stockRequest(ClientProduct $product, int $quantity, ?int $shown = null, array $attributes = []): InventoryRequest
    {
        return InventoryRequest::create($attributes + [
            'client_id' => $product->client_id,
            'client_product_id' => $product->id,
            'type' => InventoryRequest::STOCK,
            'requested_quantity' => $quantity,
            'stock_at_request' => $shown ?? $product->quantity,
        ]);
    }

    /** @param  array<string, mixed>  $changes */
    protected function detailsRequest(ClientProduct $product, array $changes, array $attributes = []): InventoryRequest
    {
        return InventoryRequest::create($attributes + [
            'client_id' => $product->client_id,
            'client_product_id' => $product->id,
            'type' => InventoryRequest::DETAILS,
            'changes' => $changes,
            'original' => collect($changes)->except('remove_image_ids')->map(fn ($value, $field) => $product->{$field})->all(),
        ]);
    }
}

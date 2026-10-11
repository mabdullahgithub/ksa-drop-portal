<?php

namespace App\Services\Inventory;

use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\InventoryRequest;
use App\Models\User;
use App\Notifications\InventoryRequestReviewedNotification;
use App\Notifications\InventoryRequestSubmittedNotification;
use App\Notifications\ProductSubmittedNotification;
use App\Support\ClientAccess;
use Illuminate\Support\Collection;

/**
 * Who hears about what on the Requests page: the team when a client asks for
 * something, the client when it is decided.
 *
 * The team members told are the ones who can open that queue and may see
 * that client. A notification that fails never undoes the thing it was about.
 */
class InventoryRequestNotifier
{
    /** Request type => the permission that opens its tab. */
    private const QUEUE = [
        InventoryRequest::STOCK => 'view stock requests',
        InventoryRequest::DETAILS => 'view detail requests',
    ];

    /**
     * A client asked for a change to a verified product.
     */
    public function submitted(InventoryRequest $request): void
    {
        $this->quietly(fn () => $this->team([self::QUEUE[$request->type]], $request->client_id)
            ->each(fn (User $user) => $user->notify(new InventoryRequestSubmittedNotification($request))));
    }

    /**
     * The team approved or rejected it.
     */
    public function reviewed(InventoryRequest $request): void
    {
        $this->quietly(fn () => $request->client?->user?->notify(new InventoryRequestReviewedNotification($request)));
    }

    /**
     * A client added a product, or sent a rejected one back in. Admins have
     * always been told; so is anyone who reviews new products.
     */
    public function productSubmitted(ClientProduct $product, Client $client): void
    {
        $this->quietly(fn () => $this->admins()
            ->merge($this->team(['view product requests', 'review client products'], $client->id))
            ->unique('id')
            ->each(fn (User $user) => $user->notify(new ProductSubmittedNotification($product, $client))));
    }

    /**
     * @return Collection<int, User>
     */
    private function admins(): Collection
    {
        // Asking for a role that was never created throws; that only means
        // nobody holds it.
        try {
            return User::role(['admin', 'superadmin'])->get();
        } catch (\Throwable) {
            return new Collection;
        }
    }

    /**
     * @param  list<string>  $permissions  Holding any one is enough.
     * @return Collection<int, User>
     */
    private function team(array $permissions, int $clientId): Collection
    {
        return User::permission($permissions)->get()->filter(function (User $user) use ($clientId) {
            $clients = ClientAccess::idsFor($user);

            return $clients === null || in_array($clientId, $clients, true);
        })->values();
    }

    private function quietly(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable) {
        }
    }
}

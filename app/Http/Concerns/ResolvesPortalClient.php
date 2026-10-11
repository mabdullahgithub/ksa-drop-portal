<?php

namespace App\Http\Concerns;

use App\Models\Client;

/**
 * The client a portal request is about: the one being impersonated when
 * someone on the team opened the portal as a client, otherwise the signed-in
 * client's own.
 */
trait ResolvesPortalClient
{
    protected function resolveClient(): ?Client
    {
        if (session()->has('impersonate.client_id')) {
            return Client::find(session('impersonate.client_id'));
        }

        return auth()->user()->client ?? null;
    }

    /**
     * The same, for the My Inventory endpoints: only a fulfilment client
     * with the inventory feature gets through.
     */
    protected function resolveFulfilmentClient(): Client
    {
        $client = $this->resolveClient();

        if (! $client || ! $client->is_fulfilment || ! in_array('inventory', $client->portal_features ?? [])) {
            abort(403);
        }

        return $client;
    }
}

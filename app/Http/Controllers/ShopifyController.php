<?php

namespace App\Http\Controllers;

use App\Jobs\ShopifyOrderSyncJob;
use App\Models\Client;
use App\Models\ClientShopifyConnection;
use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyController extends Controller
{
    public function __construct(private ShopifyService $shopify) {}

    /**
     * Resolve the acting client (impersonation-aware, mirrors PortalController).
     */
    private function resolveClient(): ?Client
    {
        if (session()->has('impersonate.client_id')) {
            return Client::find(session('impersonate.client_id'));
        }

        return auth()->user()->client ?? null;
    }

    /**
     * Shopify redirects here with a temporary code after every OAuth grant —
     * the initial App Store install, a reinstall, or a re-grant opened from
     * inside the store's own Shopify admin. There is exactly one place the
     * code is ever exchanged: whether or not a KSA Drop client is linked yet
     * is decided *after* the exchange, never before it. A grant from Shopify
     * must never be discarded — App Store review requirement 2.3.1 forbids a
     * second, portal-initiated OAuth handshake to "finish" a connection that
     * already happened on a Shopify-owned surface.
     */
    public function callback(Request $request)
    {
        $shop   = $this->shopify->normalizeShopDomain((string) $request->shop);
        $client = auth()->check() ? $this->resolveClient() : null;

        // Every rejection below refuses the connection with a log line. A
        // portal user goes back to Connectors with a message; a visitor with
        // no portal session (install started inside the Shopify admin) lands
        // on the login page with the reason — never a bare error page, and
        // never back into the admin app, which would restart the OAuth hop.
        $reject = function (string $reason, string $message) use ($request, $shop) {
            Log::warning('Shopify OAuth callback rejected', [
                'reason'        => $reason,
                'shop'          => $shop,
                'state_present' => $request->filled('state'),
                'user_id'       => auth()->id(),
            ]);

            if (auth()->check()) {
                return redirect()->route('portal.connectors')->with('error', $message);
            }

            return redirect()->route('login')->with(
                'status',
                $message . ' Sign in to KSA Drop, then connect the store from Connectors.'
            );
        };

        if (! $this->shopify->isValidShopDomain($shop)) {
            return $reject('invalid_shop', 'Shopify sent back an invalid store domain. Please try again.');
        }

        // CSRF: the state is a signed "shop|timestamp" token minted by us at
        // the start of the handshake — self-verifying, and bound to this shop,
        // so no session storage is needed (the embedded-install flow has none).
        if (! $this->shopify->verifyState((string) $request->state, $shop)) {
            return $reject(
                'state_invalid',
                'Your connection attempt expired or was tampered with. Please reopen the app from Shopify Admin and try again.'
            );
        }

        // Authenticity: validate Shopify's HMAC over the query params. Sign the raw
        // query string — the decoded params drop the %3D padding on `host`. Read it
        // off the server bag, not getQueryString(), which re-encodes and sorts.
        if (! $this->shopify->verifyOauthHmac($request->query(), $request->server('QUERY_STRING'))) {
            return $reject(
                'hmac_failed',
                'We could not verify the response from Shopify. Please try connecting again.'
            );
        }

        // A logged-in portal user with no linked client can't own a connection.
        if (auth()->check() && ! $client) {
            return $reject('no_client', 'No client account is linked to your login. Please contact support.');
        }

        try {
            $token = $this->shopify->exchangeCodeForToken($shop, (string) $request->code);
        } catch (\Throwable $e) {
            Log::error('Shopify OAuth exchange failed', ['shop' => $shop, 'error' => $e->getMessage()]);

            return $reject('exchange_failed', 'Failed to connect Shopify store. Please try again.');
        }

        // A portal session happened to be active in the same browser (e.g. the
        // client reopened the app from Shopify Admin while also logged into
        // the portal) — attach directly, no separate claim step needed.
        if ($client) {
            return $this->attachConnectionToClient($shop, $token, $client);
        }

        // No portal session. If the store is already linked to a client
        // (reinstall after uninstall, or a re-grant), approving the install
        // in that store's admin proves control of the store — ownership is
        // unchanged, so just refresh the tokens on the existing link.
        $existing = ClientShopifyConnection::where('shop_domain', $shop)
            ->whereNotNull('client_id')
            ->first();

        if ($existing) {
            return $this->relinkExistingConnection($token, $existing, $shop);
        }

        // Fresh install, not linked to any KSA Drop client yet. Store the
        // grant now — the merchant links their KSA Drop account afterward
        // from the portal, which only attaches the existing record and never
        // re-runs OAuth.
        return $this->storeUnlinkedConnection($shop, $token);
    }

    /**
     * Link an already-installed (but unlinked) Shopify connection to the
     * current KSA Drop client. Never talks to Shopify's OAuth endpoint — the
     * token was already obtained when the merchant installed the app from
     * Shopify, so this only updates a foreign key.
     *
     * The claim_token proves the caller actually controls the store — it can
     * only have been minted by EmbeddedAppController::claimToken(), which
     * requires a valid Shopify App Bridge session token for that exact shop.
     * Without it, `shop` would be nothing more than user-supplied input: any
     * portal user could link (and see the order history and PII of) any
     * store just by knowing or guessing its domain.
     */
    public function claim(Request $request)
    {
        $request->validate([
            'shop'        => 'required|string|max:255',
            'claim_token' => 'required|string',
        ]);

        $client = $this->resolveClient();

        if (! $client) {
            return response()->json(['message' => 'No client account is linked to your login.'], 422);
        }

        $shop = $this->shopify->normalizeShopDomain($request->shop);

        if (! $this->shopify->verifyClaimToken($request->claim_token, $shop)) {
            return response()->json([
                'message' => 'This connect link has expired. Please reopen the app from Shopify Admin and try again.',
            ], 422);
        }

        $connection = ClientShopifyConnection::whereNull('client_id')
            ->where('shop_domain', $shop)
            ->first();

        if (! $connection) {
            return response()->json([
                'message' => 'We could not find a pending installation for that store. Install KSA Drop from the Shopify App Store first, then come back here.',
            ], 404);
        }

        // A client can only have one Shopify store — release any existing link.
        ClientShopifyConnection::where('client_id', $client->id)
            ->where('id', '!=', $connection->id)
            ->get()
            ->each(function (ClientShopifyConnection $old) use ($client) {
                Log::warning('Shopify store unlinked in favor of a new claim', [
                    'shop'      => $old->shop_domain,
                    'client_id' => $client->id,
                ]);

                $old->delete();
            });

        $connection->update(['client_id' => $client->id]);

        $this->dispatchSyncFor($connection, $shop);

        Log::info('Shopify connection claimed by client', ['shop' => $shop, 'client_id' => $client->id]);

        return response()->json([
            'message'     => 'Shopify store connected. Recent orders are syncing in the background.',
            'shop_domain' => $shop,
        ]);
    }

    /**
     * OAuth grant with a resolved KSA Drop client (portal-session case).
     */
    private function attachConnectionToClient(string $shop, array $token, Client $client)
    {
        // The store may still be linked to a different client. Reaching this point
        // means the user approved the install while logged in as this client, which
        // proves they control it — so the store moves to them and the stale link is
        // released. shop_domain is unique, so the old row cannot just be marked
        // disconnected; it has to go. Only the link and its tokens are dropped —
        // orders already synced stay with the old client.
        ClientShopifyConnection::where('shop_domain', $shop)
            ->where('client_id', '!=', $client->id)
            ->get()
            ->each(function (ClientShopifyConnection $old) use ($shop, $client) {
                Log::warning('Shopify store reassigned to a new client', [
                    'shop'           => $shop,
                    'from_client_id' => $old->client_id,
                    'to_client_id'   => $client->id,
                    'by_user_id'     => auth()->id(),
                ]);

                $old->delete();
            });

        // Preserve sync_mode if reconnecting an existing connection.
        $previousSyncMode = ClientShopifyConnection::where('client_id', $client->id)->value('sync_mode');

        $connection = ClientShopifyConnection::updateOrCreate(
            ['client_id' => $client->id],
            [
                'shop_domain'              => $shop,
                'access_token'             => $token['access_token'],
                'refresh_token'            => $token['refresh_token'],
                'token_expires_at'         => $this->shopify->expiryFromSeconds($token['expires_in']),
                'refresh_token_expires_at' => $this->shopify->expiryFromSeconds($token['refresh_token_expires_in']),
                'scope'                    => $token['scope'],
                'sync_mode'                => $previousSyncMode ?? 'auto_sync',
                'status'                   => 'active',
                'webhooks_registered'      => false,
                'connected_at'             => now(),
            ]
        );

        $this->registerWebhooksFor($connection, $shop, $token['access_token']);
        $this->dispatchSyncFor($connection, $shop);

        session()->forget(['shopify_oauth_nonce', 'shopify_oauth_shop']);

        return redirect()->route('portal.connectors')
            ->with('success', 'Shopify store connected. Recent orders are syncing in the background.');
    }

    /**
     * Reinstall / re-grant of a store that already belongs to a client, done
     * from inside the store's own Shopify admin (no portal session). Refresh
     * the tokens on the existing link — the client never changes — and land
     * the merchant back in the embedded app.
     */
    private function relinkExistingConnection(array $token, ClientShopifyConnection $connection, string $shop)
    {
        $connection->update([
            'access_token'             => $token['access_token'],
            'refresh_token'            => $token['refresh_token'],
            'token_expires_at'         => $this->shopify->expiryFromSeconds($token['expires_in']),
            'refresh_token_expires_at' => $this->shopify->expiryFromSeconds($token['refresh_token_expires_in']),
            'scope'                    => $token['scope'],
            'status'                   => 'active',
            'connected_at'             => now(),
        ]);

        $this->registerWebhooksFor($connection, $shop, $token['access_token']);
        $this->dispatchSyncFor($connection, $shop);

        Log::info('Shopify store relinked via admin re-grant', [
            'shop'      => $shop,
            'client_id' => $connection->client_id,
        ]);

        return redirect()->away($this->shopify->adminAppUrl($shop));
    }

    /**
     * Fresh install with no KSA Drop client linked yet. Store the grant now —
     * the merchant claims it from the portal afterward without re-running
     * OAuth.
     */
    private function storeUnlinkedConnection(string $shop, array $token)
    {
        $connection = ClientShopifyConnection::updateOrCreate(
            ['shop_domain' => $shop],
            [
                'client_id'                => null,
                'access_token'             => $token['access_token'],
                'refresh_token'            => $token['refresh_token'],
                'token_expires_at'         => $this->shopify->expiryFromSeconds($token['expires_in']),
                'refresh_token_expires_at' => $this->shopify->expiryFromSeconds($token['refresh_token_expires_in']),
                'scope'                    => $token['scope'],
                'status'                   => 'active',
                'webhooks_registered'      => false,
                'connected_at'             => now(),
            ]
        );

        $this->registerWebhooksFor($connection, $shop, $token['access_token']);

        Log::info('Shopify install completed for unlinked store — token stored', ['shop' => $shop]);

        return redirect()->away($this->shopify->adminAppUrl($shop));
    }

    private function registerWebhooksFor(ClientShopifyConnection $connection, string $shop, string $accessToken): void
    {
        try {
            $results = $this->shopify->registerWebhooks($shop, $accessToken);
            $connection->update(['webhooks_registered' => ! in_array(false, $results, true)]);
        } catch (\Throwable $e) {
            Log::warning('Shopify webhook registration error', ['shop' => $shop, 'error' => $e->getMessage()]);
        }
    }

    private function dispatchSyncFor(ClientShopifyConnection $connection, string $shop): void
    {
        try {
            ShopifyOrderSyncJob::dispatch($connection->id);
        } catch (\Throwable $e) {
            Log::warning('Shopify order sync dispatch failed', ['shop' => $shop, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Disconnect the current client's Shopify store.
     */
    public function disconnect(Request $request)
    {
        $client = $this->resolveClient();
        $connection = $client?->shopifyConnection;

        if (! $connection) {
            return response()->json(['message' => 'No connection found.'], 404);
        }

        $connection->update([
            'status'                   => 'disconnected',
            'access_token'             => null,
            'refresh_token'            => null,
            'token_expires_at'         => null,
            'refresh_token_expires_at' => null,
            // Release the account link, not just the tokens. Disconnecting here
            // does not uninstall the app from Shopify, so the next embedded load
            // re-runs token exchange and reactivates this row — with client_id
            // still set that silently undoes the disconnect. Clearing it also
            // returns the row to the claimable state (client_id null) that
            // claim() looks for, which is what makes reconnecting possible.
            'client_id'                => null,
            // Sync preferences belonged to the account that just left; the row
            // is now claimable by whoever controls the store.
            'sync_mode'                => 'auto_sync',
            'sync_filters'             => null,
        ]);

        return response()->json(['message' => 'Shopify store disconnected.']);
    }

    /**
     * Re-attempt webhook registration on an existing connection. Used after the
     * app is approved for protected customer data (order webhooks are gated on it).
     */
    public function retryWebhooks(Request $request)
    {
        $client = $this->resolveClient();
        $connection = $client?->shopifyConnection;

        if (! $connection) {
            return response()->json(['message' => 'No connection found.'], 404);
        }

        try {
            $token   = $this->shopify->getValidToken($connection);
            $results = $this->shopify->registerWebhooks($connection->shop_domain, $token);
            $allOk   = ! in_array(false, $results, true);

            $connection->update(['webhooks_registered' => $allOk]);

            return response()->json([
                'webhooks_registered' => $allOk,
                'results'             => $results,
                'message' => $allOk
                    ? 'Live order sync is now active.'
                    : 'Some webhooks could not be registered. Check that the app is approved for protected customer data.',
            ], $allOk ? 200 : 422);
        } catch (\Throwable $e) {
            Log::warning('Shopify retryWebhooks failed', [
                'shop' => $connection->shop_domain, 'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Could not register webhooks. Please try again.'], 422);
        }
    }

    /**
     * Switch between auto_sync and manual_approval.
     */
    public function updateSyncMode(Request $request)
    {
        $validated = $request->validate([
            'sync_mode' => 'required|in:auto_sync,manual_approval',
        ]);

        $client = $this->resolveClient();
        $connection = $client?->shopifyConnection;

        if (! $connection) {
            return response()->json(['message' => 'No connection found.'], 404);
        }

        $connection->update(['sync_mode' => $validated['sync_mode']]);

        return response()->json([
            'message'   => 'Sync mode updated.',
            'sync_mode' => $connection->sync_mode,
        ]);
    }
}

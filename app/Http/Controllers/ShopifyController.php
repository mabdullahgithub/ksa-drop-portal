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
     * Step 1 — client submits a shop domain; redirect to Shopify for permission.
     */
    public function redirect(Request $request)
    {
        $request->validate(['shop' => 'required|string|max:255']);

        $shop = $this->shopify->normalizeShopDomain($request->shop);

        if (! $this->shopify->isValidShopDomain($shop)) {
            return redirect()->route('portal.connectors')
                ->with('error', 'That does not look like a valid Shopify store URL. Use your permanent .myshopify.com domain (found in Shopify admin under Settings → Domains), not your custom domain.');
        }

        $nonce = bin2hex(random_bytes(16));

        session([
            'shopify_oauth_nonce' => $nonce,
            'shopify_oauth_shop'  => $shop,
        ]);

        return redirect()->away($this->shopify->buildAuthUrl($shop, $nonce));
    }

    /**
     * Step 2 — Shopify redirects back with a temporary code.
     */
    public function callback(Request $request)
    {
        $shop = $this->shopify->normalizeShopDomain((string) $request->shop);

        // Every rejection below refuses the connection. They used to abort(403),
        // which dead-ended the merchant on an error page with nothing in the logs;
        // now each one says why and sends them back to Connectors to retry.
        $reject = function (string $reason, string $message) use ($request, $shop) {
            Log::warning('Shopify OAuth callback rejected', [
                'reason'          => $reason,
                'shop'            => $shop,
                'state_present'   => $request->filled('state'),
                'nonce_in_session' => session()->has('shopify_oauth_nonce'),
                'session_shop'    => session('shopify_oauth_shop'),
                'user_id'         => auth()->id(),
            ]);

            return redirect()->route('portal.connectors')->with('error', $message);
        };

        // CSRF: state must match the nonce we stored in session.
        if (! $request->filled('state') || $request->state !== session('shopify_oauth_nonce')) {
            return $reject(
                'state_mismatch',
                'Your connection attempt expired or was started in another tab. Please click Connect Shopify and try again.'
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

        $client = $this->resolveClient();

        if (! $client) {
            return $reject(
                'no_client',
                'No client account is linked to your login. Please contact support.'
            );
        }

        // The shop in the callback must match the one we initiated OAuth for.
        if ($shop !== session('shopify_oauth_shop')) {
            return $reject(
                'shop_mismatch',
                'The store Shopify sent back does not match the one you entered. Please try again.'
            );
        }

        try {
            $token = $this->shopify->exchangeCodeForToken($shop, (string) $request->code);
        } catch (\Throwable $e) {
            Log::error('Shopify OAuth exchange failed', ['shop' => $shop, 'error' => $e->getMessage()]);

            return redirect()->route('portal.connectors')
                ->with('error', 'Failed to connect Shopify store. Please try again.');
        }

        // The store may still be linked to a different client. Reaching this point
        // means the user approved the install inside that store's own Shopify admin,
        // which proves they control it — so the store moves to them and the stale
        // link is released. shop_domain is unique, so the old row cannot just be
        // marked disconnected; it has to go. Only the link and its tokens are
        // dropped — orders already synced stay with the old client.
        //
        // Done after the token exchange so a failed exchange cannot strand the
        // previous owner with no connection.
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

        // Register order webhooks (best-effort; logged on failure).
        try {
            $results = $this->shopify->registerWebhooks($shop, $token['access_token']);
            $connection->update(['webhooks_registered' => ! in_array(false, $results, true)]);
        } catch (\Throwable $e) {
            Log::warning('Shopify webhook registration error', ['shop' => $shop, 'error' => $e->getMessage()]);
        }

        // Pull last 60 days of orders in the background.
        try {
            ShopifyOrderSyncJob::dispatch($connection->id);
        } catch (\Throwable $e) {
            Log::warning('Shopify order sync dispatch failed', ['shop' => $shop, 'error' => $e->getMessage()]);
        }

        session()->forget(['shopify_oauth_nonce', 'shopify_oauth_shop']);

        return redirect()->route('portal.connectors')
            ->with('success', 'Shopify store connected. Recent orders are syncing in the background.');
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
            'status' => 'disconnected',
            'access_token' => null,
            'refresh_token' => null,
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

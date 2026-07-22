<?php

namespace App\Http\Middleware;

use App\Models\ClientShopifyConnection;
use App\Services\ShopifyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates embedded-app API requests via the Shopify App Bridge session
 * token (a short-lived HS256 JWT signed with the app's API secret). The
 * embedded iframe has no Laravel session — every request carries a fresh
 * Bearer token minted by App Bridge.
 *
 * JWT verification itself lives in ShopifyService (shared with the
 * claim-token endpoint, which needs the same proof-of-shop-session check for
 * stores that aren't linked to a client yet — this middleware requires a
 * linked client, so it can't be reused there as-is).
 */
class VerifyShopifySessionToken
{
    public function __construct(private ShopifyService $shopify) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Missing session token.'], 401);
        }

        $claims = $this->shopify->verifySessionToken($token);

        if ($claims === null) {
            return response()->json(['message' => 'Invalid session token.'], 401);
        }

        $shopDomain = $this->shopify->shopFromSessionTokenClaims($claims);

        if (! $shopDomain) {
            return response()->json(['message' => 'Invalid session token destination.'], 401);
        }

        $connection = ClientShopifyConnection::with('client')
            ->where('shop_domain', $shopDomain)
            ->where('status', 'active')
            ->first();

        if (! $connection || ! $connection->client) {
            return response()->json(['message' => 'Store is not connected.'], 401);
        }

        $request->attributes->set('shopify_connection', $connection);

        return $next($request);
    }
}

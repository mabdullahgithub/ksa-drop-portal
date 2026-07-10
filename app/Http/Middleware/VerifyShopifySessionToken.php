<?php

namespace App\Http\Middleware;

use App\Models\ClientShopifyConnection;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates embedded-app API requests via the Shopify App Bridge session
 * token (a short-lived HS256 JWT signed with the app's API secret). The
 * embedded iframe has no Laravel session — every request carries a fresh
 * Bearer token minted by App Bridge.
 *
 * Verified manually (base64url HMAC) rather than via a JWT library, matching
 * the hand-rolled verification style in ShopifyService (verifyOauthHmac,
 * verifyWebhookHmac).
 */
class VerifyShopifySessionToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Missing session token.'], 401);
        }

        $claims = $this->verifyAndDecode($token);

        if ($claims === null) {
            return response()->json(['message' => 'Invalid session token.'], 401);
        }

        $shopDomain = $this->shopFromDest((string) ($claims['dest'] ?? ''));

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

    /**
     * Verify signature + standard claims. Returns the payload claims, or null
     * on any failure.
     */
    private function verifyAndDecode(string $jwt): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        [$header64, $payload64, $signature64] = $parts;

        $header = json_decode($this->base64UrlDecode($header64) ?: '', true);

        if (($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $expected = $this->base64UrlEncode(hash_hmac(
            'sha256',
            "{$header64}.{$payload64}",
            (string) config('services.shopify.secret'),
            true
        ));

        if (! hash_equals($expected, $signature64)) {
            return null;
        }

        $claims = json_decode($this->base64UrlDecode($payload64) ?: '', true);

        if (! is_array($claims)) {
            return null;
        }

        $now = time();

        if (($claims['exp'] ?? 0) < $now || ($claims['nbf'] ?? 0) > $now + 5) {
            return null;
        }

        if (($claims['aud'] ?? null) !== (string) config('services.shopify.key')) {
            return null;
        }

        return $claims;
    }

    /**
     * dest claim is "https://{shop}.myshopify.com" — extract the bare domain.
     */
    private function shopFromDest(string $dest): ?string
    {
        $host = strtolower((string) parse_url($dest, PHP_URL_HOST));

        return preg_match('/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/', $host) ? $host : null;
    }

    private function base64UrlDecode(string $data): string|false
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

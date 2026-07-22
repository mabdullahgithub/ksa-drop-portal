<?php

namespace App\Http\Controllers\Embedded;

use App\Http\Controllers\Controller;
use App\Models\ClientShopifyConnection;
use App\Services\ShopifyService;
use Illuminate\Http\Request;

/**
 * Serves the HTML shell for the embedded Shopify Admin app. This URL is
 * configured as the "App URL" in the Shopify Partner Dashboard — Shopify
 * iframes it with ?shop=...&host=...&embedded=1 query params.
 *
 * It is also the entry point for App Store installs: Shopify sends the
 * merchant here TOP-LEVEL (no embedded=1) when they click Install, and app
 * review requires the app to immediately begin OAuth — not render UI — for
 * a store that has not granted access yet (requirements 2.3.2 / 2.3.4).
 *
 * Embedded (iframe) loads never start OAuth: reaching the iframe means the
 * grant already happened on Shopify's side, so the shell always renders and
 * an unlinked store gets the in-app onboarding screen instead. This split is
 * also what makes an OAuth redirect loop structurally impossible.
 */
class EmbeddedAppController extends Controller
{
    public function __construct(private ShopifyService $shopify) {}

    public function index(Request $request)
    {
        $shop = $this->shopify->normalizeShopDomain((string) $request->query('shop', ''));

        // Fresh install (or reinstall after uninstall): top-level request for a
        // shop with no usable token. Start the OAuth grant right away. Only for
        // requests genuinely signed by Shopify — the HMAC covers the query string.
        if ($request->query('embedded') !== '1'
            && $this->shopify->isValidShopDomain($shop)
            && $this->needsInstall($shop)
            && $request->filled('hmac')
            && $this->shopify->verifyOauthHmac($request->query(), $request->server('QUERY_STRING'))) {
            return redirect()->away(
                $this->shopify->buildAuthUrl($shop, $this->shopify->makeState($shop))
            );
        }

        $portalUrl = rtrim((string) config('services.shopify.portal_url'), '/');

        return view('embedded', [
            'shop'           => $shop,
            'host'           => (string) $request->query('host', ''),
            'portalUrl'      => $portalUrl,
            'portalLoginUrl' => $portalUrl . '/login',
        ]);
    }

    /**
     * A store needs the OAuth grant when it has no connection at all, the
     * connection was disconnected (uninstall), or its tokens are gone.
     */
    private function needsInstall(string $shop): bool
    {
        $connection = ClientShopifyConnection::where('shop_domain', $shop)->first();

        return ! $connection
            || $connection->status !== 'active'
            || ! $connection->access_token;
    }

    /**
     * Resolve the store's connection state and mint a short-lived claim token
     * for the onboarding "connect your store" link. Verifies the caller's App
     * Bridge session token directly (not the shopify.session middleware — that
     * requires an already-linked client, which is exactly what an unlinked
     * store doesn't have yet). The claim token then lets the portal's claim
     * endpoint trust the shop domain in the deep link instead of taking it as
     * bare, unverified user input — closing an IDOR where anyone with a portal
     * login could link any store just by knowing or guessing its domain.
     *
     * This is also where installation completes. Under managed installation
     * Shopify never calls the OAuth redirect_uri, so this — the first
     * authenticated call the embedded app makes on every load — is the app's
     * only chance to turn the session token into an API token. Without it the
     * store has no connection record at all and the portal's claim endpoint
     * has nothing to attach the account to.
     */
    public function claimToken(Request $request)
    {
        $token  = $request->bearerToken();
        $claims = $token ? $this->shopify->verifySessionToken($token) : null;
        $shop   = $claims ? $this->shopify->shopFromSessionTokenClaims($claims) : null;

        if (! $shop) {
            return response()->json(['message' => 'Invalid session token.'], 401);
        }

        $connection = $this->shopify->ensureInstalled($shop, $token);

        // Token exchange is the only thing standing between a fresh install and
        // a usable connection, and it can fail (revoked app, clock skew, bad
        // credentials). Report that instead of letting the merchant walk into
        // the portal and hit a "no pending installation" 404 with no
        // explanation — the claim cannot succeed without a stored grant.
        $installed = (bool) ($connection && $connection->status === 'active' && $connection->access_token);

        // `linked` lets the embedded app decide, without a failed request, whether
        // to load the dashboard or show the onboarding screen. Probing the
        // client-gated API endpoints instead would surface a 401 in the browser
        // console on every unlinked-store visit (harmless, but noise during app
        // review). The claim token is still returned for the onboarding deep link.
        $linked = $installed && $connection->client_id !== null;

        return response()->json([
            'shop'      => $shop,
            'linked'    => $linked,
            'installed' => $installed,
            'token'     => $installed ? $this->shopify->makeClaimToken($shop) : null,
        ]);
    }
}

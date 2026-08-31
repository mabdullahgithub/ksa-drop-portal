<?php

namespace App\Http\Controllers\Embedded;

use App\Http\Controllers\Controller;
use App\Models\ClientShopifyConnection;
use App\Services\EmbeddedPayloadService;
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
    public function __construct(
        private ShopifyService $shopify,
        private EmbeddedPayloadService $payload,
    ) {}

    public function index(Request $request)
    {
        $shop = $this->shopify->normalizeShopDomain((string) $request->query('shop', ''));

        // One lookup, reused by the install check and the bootstrap below.
        $connection = $this->shopify->isValidShopDomain($shop)
            ? ClientShopifyConnection::with('client')->where('shop_domain', $shop)->first()
            : null;

        // Fresh install (or reinstall after uninstall): top-level request for a
        // shop with no usable token. Start the OAuth grant right away. Only for
        // requests genuinely signed by Shopify — the HMAC covers the query string.
        if ($request->query('embedded') !== '1'
            && $this->shopify->isValidShopDomain($shop)
            && $this->needsInstall($connection)
            && $request->filled('hmac')
            && $this->shopify->verifyOauthHmac($request->query(), $request->server('QUERY_STRING'))) {
            return redirect()->away(
                $this->shopify->buildAuthUrl($shop, $this->shopify->makeState($shop))
            );
        }

        $portalUrl = rtrim((string) config('services.shopify.portal_url'), '/');

        // Both embedded routes render this same shell; the view decides which
        // skeleton paints and which payload is worth inlining.
        $view = $request->routeIs('embedded.shopify.settings') ? 'settings' : 'dashboard';

        $bootstrap = $this->bootstrap($request, $connection, $view);

        $response = response()->view('embedded', [
            'shop'           => $shop,
            'host'           => (string) $request->query('host', ''),
            'portalUrl'      => $portalUrl,
            'portalLoginUrl' => $portalUrl . '/login',
            'view'           => $view,
            'linkedHint'     => $this->isLinked($connection),
            'bootstrap'      => $bootstrap,
        ]);

        // The inlined payload is this merchant's order data. Never let a shared
        // cache hold it, and never let the browser replay it for another shop.
        if ($bootstrap !== null) {
            $response->header('Cache-Control', 'private, no-store, max-age=0');
        }

        return $response;
    }

    /**
     * Payload for the very first paint, inlined into the shell so the embedded
     * app renders real content without waiting on a round trip.
     *
     * Only ever built from the `id_token` Shopify appends to the app URL on
     * embedded loads — the same App Bridge session-token JWT the API routes
     * authenticate with, verified the same way. The unsigned ?shop= param is
     * never trusted for this. When the token is absent, expired, or belongs to
     * a store that isn't linked to a client yet, this returns null and the app
     * falls back to its normal fetch path.
     *
     * This is strictly a rendering shortcut. Installation still completes via
     * /api/claim-token on every load, exactly as before — the frontend calls it
     * regardless of whether a payload was inlined here.
     */
    private function bootstrap(Request $request, ?ClientShopifyConnection $connection, string $view): ?array
    {
        $idToken = (string) $request->query('id_token', '');

        if ($idToken === '') {
            return null;
        }

        $claims = $this->shopify->verifySessionToken($idToken);
        $tokenShop = $claims ? $this->shopify->shopFromSessionTokenClaims($claims) : null;

        if (! $tokenShop) {
            return null;
        }

        // The verified token — not the query string — decides which store's data
        // this is. A mismatch means the ?shop= param was rewritten; drop the
        // shortcut and let the authenticated endpoints answer.
        if (! $connection || $connection->shop_domain !== $tokenShop) {
            return null;
        }

        if (! $this->isLinked($connection)) {
            return null;
        }

        return $view === 'settings'
            ? ['settings' => $this->payload->settings($connection)]
            : ['dashboard' => $this->payload->dashboard($connection)];
    }

    /**
     * Whether the store is installed AND linked to a KSA Drop client — the same
     * condition VerifyShopifySessionToken enforces and claimToken reports as
     * `linked`, so the shell can never disagree with the API about it.
     */
    private function isLinked(?ClientShopifyConnection $connection): bool
    {
        return (bool) $connection
            && $connection->status === 'active'
            && $connection->access_token
            && $connection->client_id !== null
            && $connection->client !== null;
    }

    /**
     * A store needs the OAuth grant when it has no connection at all, the
     * connection was disconnected (uninstall), or its tokens are gone.
     */
    private function needsInstall(?ClientShopifyConnection $connection): bool
    {
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

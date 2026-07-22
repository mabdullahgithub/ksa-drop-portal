import { embeddedFetch } from './api-client'

const root = document.getElementById('embedded-root')

/**
 * Values injected by resources/views/embedded.blade.php.
 *
 * SHOP is the merchant's *.myshopify.com domain for the current admin
 * session. PORTAL_URL/PORTAL_LOGIN_URL point at the KSA Drop portal, used
 * to link the store when it isn't yet associated with a KSA Drop account
 * (the embedded API returns 401 until then).
 */
export const SHOP = root?.dataset.shop || ''

export const PORTAL_URL = root?.dataset.portalUrl || 'https://portal.ksadrop.com'

export const PORTAL_LOGIN_URL =
    root?.dataset.portalLoginUrl || 'https://portal.ksadrop.com/login'

/**
 * Deep link that finishes account linking without any manual data entry:
 * lands on Connectors with the shop and a short-lived signed claim token
 * prefilled (via the login page first if the merchant has no portal session
 * — Laravel returns them here after sign-in).
 *
 * The claim token is minted server-side from this browser's live App Bridge
 * session token, proving it's really inside this shop's Shopify Admin. The
 * portal's claim endpoint verifies it instead of trusting the shop domain
 * alone — otherwise anyone with a portal login could link any store just by
 * knowing or guessing its domain.
 */
export function buildPortalConnectUrl(shop: string, claimToken: string): string {
    return `${PORTAL_URL}/portal/connectors?shop=${encodeURIComponent(shop)}&claim_token=${encodeURIComponent(claimToken)}`
}

export async function getPortalConnectUrl(): Promise<string> {
    if (!SHOP) return PORTAL_LOGIN_URL

    try {
        const { shop, token } = await embeddedFetch<{ shop: string; token: string }>(
            '/claim-token'
        )
        return buildPortalConnectUrl(shop, token)
    } catch {
        return PORTAL_LOGIN_URL
    }
}

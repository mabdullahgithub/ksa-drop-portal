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
 * lands on Connectors with the shop prefilled (via the login page first if
 * the merchant has no portal session — Laravel returns them here after
 * sign-in).
 */
export const PORTAL_CONNECT_URL = SHOP
    ? `${PORTAL_URL}/portal/connectors?shop=${encodeURIComponent(SHOP)}`
    : PORTAL_LOGIN_URL

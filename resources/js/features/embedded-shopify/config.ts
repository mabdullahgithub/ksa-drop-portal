const root = document.getElementById('embedded-root')

/**
 * Portal login URL, injected by resources/views/embedded.blade.php. Opened in
 * a new tab to link/connect the store when it isn't yet associated with a
 * KSA Drop account (the embedded API returns 401 until then).
 */
export const PORTAL_LOGIN_URL =
    root?.dataset.portalLoginUrl || 'https://portal.ksadrop.com/login'

import { api, ApiError } from '@/features/rider-app/api'
import type { Me, RiderBoot, RiderRole } from '@/features/rider-app/types'

/**
 * Who is signed in on this phone.
 *
 * The web app is told by the portal's page (window.__RIDER__) and signed in
 * by a cookie. The phone app keeps the device token itself and asks the
 * portal who it belongs to each time it opens.
 */

const TOKEN_KEY = 'rider_device_token'
const WHO_KEY = 'rider_who'

type Who = { name: string; role?: RiderRole }

/**
 * The token lives in the web view's own storage, which only this app can
 * read. To move it to the Keychain / Keystore, swap these three for
 * NativePHP's SecureStorage (a paid plugin) — nothing else reads the key.
 */
export function readToken(): string | null {
  try {
    return localStorage.getItem(TOKEN_KEY)
  } catch {
    return null
  }
}

export function saveToken(token: string) {
  localStorage.setItem(TOKEN_KEY, token)
}

export function forgetToken() {
  try {
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(WHO_KEY)
  } catch {
    // Nothing was kept.
  }
}

/** Who it was last time, for opening the app with no signal. */
function lastWho(): Who | null {
  try {
    return JSON.parse(localStorage.getItem(WHO_KEY) || 'null')
  } catch {
    return null
  }
}

function rememberWho(who: Who) {
  try {
    localStorage.setItem(WHO_KEY, JSON.stringify(who))
  } catch {
    // Opening offline will fall back to the rider's app.
  }
}

/**
 * What the portal's page works out on the server (RiderAppController): the
 * app, the activation link, or phone + PIN.
 */
export async function start(activation: string | null): Promise<RiderBoot> {
  let reason: string | null = null

  if (readToken()) {
    try {
      const { rider } = await api.get<Me>('/rider/api/me')
      const who = { name: rider.name, role: rider.role }
      rememberWho(who)
      return { mode: 'app', rider: who }
    } catch (error) {
      const e = error as ApiError
      // The portal refused the token. Anything else — no signal, a server
      // error — says nothing about the rider: open the app as they left it.
      if (e.status !== 401 && e.status !== 403) return { mode: 'app', rider: lastWho() }

      reason = e.code === 'signed_out' ? null : (e.code ?? null)
      // A suspended rider keeps their token: reactivating them just works.
      if (e.code !== 'suspended') forgetToken()
    }
  }

  // A phone that's already signed in ignores the link, like the web app.
  return activation ? { mode: 'activate', token: activation } : { mode: 'sign_in', reason }
}

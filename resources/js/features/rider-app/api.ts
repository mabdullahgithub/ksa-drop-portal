/**
 * Tiny fetch client for /rider/api/*. On the portal the device cookie rides
 * along on its own (same origin, HttpOnly) and the server checks Origin on
 * writes instead of a CSRF token. The phone app (lib/host.ts) calls the
 * portal from its own origin and sends its token instead.
 */

import { host } from './lib/host'

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public code?: string,
    public errors?: Record<string, string[]>,
    public data?: any
  ) {
    super(message)
  }

  get offline() {
    return this.status === 0
  }

  /** First validation message, or the general one. */
  get firstMessage() {
    const first = this.errors ? Object.values(this.errors)[0]?.[0] : undefined
    return first || this.message
  }
}

/** Codes that mean this phone is no longer signed in. */
const SIGNED_OUT_CODES = ['signed_out', 'replaced', 'revoked', 'suspended']

const ROLE_RELOAD_KEY = 'rider_role_reload_at'

/**
 * The office changed this person between rider and inventory manager while
 * the app was open: reload into the other app. Once, so a phone that keeps
 * getting the answer can't loop.
 */
function reloadForNewRole() {
  try {
    const last = Number(sessionStorage.getItem(ROLE_RELOAD_KEY) || 0)
    if (Date.now() - last < 10_000) return
    sessionStorage.setItem(ROLE_RELOAD_KEY, String(Date.now()))
  } catch {
    // Storage blocked: stay put rather than risk a reload loop.
    return
  }

  window.location.reload()
}

async function request<T>(method: 'GET' | 'POST', url: string, body?: FormData | object): Promise<T> {
  const { native, apiBase, token } = host()
  const headers: Record<string, string> = { Accept: 'application/json' }
  const init: RequestInit = { method, headers, credentials: native ? 'omit' : 'same-origin' }

  if (native) {
    headers['X-Rider-Client'] = 'native'
    const bearer = token()
    if (bearer) headers.Authorization = `Bearer ${bearer}`
  }

  if (body instanceof FormData) {
    init.body = body
  } else if (body) {
    headers['Content-Type'] = 'application/json'
    init.body = JSON.stringify(body)
  }

  let response: Response
  try {
    response = await fetch(apiBase + url, init)
  } catch {
    throw new ApiError(0, 'offline', 'offline')
  }

  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    const code: string | undefined = data?.code ?? (response.status === 429 ? 'too_many' : undefined)

    if (code && SIGNED_OUT_CODES.includes(code)) {
      window.dispatchEvent(new CustomEvent('rider:signed-out', { detail: code }))
    }

    if (code === 'wrong_role') reloadForNewRole()

    throw new ApiError(response.status, data?.message || `HTTP ${response.status}`, code, data?.errors, data)
  }

  return data as T
}

export const api = {
  get: <T>(url: string) => request<T>('GET', url),
  post: <T>(url: string, body?: FormData | object) => request<T>('POST', url, body),
}

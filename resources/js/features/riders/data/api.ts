import axios from 'axios'

/** Matches the server's own cache, so nothing shown is staler than it would be anyway. */
const FRESH_FOR = 60_000
const MAX_ENTRIES = 50

const cache = new Map<string, { at: number; data: unknown }>()

type Params = Record<string, string | number | null | undefined>

const keyOf = (url: string, params: Params) =>
  `${url}?${Object.entries(params)
    .filter(([, v]) => v !== undefined && v !== null && v !== '')
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([k, v]) => `${k}=${encodeURIComponent(String(v))}`)
    .join('&')}`

/** What an earlier request already fetched, if it is still fresh. */
export function peekCached<T>(url: string, params: Params): T | undefined {
  const hit = cache.get(keyOf(url, params))
  return hit && Date.now() - hit.at < FRESH_FOR ? (hit.data as T) : undefined
}

/**
 * GET with a one-minute memory for the Top performers section: flipping back
 * to a preset, metric or rider already seen costs the server nothing.
 */
export async function cachedGet<T>(url: string, params: Params, signal?: AbortSignal): Promise<T> {
  const cached = peekCached<T>(url, params)
  if (cached !== undefined) return cached

  const { data } = await axios.get<T>(url, { params, signal })

  const key = keyOf(url, params)
  cache.delete(key)
  cache.set(key, { at: Date.now(), data })
  if (cache.size > MAX_ENTRIES) cache.delete(cache.keys().next().value!)

  return data
}

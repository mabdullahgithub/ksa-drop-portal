import { useCallback, useEffect, useState } from 'react'

export type RecycleBinTab = 'orders' | 'clients' | 'inventory' | 'users'

/** Who deleted a record, and from where. Null for rows deleted before auditing existed. */
export type DeletedByInfo = {
  name: string | null
  email: string | null
  ip: string | null
  device: string | null
  user_agent: string | null
}

export type TrashedOrder = {
  id: number
  order_number: string
  customer_name: string | null
  client_name: string | null
  client_trashed: boolean
  items_count: number
  total_price: string | number | null
  fulfillment_status: string | null
  deleted_at: string | null
  deleted_by: DeletedByInfo | null
}

export type TrashedClient = {
  id: number
  company_name: string
  short_id: string | null
  contact_person: string | null
  status: string | null
  orders_count: number
  products_count: number
  deleted_at: string | null
  deleted_by: DeletedByInfo | null
}

export type TrashedInventoryItem = {
  /** Composite "product:7" / "client_product:7" — ids collide across the two tables. */
  row_id: string
  item_type: 'product' | 'client_product'
  id: number
  name: string | null
  sku: string | null
  code: string | null
  price: string | number | null
  quantity: number | null
  client_name: string | null
  parent_trashed: boolean
  deleted_at: string | null
  deleted_by: DeletedByInfo | null
}

export type TrashedUser = {
  id: number
  name: string
  email: string
  roles: string[]
  is_client: boolean
  deleted_at: string | null
  deleted_by: DeletedByInfo | null
}

export type RecycleBinMeta = {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export type RecycleBinCounts = {
  orders: number
  clients: number
  inventory: number
  users: number
}

export type RestoreResult = {
  message: string
  restored_count: number
  requested_count: number
  blocked?: { name: string; reason: string }[]
  renamed?: { name: string; from: string; to: string }[]
}

export type PurgeResult = {
  message: string
  purged_count: number
  /** Rows the server refused to purge (users only, for now), with why. */
  blocked?: { name: string; reason: string }[]
}

const getCsrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

/**
 * The server answers 423 when the PIN has not been entered or the unlock
 * expired. Distinguished from an ordinary failure so the page can drop back to
 * the lock screen rather than show an error toast.
 */
export class RecycleBinLockedError extends Error {}

const LOCKED_STATUS = 423

async function post<T>(url: string, body?: unknown): Promise<T> {
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': getCsrfToken(),
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  const payload = await response.json().catch(() => null)

  if (response.status === LOCKED_STATUS) {
    throw new RecycleBinLockedError(payload?.message || 'Recycle bin is locked')
  }

  if (!response.ok) {
    throw new Error(payload?.message || 'Request failed')
  }

  return payload as T
}

/** Exchange the PIN for an unlocked session. The PIN is only ever checked server-side. */
export async function unlockRecycleBin(pin: string): Promise<void> {
  await post('/api/recycle-bin/unlock', { pin })
}

/** Re-lock on the way out, so the next visit prompts again. */
export async function lockRecycleBin(): Promise<void> {
  try {
    await post('/api/recycle-bin/lock')
  } catch {
    // Leaving the page should never surface an error; the TTL re-locks anyway.
  }
}

/** Tab badge counts, refetched after anything that changes the bin. */
export function useRecycleBinCounts(enabled = true) {
  const [counts, setCounts] = useState<RecycleBinCounts>({ orders: 0, clients: 0, inventory: 0, users: 0 })

  const refresh = useCallback(async () => {
    if (!enabled) return

    try {
      const response = await fetch('/api/recycle-bin/counts', { headers: { Accept: 'application/json' } })
      if (response.ok) setCounts(await response.json())
    } catch {
      // A failed count only costs a stale badge; leave the last known value.
    }
  }, [enabled])

  useEffect(() => {
    void refresh()
  }, [refresh])

  return { counts, refreshCounts: refresh }
}

/**
 * One tab's listing. `T` is the row shape for that tab.
 *
 * `enabled` is false for tabs the user lacks permission for, so the hook never
 * fires a request that would 403.
 */
export function useRecycleBinList<T>(tab: RecycleBinTab, enabled = true, onLocked?: () => void) {
  const [items, setItems] = useState<T[]>([])
  const [meta, setMeta] = useState<RecycleBinMeta | null>(null)
  const [loading, setLoading] = useState(enabled)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(25)
  const [search, setSearch] = useState('')

  const fetchPage = useCallback(async () => {
    if (!enabled) {
      setItems([])
      setLoading(false)
      return
    }

    setLoading(true)
    try {
      const params = new URLSearchParams({ page: String(page), per_page: String(perPage) })
      if (search.trim()) params.set('search', search.trim())

      const response = await fetch(`/api/recycle-bin/${tab}?${params}`, {
        headers: { Accept: 'application/json' },
      })

      if (response.status === LOCKED_STATUS) {
        setItems([])
        setMeta(null)
        onLocked?.()
        return
      }

      if (!response.ok) throw new Error('Failed to load recycle bin')

      const payload = await response.json()
      setItems(payload.data ?? [])
      setMeta(payload.meta ?? null)
    } catch {
      setItems([])
      setMeta(null)
    } finally {
      setLoading(false)
    }
  }, [tab, enabled, page, perPage, search, onLocked])

  useEffect(() => {
    void fetchPage()
  }, [fetchPage])

  // A page that empties out after a restore or purge would otherwise strand
  // the user on a blank page past the end of the list.
  useEffect(() => {
    if (!loading && items.length === 0 && meta && meta.current_page > 1) {
      setPage(meta.current_page - 1)
    }
  }, [loading, items.length, meta])

  return {
    items,
    meta,
    loading,
    page,
    setPage,
    perPage,
    setPerPage: (size: number) => {
      setPerPage(size)
      setPage(1)
    },
    search,
    setSearch: (term: string) => {
      setSearch(term)
      setPage(1)
    },
    refresh: fetchPage,
  }
}

/** Restore / purge actions. Ids are numbers everywhere except inventory. */
export function useRecycleBinActions(tab: RecycleBinTab) {
  const restore = (ids: (number | string)[]) =>
    post<RestoreResult>(`/api/recycle-bin/${tab}/restore`, { ids })

  const purge = (ids: (number | string)[]) =>
    post<PurgeResult>(`/api/recycle-bin/${tab}/purge`, { ids })

  const purgeAll = () => post<PurgeResult>(`/api/recycle-bin/${tab}/purge-all`)

  return { restore, purge, purgeAll }
}

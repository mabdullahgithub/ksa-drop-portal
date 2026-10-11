import { useCallback, useEffect, useRef, useState } from 'react'
import type { ClientProduct, InventoryRequest } from '@/types/client'

/** The three queues of the Requests page. */
export type RequestTab = 'products' | 'stock' | 'details'

/** What is waiting in each queue; null for one the person cannot open. */
export type RequestCounts = Record<RequestTab, number | null>

export type RequestClient = { id: number; company_name: string }

/** A product a client added, as the New products queue lists it. */
export type RequestedProduct = ClientProduct & { client: RequestClient }

export type RequestFilters = {
  status: string
  client_id: string
  search: string
  page: number
}

export type RequestMeta = {
  current_page: number
  last_page: number
  total: number
  from: number | null
  to: number | null
}

/** What a bulk action did, and what it had to leave. */
export type BulkResult = {
  done: number
  skipped: { id: number; product: string | null; message: string }[]
}

const getCsrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

/**
 * The server answers 423 when the PIN has not been entered or the unlock sat
 * idle too long. Told apart from an ordinary failure so the page can drop
 * back to the PIN prompt rather than show an error.
 */
export class RequestsLockedError extends Error {}

const LOCKED_STATUS = 423

/**
 * POST and hand back the JSON, or throw the server's own message: "already
 * approved", "the client changed this request", a validation error.
 */
async function post<T>(url: string, body: unknown | FormData = {}): Promise<T> {
  const isForm = body instanceof FormData

  const response = await fetch(url, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'X-CSRF-TOKEN': getCsrfToken(),
      ...(isForm ? {} : { 'Content-Type': 'application/json' }),
    },
    body: isForm ? body : JSON.stringify(body),
  })

  const payload = await response.json().catch(() => null)

  if (response.status === LOCKED_STATUS) {
    throw new RequestsLockedError(payload?.message || 'The requests page is locked')
  }

  if (!response.ok) {
    const firstError = payload?.errors ? (Object.values(payload.errors).flat()[0] as string) : null
    throw new Error(firstError || payload?.message || 'Request failed')
  }

  return payload as T
}

/** Exchange the PIN for an unlocked session. The PIN is only ever checked server-side. */
export async function unlockRequests(pin: string): Promise<void> {
  await post('/api/requests/unlock', { pin })
}

/** Re-lock on the way out, so the next visit prompts again. */
export async function lockRequests(): Promise<void> {
  try {
    await post('/api/requests/lock')
  } catch {
    // Leaving the page should never surface an error; it locks on its own when idle.
  }
}

/**
 * The tab counts, and the clients to filter by.
 */
export function useRequestCounts(enabled: boolean, onLocked?: () => void) {
  const [counts, setCounts] = useState<RequestCounts>({ products: null, stock: null, details: null })
  const [clients, setClients] = useState<RequestClient[]>([])

  const refreshCounts = useCallback(async () => {
    if (!enabled) return
    try {
      const response = await fetch('/api/requests/counts', { headers: { Accept: 'application/json' } })
      if (response.status === LOCKED_STATUS) {
        onLocked?.()
        return
      }
      if (!response.ok) return
      const data = await response.json()
      setCounts(data.counts)
      setClients(data.clients)
    } catch {
      // The counts are a convenience; the queues still load without them.
    }
  }, [enabled, onLocked])

  useEffect(() => {
    refreshCounts()
  }, [refreshCounts])

  return { counts, clients, refreshCounts }
}

/**
 * One queue: its rows for the filters given, a page at a time.
 */
export function useRequestQueue<Row>(tab: RequestTab, initial: Partial<RequestFilters> = {}, onLocked?: () => void) {
  const [filters, setFilters] = useState<RequestFilters>({
    status: 'pending',
    client_id: 'all',
    search: '',
    page: 1,
    ...initial,
  })
  const [rows, setRows] = useState<Row[]>([])
  const [meta, setMeta] = useState<RequestMeta | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(false)
  // A slow answer to an older filter must not overwrite a newer one.
  const latest = useRef(0)

  const load = useCallback(async () => {
    const call = ++latest.current
    setLoading(true)
    setError(false)

    const params = new URLSearchParams({ status: filters.status, page: String(filters.page) })
    if (filters.client_id !== 'all') params.set('client_id', filters.client_id)
    if (filters.search.trim()) params.set('search', filters.search.trim())

    try {
      const response = await fetch(`/api/requests/${tab}?${params}`, { headers: { Accept: 'application/json' } })
      // Locked again (idle too long): back to the PIN prompt, not an error.
      if (response.status === LOCKED_STATUS) {
        if (call === latest.current) {
          setRows([])
          onLocked?.()
        }
        return
      }
      if (!response.ok) throw new Error('Failed')
      const data = await response.json()
      if (call !== latest.current) return
      setRows(data.data)
      setMeta({
        current_page: data.current_page,
        last_page: data.last_page,
        total: data.total,
        from: data.from,
        to: data.to,
      })
    } catch {
      if (call === latest.current) setError(true)
    } finally {
      if (call === latest.current) setLoading(false)
    }
  }, [tab, filters, onLocked])

  useEffect(() => {
    load()
  }, [load])

  /** Any filter but the page itself goes back to the first page. */
  const updateFilters = useCallback((changed: Partial<RequestFilters>) => {
    setFilters((previous) => ({ ...previous, page: 1, ...changed }))
  }, [])

  return { rows, meta, loading, error, filters, updateFilters, refresh: load }
}

/** Verify, reject or keep pending a product a client added. */
export function reviewRequestedProduct(
  productId: number,
  action: 'verify' | 'reject' | 'pending',
  images: File[],
  rejectionReason: string,
  isOutOfStock: boolean,
  receivedQuantity: number | null
) {
  const body = new FormData()
  body.append('action', action)
  if (rejectionReason) body.append('rejection_reason', rejectionReason)
  body.append('is_out_of_stock', isOutOfStock ? '1' : '0')
  if (receivedQuantity !== null) body.append('received_quantity', String(receivedQuantity))
  images.forEach((file) => body.append('images[]', file))

  return post<{ message: string }>(`/api/requests/products/${productId}/review`, body)
}

/**
 * `units` is the count that really arrived (or, for a request to lower stock,
 * the count to take off). `revision` is the one on screen, so a request the
 * client changed meanwhile is refused instead of approved blind.
 */
export function approveStockRequest(request: InventoryRequest, units: number, markInStock: boolean) {
  return post<{ message: string; request: InventoryRequest }>(`/api/requests/stock/${request.id}/approve`, {
    units,
    mark_in_stock: markInStock,
    revision: request.revision,
  })
}

export function approveDetailsRequest(request: InventoryRequest) {
  return post<{ message: string; request: InventoryRequest }>(`/api/requests/details/${request.id}/approve`, {
    revision: request.revision,
  })
}

export function rejectInventoryRequest(request: InventoryRequest, reason: string) {
  return post<{ message: string; request: InventoryRequest }>(`/api/requests/${request.type}/${request.id}/reject`, {
    reason,
    revision: request.revision,
  })
}

export function bulkApproveRequests(type: 'stock' | 'details', ids: number[]) {
  return post<BulkResult>(`/api/requests/${type}/bulk-approve`, { ids })
}

export function bulkRejectRequests(type: 'stock' | 'details', ids: number[], reason: string) {
  return post<BulkResult>(`/api/requests/${type}/bulk-reject`, { ids, reason })
}

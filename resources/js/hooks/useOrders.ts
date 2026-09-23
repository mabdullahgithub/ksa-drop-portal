import { useState, useEffect, useCallback, useRef } from 'react'
import { router } from '@inertiajs/react'
import type {
  Order,
  OrderFilters,
  PaginatedOrders,
  OrderStatistics,
  OrderFilterOptions,
  BulkUpdatePayload,
} from '@/types/order'

/** Serialise filters to query params: arrays comma-joined, booleans as 1/0, empties dropped. */
export function filtersToParams(filters: OrderFilters): URLSearchParams {
  const params = new URLSearchParams()

  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      if (Array.isArray(value)) {
        if (value.length > 0) {
          params.append(key, value.join(','))
        }
      } else if (typeof value === 'boolean') {
        params.append(key, value ? '1' : '0')
      } else {
        params.append(key, String(value))
      }
    }
  })

  return params
}

export function useOrders(initialFilters: OrderFilters = {}) {
  const [orders, setOrders] = useState<PaginatedOrders | null>(null)
  const [loading, setLoading] = useState(true)
  const [filters, setFilters] = useState<OrderFilters>(initialFilters)

  const fetchOrders = useCallback(async (newFilters?: OrderFilters) => {
    setLoading(true)
    const params = filtersToParams(newFilters || filters)

    try {
      const response = await fetch(`/api/orders?${params}`)
      const data = await response.json()
      setOrders(data)
    } catch (error) {
      console.error('Error fetching orders:', error)
    } finally {
      setLoading(false)
    }
  }, [filters])

  useEffect(() => {
    fetchOrders()
  }, [])

  const updateFilters = useCallback((newFilters: Partial<OrderFilters>) => {
    setFilters((prev) => ({ ...prev, ...newFilters }))
    fetchOrders({ ...filters, ...newFilters })
  }, [filters, fetchOrders])

  const refresh = useCallback(() => {
    return fetchOrders()
  }, [fetchOrders])

  const meta = orders ? {
    current_page: orders.current_page,
    last_page: orders.last_page,
    per_page: orders.per_page,
    total: orders.total,
    from: orders.from,
    to: orders.to,
  } : undefined

  return {
    orders: orders?.data || [],
    meta,
    loading,
    filters,
    updateFilters,
    refresh,
  }
}

export function useOrder(orderId: number) {
  const [order, setOrder] = useState<Order | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const fetchOrder = async () => {
      try {
        const response = await fetch(`/api/orders/${orderId}`)
        const data = await response.json()
        setOrder(data)
      } catch (error) {
        console.error('Error fetching order:', error)
      } finally {
        setLoading(false)
      }
    }

    if (orderId) {
      fetchOrder()
    }
  }, [orderId])

  return { order, loading }
}

/**
 * Order statistics for the given filters, refetched whenever they change.
 * Paging, sorting and the All / Assigned tab don't affect the stats, so they
 * are left out of the request. Previous stats stay visible while refetching.
 */
export function useOrderStatistics(filters: OrderFilters = {}) {
  const [statistics, setStatistics] = useState<OrderStatistics | null>(null)
  const [loading, setLoading] = useState(true)
  const latestRequest = useRef(0)

  const { page, per_page, sort_by, sort_order, has_shipment, assigned_to, ...statFilters } = filters
  const query = filtersToParams(statFilters).toString()

  const fetchStatistics = useCallback(async () => {
    const requestId = ++latestRequest.current
    setLoading(true)

    try {
      const response = await fetch(`/api/orders/statistics?${query}`)
      const data = await response.json()
      // Ignore responses that arrive after a newer request was made.
      if (requestId === latestRequest.current) setStatistics(data)
    } catch (error) {
      console.error('Error fetching statistics:', error)
    } finally {
      if (requestId === latestRequest.current) setLoading(false)
    }
  }, [query])

  useEffect(() => {
    fetchStatistics()
  }, [fetchStatistics])

  return { statistics, loading, refresh: fetchStatistics }
}

export function useFilterOptions() {
  const [options, setOptions] = useState<OrderFilterOptions | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const fetchOptions = async () => {
      try {
        const response = await fetch('/api/orders/filter-options')
        const data = await response.json()
        setOptions(data)
      } catch (error) {
        console.error('Error fetching filter options:', error)
      } finally {
        setLoading(false)
      }
    }

    fetchOptions()
  }, [])

  return { options, loading }
}

export type BulkDeleteResult = {
  message: string
  deleted_count: number
  requested_count: number
  blocked: { id: number; order_number: string; reason: string }[]
}

export function useOrderMutations() {
  const [loading, setLoading] = useState(false)

  const updateFulfillmentStatus = async (
    orderId: number,
    status: string
  ): Promise<boolean> => {
    setLoading(true)
    try {
      const response = await fetch(`/api/orders/${orderId}/fulfillment-status`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN':
            document
              .querySelector('meta[name="csrf-token"]')
              ?.getAttribute('content') || '',
        },
        body: JSON.stringify({ fulfillment_status: status }),
      })

      if (!response.ok) throw new Error('Failed to update status')

      return true
    } catch (error) {
      console.error('Error updating fulfillment status:', error)
      return false
    } finally {
      setLoading(false)
    }
  }

  const updateFinancialStatus = async (
    orderId: number,
    status: string,
    refundedAmount?: number
  ): Promise<boolean> => {
    setLoading(true)
    try {
      const response = await fetch(`/api/orders/${orderId}/financial-status`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN':
            document
              .querySelector('meta[name="csrf-token"]')
              ?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          financial_status: status,
          ...(refundedAmount && { refunded_amount: refundedAmount }),
        }),
      })

      if (!response.ok) throw new Error('Failed to update status')

      return true
    } catch (error) {
      console.error('Error updating financial status:', error)
      return false
    } finally {
      setLoading(false)
    }
  }

  const updateOrder = async (
    orderId: number,
    data: Partial<Order>
  ): Promise<boolean> => {
    setLoading(true)
    try {
      const response = await fetch(`/api/orders/${orderId}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN':
            document
              .querySelector('meta[name="csrf-token"]')
              ?.getAttribute('content') || '',
        },
        body: JSON.stringify(data),
      })

      if (!response.ok) throw new Error('Failed to update order')

      return true
    } catch (error) {
      console.error('Error updating order:', error)
      return false
    } finally {
      setLoading(false)
    }
  }

  const bulkUpdate = async (payload: BulkUpdatePayload): Promise<boolean> => {
    setLoading(true)
    try {
      const response = await fetch('/api/orders/bulk-update', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN':
            document
              .querySelector('meta[name="csrf-token"]')
              ?.getAttribute('content') || '',
        },
        body: JSON.stringify(payload),
      })

      if (!response.ok) throw new Error('Failed to bulk update')

      return true
    } catch (error) {
      console.error('Error bulk updating orders:', error)
      throw error
    } finally {
      setLoading(false)
    }
  }

  /**
   * Move orders to the recycle bin (a soft delete).
   *
   * Orders carrying an active shipment are refused by the server, so the
   * response reports deleted_count against requested_count and names what was
   * skipped rather than failing the whole batch.
   */
  const bulkDelete = async (orderIds: number[]): Promise<BulkDeleteResult> => {
    setLoading(true)
    try {
      const response = await fetch('/api/orders/bulk-delete', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN':
            document
              .querySelector('meta[name="csrf-token"]')
              ?.getAttribute('content') || '',
        },
        body: JSON.stringify({ order_ids: orderIds }),
      })

      const payload = await response.json().catch(() => null)

      if (!response.ok) throw new Error(payload?.message || 'Failed to delete orders')

      return payload as BulkDeleteResult
    } catch (error) {
      console.error('Error deleting orders:', error)
      throw error
    } finally {
      setLoading(false)
    }
  }

  const deleteOrder = async (orderId: number): Promise<void> => {
    setLoading(true)
    try {
      const response = await fetch(`/api/orders/${orderId}`, {
        method: 'DELETE',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN':
            document
              .querySelector('meta[name="csrf-token"]')
              ?.getAttribute('content') || '',
        },
      })

      const payload = await response.json().catch(() => null)

      if (!response.ok) throw new Error(payload?.message || 'Failed to delete order')
    } finally {
      setLoading(false)
    }
  }

  const exportOrders = (filters: OrderFilters = {}) => {
    const params = new URLSearchParams()
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        if (Array.isArray(value)) {
          if (value.length > 0) {
            params.append(key, value.join(','))
          }
        } else if (typeof value === 'boolean') {
          params.append(key, value ? '1' : '0')
        } else {
          params.append(key, String(value))
        }
      }
    })

    window.location.href = `/api/orders/export?${params}`
  }

  return {
    loading,
    updateFulfillmentStatus,
    updateFinancialStatus,
    updateOrder,
    bulkUpdate,
    bulkDelete,
    deleteOrder,
    exportOrders,
  }
}

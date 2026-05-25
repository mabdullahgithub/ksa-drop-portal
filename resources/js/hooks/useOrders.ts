import { useState, useEffect, useCallback } from 'react'
import { router } from '@inertiajs/react'
import type {
  Order,
  OrderFilters,
  PaginatedOrders,
  OrderStatistics,
  OrderFilterOptions,
  BulkUpdatePayload,
} from '@/types/order'

export function useOrders(initialFilters: OrderFilters = {}) {
  const [orders, setOrders] = useState<PaginatedOrders | null>(null)
  const [loading, setLoading] = useState(true)
  const [filters, setFilters] = useState<OrderFilters>(initialFilters)

  const fetchOrders = useCallback(async (newFilters?: OrderFilters) => {
    setLoading(true)
    const params = new URLSearchParams()

    const activeFilters = newFilters || filters

    Object.entries(activeFilters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        if (key === 'client_ids' && Array.isArray(value)) {
          if (value.length > 0) {
            params.append(key, value.join(','))
          }
        } else {
          params.append(key, String(value))
        }
      }
    })

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
    fetchOrders()
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

export function useOrderStatistics(startDate?: string, endDate?: string) {
  const [statistics, setStatistics] = useState<OrderStatistics | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const fetchStatistics = async () => {
      const params = new URLSearchParams()
      if (startDate) params.append('start_date', startDate)
      if (endDate) params.append('end_date', endDate)

      try {
        const response = await fetch(`/api/orders/statistics?${params}`)
        const data = await response.json()
        setStatistics(data)
      } catch (error) {
        console.error('Error fetching statistics:', error)
      } finally {
        setLoading(false)
      }
    }

    fetchStatistics()
  }, [startDate, endDate])

  return { statistics, loading }
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
      return false
    } finally {
      setLoading(false)
    }
  }

  const exportOrders = (filters: OrderFilters = {}) => {
    const params = new URLSearchParams()
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        if (key === 'client_ids' && Array.isArray(value)) {
          if (value.length > 0) {
            params.append(key, value.join(','))
          }
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
    exportOrders,
  }
}

import { useState, useEffect } from 'react'
import { usePermissions } from '@/hooks/use-permissions'
import type { InboxStats } from '@/features/whatsapp/types'

export interface OrderStats {
  total_orders: number
  unassigned_orders: number
  assigned_orders: number
  total_revenue: number
  average_order_value: number
  today_orders: number
  today_revenue: number
  by_shipment_status: Array<{ status: string; count: number }>
  by_tag: Array<{ id: number; name: string; color: string; count: number }>
}

export interface ClientStats {
  total_clients: number
  active_clients: number
  inactive_clients: number
  suspended_clients: number
  dropshippers_count: number
  fulfilment_count: number
}

export interface RecentOrder {
  id: number
  order_number: string
  customer_name: string | null
  financial_status: string
  fulfillment_status: string
  formatted_total: string
  total: string
  created_at: string
}

export interface DashboardData {
  /** null when the request failed or the user cannot view that section. */
  orders: OrderStats | null
  whatsapp: InboxStats | null
  clients: ClientStats | null
  recent_orders: RecentOrder[]
}

const EMPTY: DashboardData = { orders: null, whatsapp: null, clients: null, recent_orders: [] }

/**
 * One section failing must not blank the others, so every request resolves to
 * null instead of rejecting.
 */
async function getJson<T>(url: string): Promise<T | null> {
  try {
    const res = await fetch(url, { headers: { Accept: 'application/json' } })
    if (!res.ok) return null
    return (await res.json()) as T
  } catch {
    return null
  }
}

export function useDashboard() {
  const { can } = usePermissions()
  const canOrders = can('view orders')
  const canClients = can('view client')

  const [data, setData] = useState<DashboardData>(EMPTY)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false

    const load = async () => {
      setLoading(true)

      // Today's figures come from the statistics endpoint over a single-day
      // range rather than by summing a page of orders — a page cap would
      // silently under-report revenue on a busy day. `en-CA` is YYYY-MM-DD in
      // the viewer's own timezone, so "today" is the local day, not UTC's.
      // The range scope compares raw datetimes, so the end has to be spelled
      // out to the last second or only midnight-exact orders would match.
      const today = new Date().toLocaleDateString('en-CA')
      const dayRange = `start_date=${today}&end_date=${encodeURIComponent(`${today} 23:59:59`)}`

      const [stats, todayStats, waStats, clientStats, ordersPage] = await Promise.all([
        canOrders ? getJson<Record<string, unknown>>('/api/orders/statistics') : null,
        canOrders ? getJson<Record<string, unknown>>(`/api/orders/statistics?${dayRange}`) : null,
        // The inbox lives behind order permissions: a conversation is just an
        // order's message thread.
        canOrders ? getJson<InboxStats>('/api/whatsapp/stats') : null,
        canClients ? getJson<ClientStats>('/api/clients/statistics') : null,
        canOrders ? getJson<{ data?: RecentOrder[] }>('/api/orders?per_page=8&sort_by=created_at&sort_order=desc') : null,
      ])

      if (cancelled) return

      setData({
        orders: stats
          ? {
              total_orders: Number(stats.total_orders ?? 0),
              unassigned_orders: Number(stats.unassigned_orders ?? 0),
              assigned_orders: Number(stats.assigned_orders ?? 0),
              total_revenue: Number(stats.total_revenue ?? 0),
              average_order_value: Number(stats.average_order_value ?? 0),
              today_orders: Number(todayStats?.total_orders ?? 0),
              today_revenue: Number(todayStats?.total_revenue ?? 0),
              by_shipment_status: (stats.by_shipment_status as OrderStats['by_shipment_status']) ?? [],
              by_tag: (stats.by_tag as OrderStats['by_tag']) ?? [],
            }
          : null,
        whatsapp: waStats,
        clients: clientStats,
        recent_orders: ordersPage?.data ?? [],
      })
      setLoading(false)
    }

    load()
    return () => {
      cancelled = true
    }
  }, [canOrders, canClients])

  return { data, loading }
}

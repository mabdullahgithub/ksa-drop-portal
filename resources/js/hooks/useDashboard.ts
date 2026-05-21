import { useState, useEffect } from 'react'

export interface DashboardStats {
  total_orders: number
  total_revenue: number
  average_order_value: number
  today_orders: number
  today_revenue: number
  total_clients: number
  active_clients: number
  new_clients_this_month: number
  total_products: number
  active_products: number
  by_payment_method: Array<{ payment_method: string; count: number }>
  by_financial_status: Array<{ financial_status: string; count: number }>
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
  stats: DashboardStats | null
  recent_orders: RecentOrder[]
}

export function useDashboard() {
  const [data, setData] = useState<DashboardData>({ stats: null, recent_orders: [] })
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false

    const load = async () => {
      setLoading(true)
      try {
        const [statsRes, ordersRes, clientsRes] = await Promise.all([
          fetch('/api/orders/statistics'),
          fetch('/api/orders?per_page=8&sort_by=created_at&sort_order=desc'),
          fetch('/api/clients/statistics'),
        ])

        const [statsData, ordersData, clientsData] = await Promise.all([
          statsRes.json(),
          ordersRes.json(),
          clientsRes.json(),
        ])

        // Compute today stats from orders (best effort)
        const today = new Date().toISOString().slice(0, 10)
        const todayOrdersRes = await fetch(`/api/orders?per_page=100&start_date=${today}&end_date=${today}&sort_by=created_at&sort_order=desc`)
        const todayOrdersData = await todayOrdersRes.json()
        const todayOrders: RecentOrder[] = todayOrdersData?.data ?? []
        const todayRevenue = todayOrders.reduce((sum: number, o: RecentOrder) => sum + parseFloat(o.total || '0'), 0)

        // New clients this month
        const monthStart = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10)
        const newClientsRes = await fetch(`/api/clients?created_after=${monthStart}&per_page=1`)
        const newClientsData = await newClientsRes.json()

        if (cancelled) return

        setData({
          stats: {
            total_orders: statsData.total_orders ?? 0,
            total_revenue: statsData.total_revenue ?? 0,
            average_order_value: statsData.average_order_value ?? 0,
            today_orders: todayOrders.length,
            today_revenue: todayRevenue,
            total_clients: clientsData.total_clients ?? 0,
            active_clients: clientsData.active_clients ?? 0,
            new_clients_this_month: newClientsData?.total ?? 0,
            total_products: 0,
            active_products: 0,
            by_payment_method: statsData.by_payment_method ?? [],
            by_financial_status: statsData.by_financial_status ?? [],
          },
          recent_orders: ordersData?.data ?? [],
        })
      } catch (e) {
        console.error('Dashboard load error', e)
      } finally {
        if (!cancelled) setLoading(false)
      }
    }

    load()
    return () => { cancelled = true }
  }, [])

  return { data, loading }
}

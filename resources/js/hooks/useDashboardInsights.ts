import { useEffect, useState } from 'react'
import { usePermissions } from '@/hooks/use-permissions'

/** Days back from today; 0 is all time. */
export type InsightRange = 7 | 30 | 90 | 0

export interface CityInsight {
  name: string
  orders: number
  /** null without the revenue permission. */
  revenue: number | null
  delivered: number
}

export interface CourierInsight {
  courier: string
  total: number
  delivered: number
  returned: number
  in_progress: number
  /** Delivered out of every shipment, as a percentage. */
  delivery_rate: number
  avg_delivery_hours: number | null
}

export interface StoreInsight {
  client_id: number
  name: string
  short_id: string | null
  logo: string | null
  orders: number
  revenue: number | null
  delivered: number
  /** Orders per bucket, oldest first: days, weeks or months by range. */
  trend: number[]
}

/** Orders per hour of the day (0–23) and per weekday (Sunday first), Riyadh time. */
export interface OrderRhythm {
  hours: number[]
  weekdays: number[]
}

export interface DashboardInsights {
  cities: { total_orders: number; city_count: number; items: CityInsight[] } | null
  couriers: CourierInsight[] | null
  rhythm: OrderRhythm | null
  stores: StoreInsight[] | null
}

const EMPTY: DashboardInsights = { cities: null, couriers: null, rhythm: null, stores: null }

export function useDashboardInsights(range: InsightRange) {
  const { can } = usePermissions()
  const canCities = can('view dashboard city heatmap')
  const canCouriers = can('view dashboard courier ranking')
  const canRhythm = can('view dashboard order rhythm')
  const canStores = can('view dashboard store ranking')
  const enabled = canCities || canCouriers || canRhythm || canStores

  const [data, setData] = useState<DashboardInsights>(EMPTY)
  const [loading, setLoading] = useState(enabled)

  useEffect(() => {
    if (!enabled) return

    const controller = new AbortController()
    setLoading(true)

    fetch(`/api/orders/dashboard-insights?range=${range}`, {
      headers: { Accept: 'application/json' },
      signal: controller.signal,
    })
      .then((res) => (res.ok ? res.json() : EMPTY))
      .then((json: DashboardInsights) => {
        setData({
          cities: json.cities ?? null,
          couriers: json.couriers ?? null,
          rhythm: json.rhythm ?? null,
          stores: json.stores ?? null,
        })
        setLoading(false)
      })
      .catch((error) => {
        if (error?.name === 'AbortError') return
        setData(EMPTY)
        setLoading(false)
      })

    return () => controller.abort()
  }, [range, enabled])

  return { data, loading, canCities, canCouriers, canRhythm, canStores }
}

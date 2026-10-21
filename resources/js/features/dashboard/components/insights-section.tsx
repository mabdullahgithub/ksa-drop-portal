import { useState } from 'react'
import { cn } from '@/lib/utils'
import { usePermissions } from '@/hooks/use-permissions'
import { type InsightRange, useDashboardInsights } from '@/hooks/useDashboardInsights'
import { CityHeatmap } from './city-heatmap'
import { CourierRanking } from './courier-ranking'
import { OrderRhythm } from './order-rhythm'
import { StoreRanking } from './store-ranking'

const RANGES: Array<{ value: InsightRange; label: string }> = [
  { value: 7, label: '7D' },
  { value: 30, label: '30D' },
  { value: 90, label: '90D' },
  { value: 0, label: 'All' },
]

/**
 * The dashboard's charts — city heatmap, best couriers, order rhythm and best
 * stores — over one shared period. Each chart is its own permission; the section is absent when
 * the person holds none of them.
 */
export function InsightsSection() {
  const [range, setRange] = useState<InsightRange>(0)
  const { data, loading, canCities, canCouriers, canRhythm, canStores } = useDashboardInsights(range)
  const { can } = usePermissions()

  if (!canCities && !canCouriers && !canRhythm && !canStores) return null

  const storeHref = can('view client details') ? (id: number) => `/client/${id}` : undefined

  return (
    <section>
      <div className='mb-3 flex flex-wrap items-end justify-between gap-3'>
        <div>
          <h2 className='text-sm font-semibold tracking-tight'>Insights</h2>
          <p className='text-xs text-muted-foreground'>Where orders go, who delivers them and which stores sell most</p>
        </div>

        <div role='radiogroup' aria-label='Period' className='inline-flex rounded-lg border bg-muted/60 p-0.5'>
          {RANGES.map((r) => (
            <button
              key={r.value}
              type='button'
              role='radio'
              aria-checked={range === r.value}
              onClick={() => setRange(r.value)}
              className={cn(
                'rounded-md px-3 py-1 text-xs font-medium transition-all',
                range === r.value
                  ? 'bg-background text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              )}
            >
              {r.label}
            </button>
          ))}
        </div>
      </div>

      <div className='space-y-4'>
        {canCities && <CityHeatmap cities={data.cities} loading={loading} />}

        {(canCouriers || canRhythm) && (
          <div className={cn('grid gap-4', canCouriers && canRhythm && 'xl:grid-cols-2')}>
            {canCouriers && <CourierRanking couriers={data.couriers} loading={loading} />}
            {canRhythm && <OrderRhythm rhythm={data.rhythm} loading={loading} />}
          </div>
        )}

        {canStores && <StoreRanking stores={data.stores} loading={loading} range={range} storeHref={storeHref} />}
      </div>
    </section>
  )
}

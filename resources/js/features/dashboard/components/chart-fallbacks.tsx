import { Clock, MapPinned, Store, Truck } from 'lucide-react'
import { Skeleton } from '@/components/ui/skeleton'
import { InsightCard } from './insight-card'

/*
 * Placeholders shown while the chart code downloads. CityHeatmap and
 * StoreRanking, CourierRanking and OrderRhythm are lazy-loaded so the charting library stays out of the
 * dashboard's first load; until their chunk arrives these stand in for them.
 *
 * Each one is the card's own loading state, copied exactly — same shell text,
 * same skeleton sizes — so when the real card mounts (still loading, then with
 * data) nothing on the page moves. If you change the loading branch in
 * city-heatmap.tsx or store-ranking.tsx, change the matching one here.
 */

export function CityHeatmapFallback() {
  return (
    <InsightCard
      eyebrow='Geography'
      title='City heatmap'
      description='Where orders ship to across the Kingdom'
      icon={<MapPinned />}
      accent='var(--viz-heat-hi)'
    >
      <div className='grid gap-6 lg:grid-cols-[1.45fr_1fr]'>
        <Skeleton className='aspect-[5/4] w-full rounded-xl' />
        <div className='space-y-3'>
          <Skeleton className='h-40 w-full rounded-xl' />
          {Array.from({ length: 5 }).map((_, i) => (
            <Skeleton key={i} className='h-8 w-full' />
          ))}
        </div>
      </div>
    </InsightCard>
  )
}

export function StoreRankingFallback() {
  return (
    <InsightCard
      eyebrow='Leaderboard'
      title='Best stores'
      description='Order trend of the top five, and the leaders ranked by orders'
      icon={<Store />}
      accent='var(--viz-rank)'
    >
      <div className='grid gap-6 lg:grid-cols-[1.3fr_1fr]'>
        <Skeleton className='h-[280px] w-full rounded-xl' />
        <div className='space-y-3'>
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className='h-9 w-full rounded-lg' />
          ))}
        </div>
      </div>
    </InsightCard>
  )
}

export function CourierRankingFallback() {
  return (
    <InsightCard
      eyebrow='Delivery'
      title='Best couriers'
      description='Ranked by delivery rate: delivered out of all shipments'
      icon={<Truck />}
      accent='var(--viz-good)'
    >
      <div className='grid items-center gap-6 sm:grid-cols-[248px_1fr]'>
        <Skeleton className='mx-auto size-[248px] rounded-full' />
        <div className='space-y-3'>
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className='h-14 w-full rounded-xl' />
          ))}
        </div>
      </div>
    </InsightCard>
  )
}

export function OrderRhythmFallback() {
  return (
    <InsightCard
      eyebrow='Rhythm'
      title='Order rhythm'
      description='When customers place orders, in Riyadh time'
      icon={<Clock />}
      accent='var(--viz-c1)'
      aside={
        <div className='inline-flex shrink-0 rounded-lg border bg-muted/60 p-0.5'>
          {['24 hours', 'Week'].map((label, i) => (
            <span
              key={label}
              className={
                i === 0
                  ? 'rounded-md bg-background px-2.5 py-1 text-[11px] font-medium text-foreground shadow-sm'
                  : 'rounded-md px-2.5 py-1 text-[11px] font-medium text-muted-foreground'
              }
            >
              {label}
            </span>
          ))}
        </div>
      }
    >
      <div className='grid items-center gap-4 sm:grid-cols-[1fr_168px]'>
        <Skeleton className='mx-auto aspect-square w-full max-w-72 rounded-full' />
        <div className='space-y-2'>
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className='h-16 w-full rounded-xl' />
          ))}
        </div>
      </div>
    </InsightCard>
  )
}

import { MapPinned, Store } from 'lucide-react'
import { Skeleton } from '@/components/ui/skeleton'
import { InsightCard } from './insight-card'

/*
 * Placeholders shown while the chart code downloads. CityHeatmap and
 * StoreRanking are lazy-loaded so the charting library stays out of the
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

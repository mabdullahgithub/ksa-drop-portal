import { useMemo, useState } from 'react'
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion'
import { MapPinned, X } from 'lucide-react'
import { Cell, Pie, PieChart } from 'recharts'
import { cn } from '@/lib/utils'
import { compactNumber } from '@/components/stat-card'
import { Skeleton } from '@/components/ui/skeleton'
import type { CityInsight, DashboardInsights } from '@/hooks/useDashboardInsights'
import { REGIONS, type Region, cityPoint, cityRegion } from '../data/saudi-map'
import { CountUp, InsightCard, InsightEmpty, percent } from './insight-card'
import { type MapCity, SaudiHeatMap, heat } from './saudi-heat-map'

const ACCENT = 'var(--viz-heat-hi)'
const LISTED = 6
/** The fill behind a top-city row: one soft blue for every row, so length alone carries the number. */
const ROW_FILL = 'var(--viz-row)'
const EASE = [0.16, 1, 0.3, 1] as const

type City = CityInsight & { rank: number; t: number; region: Region | null }
type Slice = { key: Region | 'other'; label: string; color: string; orders: number; cities: number; top: string | null }

export function CityHeatmap({
  cities,
  loading,
}: {
  cities: DashboardInsights['cities']
  loading: boolean
}) {
  const reduce = useReducedMotion() ?? false
  const [activeCity, setActiveCity] = useState<string | null>(null)
  const [hoverRegion, setHoverRegion] = useState<Region | 'other' | null>(null)
  const [pinnedRegion, setPinnedRegion] = useState<Region | 'other' | null>(null)
  const region = hoverRegion ?? pinnedRegion

  const { all, mapped, slices, max, total, replayKey } = useMemo(() => {
    const items = cities?.items ?? []
    const max = Math.max(1, ...items.map((c) => c.orders))
    const all: City[] = items.map((c, rank) => ({
      ...c,
      rank,
      t: Math.sqrt(c.orders / max),
      region: cityRegion(c.name),
    }))

    const mapped: MapCity[] = []
    for (const c of all) {
      const point = cityPoint(c.name)
      if (point) mapped.push({ ...c, x: point[0], y: point[1] })
    }

    const slices: Slice[] = [
      ...REGIONS.map((r) => ({ ...r, orders: 0, cities: 0, top: null as string | null })),
      { key: 'other' as const, label: 'Other towns', color: 'var(--viz-pending)', orders: 0, cities: 0, top: null },
    ]
    for (const c of all) {
      const slice = slices.find((s) => s.key === (c.region ?? 'other'))!
      slice.orders += c.orders
      slice.cities += 1
      slice.top ??= c.name
    }

    const total = cities?.total_orders ?? 0
    return {
      all,
      mapped,
      slices: slices.filter((s) => s.orders > 0),
      max,
      total,
      // New figures replay the map's entrance.
      replayKey: `${total}-${items.length}-${max}`,
    }
  }, [cities])

  const listed = all.filter((c) => region === null || (c.region ?? 'other') === region).slice(0, LISTED)
  const regionSlice = slices.find((s) => s.key === region)

  return (
    <InsightCard
      eyebrow='Geography'
      title='City heatmap'
      description='Where orders ship to across the Kingdom'
      icon={<MapPinned />}
      accent={ACCENT}
    >
      {loading ? (
        <div className='grid gap-6 lg:grid-cols-[1.45fr_1fr]'>
          <Skeleton className='aspect-[5/4] w-full rounded-xl' />
          <div className='space-y-3'>
            <Skeleton className='h-40 w-full rounded-xl' />
            {Array.from({ length: 5 }).map((_, i) => (
              <Skeleton key={i} className='h-8 w-full' />
            ))}
          </div>
        </div>
      ) : !all.length ? (
        <InsightEmpty>No orders with a shipping city in this period.</InsightEmpty>
      ) : (
        <div className='grid items-start gap-6 lg:grid-cols-[1.45fr_1fr]'>
          <SaudiHeatMap
            cities={mapped}
            region={region}
            activeCity={activeCity}
            onActiveCity={setActiveCity}
            total={total}
            max={max}
            replayKey={replayKey}
          />

          {/* ── Regional mix + top cities ── */}
          <motion.div
            key={replayKey}
            className='space-y-5'
            initial='hidden'
            animate='shown'
            variants={{ hidden: {}, shown: { transition: { staggerChildren: reduce ? 0 : 0.12, delayChildren: reduce ? 0 : 0.3 } } }}
          >
            <motion.div
              variants={{ hidden: { opacity: 0, y: 10 }, shown: { opacity: 1, y: 0, transition: { duration: 0.6, ease: EASE } } }}
              className='rounded-2xl border bg-background/40 p-4'
            >
              <div className='mb-3 flex items-center justify-between'>
                <h4 className='text-[10px] font-semibold tracking-[0.14em] text-muted-foreground uppercase'>Regional mix</h4>
                <AnimatePresence>
                  {pinnedRegion && (
                    <motion.button
                      type='button'
                      initial={{ opacity: 0, scale: 0.9 }}
                      animate={{ opacity: 1, scale: 1 }}
                      exit={{ opacity: 0, scale: 0.9 }}
                      onClick={() => setPinnedRegion(null)}
                      className='inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] text-muted-foreground hover:text-foreground'
                    >
                      Clear <X className='size-3' />
                    </motion.button>
                  )}
                </AnimatePresence>
              </div>

              <div className='grid grid-cols-[136px_1fr] items-center gap-4'>
                <div className='relative size-[136px]'>
                  <PieChart width={136} height={136}>
                    <Pie
                      data={slices}
                      dataKey='orders'
                      nameKey='label'
                      innerRadius={46}
                      outerRadius={64}
                      paddingAngle={slices.length > 1 ? 2.5 : 0}
                      cornerRadius={4}
                      startAngle={90}
                      endAngle={-270}
                      stroke='none'
                      isAnimationActive={!reduce}
                      animationBegin={300}
                      animationDuration={1100}
                      animationEasing='ease-out'
                      onMouseEnter={(_, index) => setHoverRegion(slices[index]?.key ?? null)}
                      onMouseLeave={() => setHoverRegion(null)}
                      onClick={(_, index) => {
                        const key = slices[index]?.key ?? null
                        setPinnedRegion((current) => (current === key ? null : key))
                      }}
                      className='cursor-pointer'
                    >
                      {slices.map((s) => (
                        <Cell
                          key={s.key}
                          fill={s.color}
                          opacity={region === null || region === s.key ? 1 : 0.25}
                          className='outline-none transition-opacity duration-100'
                        />
                      ))}
                    </Pie>
                  </PieChart>
                  <div className='pointer-events-none absolute inset-0 grid place-items-center text-center'>
                    <AnimatePresence mode='wait'>
                      <motion.div
                        key={regionSlice?.key ?? 'all'}
                        initial={{ opacity: 0, y: 4 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -4 }}
                        transition={{ duration: 0.12 }}
                      >
                        <div className='text-xl leading-none font-bold tracking-tight tabular-nums'>
                          {regionSlice ? (
                            percent(regionSlice.orders, total)
                          ) : (
                            <CountUp value={total} format={(v) => compactNumber(Math.round(v))} />
                          )}
                        </div>
                        <div className='mt-1 text-[10px] text-muted-foreground'>
                          {regionSlice ? regionSlice.label : 'orders'}
                        </div>
                      </motion.div>
                    </AnimatePresence>
                  </div>
                </div>

                <ul className='space-y-0.5'>
                  {slices.map((s, i) => (
                    <motion.li
                      key={s.key}
                      initial={{ opacity: 0, x: 8 }}
                      animate={{ opacity: 1, x: 0 }}
                      transition={{ duration: 0.45, delay: reduce ? 0 : 0.5 + i * 0.06, ease: EASE }}
                    >
                      <button
                        type='button'
                        onMouseEnter={() => setHoverRegion(s.key)}
                        onMouseLeave={() => setHoverRegion(null)}
                        onClick={() => setPinnedRegion((current) => (current === s.key ? null : s.key))}
                        className={cn(
                          'grid w-full grid-cols-[auto_1fr_auto] items-center gap-x-2 rounded-md px-1.5 py-1 text-left transition-all duration-100',
                          region === s.key ? 'bg-muted' : 'hover:bg-muted/60',
                          region !== null && region !== s.key && 'opacity-50'
                        )}
                      >
                        <span className='size-2 rounded-[3px]' style={{ background: s.color }} />
                        <span className='min-w-0'>
                          <span className='block truncate text-xs font-medium'>{s.label}</span>
                          <span className='block truncate text-[10px] text-muted-foreground'>
                            {s.cities} {s.cities === 1 ? 'city' : 'cities'}
                            {s.key !== 'other' && s.top && ` · ${s.top}`}
                          </span>
                        </span>
                        <span className='text-xs font-semibold tabular-nums'>{percent(s.orders, total)}</span>
                      </button>
                    </motion.li>
                  ))}
                </ul>
              </div>
            </motion.div>

            <motion.div
              variants={{ hidden: { opacity: 0, y: 10 }, shown: { opacity: 1, y: 0, transition: { duration: 0.6, ease: EASE } } }}
            >
              <div className='mb-2 flex items-baseline justify-between px-2'>
                <h4 className='text-[10px] font-semibold tracking-[0.14em] text-muted-foreground uppercase'>
                  Top cities
                  <AnimatePresence mode='wait'>
                    {regionSlice && (
                      <motion.span
                        key={regionSlice.key}
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className='tracking-normal normal-case'
                      >
                        {' '}· {regionSlice.label}
                      </motion.span>
                    )}
                  </AnimatePresence>
                </h4>
                <span className='flex gap-x-3 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase'>
                  <span className='w-16'>Delivered</span>
                  <span className='w-14 text-right'>Orders</span>
                </span>
              </div>

              <motion.ol layout className='space-y-0.5'>
                <AnimatePresence mode='popLayout' initial={false}>
                  {listed.map((city, i) => {
                    const slice = slices.find((s) => s.key === (city.region ?? 'other'))
                    const width = Math.max(3, (city.orders / max) * 100)
                    return (
                      <motion.li
                        layout
                        key={city.name}
                        initial={{ opacity: 0, x: 10 }}
                        animate={{ opacity: 1, x: 0 }}
                        exit={{ opacity: 0, x: -10, transition: { duration: 0.15 } }}
                        transition={{ duration: 0.45, delay: reduce ? 0 : i * 0.05, ease: EASE }}
                        onMouseEnter={() => setActiveCity(city.name)}
                        onMouseLeave={() => setActiveCity(null)}
                        className='relative isolate grid grid-cols-[1.75rem_1fr_auto_auto] items-center gap-x-3 overflow-hidden rounded-lg px-2 py-2'
                      >
                        {/* The row is the bar: a soft wash, as wide as the city's orders against the leader's. */}
                        <motion.div
                          aria-hidden
                          className='absolute inset-y-0 left-0 -z-10 rounded-lg'
                          style={{ background: ROW_FILL }}
                          initial={{ width: reduce ? `${width}%` : '0%' }}
                          animate={{ width: `${width}%`, opacity: activeCity === null || activeCity === city.name ? 1 : 0.45 }}
                          transition={{
                            width: { duration: 1.1, delay: reduce ? 0 : 0.4 + i * 0.06, ease: EASE },
                            opacity: { duration: 0.1 },
                          }}
                        />
                        <div
                          aria-hidden
                          className={cn(
                            'absolute inset-0 -z-20 rounded-lg transition-colors duration-100',
                            activeCity === city.name ? 'bg-muted' : 'bg-transparent'
                          )}
                        />

                        <span
                          className={cn(
                            'grid size-6 place-items-center rounded-full text-[10px] font-semibold tabular-nums',
                            i === 0
                              ? 'bg-card text-foreground ring-1 ring-[color-mix(in_oklab,var(--viz-heat-hi)_55%,transparent)]'
                              : 'bg-background/70 text-muted-foreground'
                          )}
                        >
                          {i + 1}
                        </span>

                        <div className='flex min-w-0 items-center gap-2'>
                          <span className='size-1.5 shrink-0 rounded-full' style={{ background: slice?.color }} />
                          <span className='truncate text-[13px] font-medium tracking-tight text-foreground'>{city.name}</span>
                        </div>

                        <DeliveredRing delivered={city.delivered} orders={city.orders} delay={reduce ? 0 : 0.6 + i * 0.06} />

                        <div className='w-14 text-right'>
                          <div className='text-[13px] leading-tight font-semibold tabular-nums'>
                            <CountUp value={city.orders} />
                          </div>
                          <div className='text-[10px] text-muted-foreground tabular-nums'>{percent(city.orders, total)}</div>
                        </div>
                      </motion.li>
                    )
                  })}
                </AnimatePresence>
              </motion.ol>

              <p className='mt-2 px-2 text-[11px] text-muted-foreground'>
                {(cities?.city_count ?? 0).toLocaleString()} cities in all · hover a region to light it on the map, click to pin it
              </p>
            </motion.div>
          </motion.div>
        </div>
      )}
    </InsightCard>
  )
}

/** A city's delivered share as a small ring that fills in, with the figure beside it. */
function DeliveredRing({ delivered, orders, delay }: { delivered: number; orders: number; delay: number }) {
  const reduce = useReducedMotion() ?? false
  const share = orders ? delivered / orders : 0
  const r = 7
  const circumference = 2 * Math.PI * r

  return (
    <div className='flex w-16 items-center gap-1.5' title={`${delivered.toLocaleString()} of ${orders.toLocaleString()} delivered`}>
      <svg width={18} height={18} viewBox='0 0 18 18' className='-rotate-90'>
        <circle cx={9} cy={9} r={r} fill='none' className='stroke-muted' strokeWidth={2.5} />
        <motion.circle
          cx={9}
          cy={9}
          r={r}
          fill='none'
          stroke='var(--viz-good)'
          strokeWidth={2.5}
          strokeLinecap='round'
          strokeDasharray={circumference}
          initial={{ strokeDashoffset: reduce ? circumference * (1 - share) : circumference }}
          animate={{ strokeDashoffset: circumference * (1 - share) }}
          transition={{ duration: 1, delay, ease: EASE }}
        />
      </svg>
      <span className='w-8 text-[11px] font-medium text-muted-foreground tabular-nums'>{percent(delivered, orders)}</span>
    </div>
  )
}

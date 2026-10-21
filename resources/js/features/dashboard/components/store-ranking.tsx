import { useId, useMemo, useState } from 'react'
import { router } from '@inertiajs/react'
import { format, startOfMonth, subDays, subMonths } from 'date-fns'
import { motion, useReducedMotion } from 'framer-motion'
import { Crown, Store } from 'lucide-react'
import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { cn } from '@/lib/utils'
import { compactNumber } from '@/components/stat-card'
import { Skeleton } from '@/components/ui/skeleton'
import type { InsightRange, StoreInsight } from '@/hooks/useDashboardInsights'
import { CountUp, InsightCard, InsightEmpty, formatSar, percent } from './insight-card'

const ACCENT = 'var(--viz-rank)'
const EASE = [0.16, 1, 0.3, 1] as const

/** The top five each get a colour, shared by the trend line and the ranking bar. */
const COLORS = ['var(--viz-s1)', 'var(--viz-s2)', 'var(--viz-s3)', 'var(--viz-s4)', 'var(--viz-s5)']
const TRENDED = COLORS.length
const colorOf = (rank: number) => COLORS[rank] ?? 'var(--viz-pending)'

/** Bucket labels matching how the server cut the trend: a short tick and a full label. */
function buckets(range: InsightRange, count: number) {
  const today = new Date()
  return Array.from({ length: count }, (_, i) => {
    const back = count - 1 - i
    if (range === 0) {
      const month = subMonths(startOfMonth(today), back)
      return { tick: format(month, 'MMM'), label: format(month, 'MMMM yyyy') }
    }
    if (range > 31) {
      const start = subDays(today, back * 7 + 6)
      return { tick: format(start, 'd MMM'), label: `Week of ${format(start, 'd MMM')}` }
    }
    const day = subDays(today, back)
    return { tick: format(day, range <= 7 ? 'EEE' : 'd MMM'), label: format(day, 'EEEE d MMM') }
  })
}

function initials(name: string) {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase())
    .join('')
}

export function StoreRanking({
  stores,
  loading,
  range,
  storeHref,
}: {
  stores: StoreInsight[] | null
  loading: boolean
  range: InsightRange
  /** Where a store links to, or undefined when the person cannot open it. */
  storeHref?: (id: number) => string
}) {
  const [active, setActive] = useState<number | null>(null)
  const list = stores ?? []
  const byRevenue = list.some((s) => s.revenue !== null)
  // New figures replay the entrance.
  const replayKey = `${range}-${list.map((s) => `${s.client_id}:${s.orders}`).join('|')}`

  return (
    <InsightCard
      eyebrow='Leaderboard'
      title='Best stores'
      description={
        byRevenue
          ? 'Order trend of the top five, and the leaders ranked by order value'
          : 'Order trend of the top five, and the leaders ranked by orders'
      }
      icon={<Store />}
      accent={ACCENT}
    >
      {loading ? (
        <div className='grid gap-6 lg:grid-cols-[1.3fr_1fr]'>
          <Skeleton className='h-[280px] w-full rounded-xl' />
          <div className='space-y-3'>
            {Array.from({ length: 6 }).map((_, i) => (
              <Skeleton key={i} className='h-9 w-full rounded-lg' />
            ))}
          </div>
        </div>
      ) : !list.length ? (
        <InsightEmpty>No store orders in this period.</InsightEmpty>
      ) : (
        <div key={replayKey} className='grid items-start gap-6 lg:grid-cols-[1.3fr_1fr]'>
          <TrendChart stores={list} range={range} active={active} onActive={setActive} />
          <Ranking stores={list} byRevenue={byRevenue} active={active} onActive={setActive} storeHref={storeHref} />
        </div>
      )}
    </InsightCard>
  )
}

/** Orders over the period for the top five, as smooth overlapping areas. */
function TrendChart({
  stores,
  range,
  active,
  onActive,
}: {
  stores: StoreInsight[]
  range: InsightRange
  active: number | null
  onActive: (id: number | null) => void
}) {
  const uid = useId().replace(/:/g, '')
  const reduce = useReducedMotion() ?? false
  const top = useMemo(() => stores.slice(0, TRENDED), [stores])

  const data = useMemo(() => {
    const count = Math.max(0, ...top.map((s) => s.trend.length))
    return buckets(range, count).map((b, i) => {
      const row: Record<string, string | number> = { tick: b.tick, label: b.label }
      for (const s of top) row[`s${s.client_id}`] = s.trend[i] ?? 0
      return row
    })
  }, [top, range])

  const empty = data.every((row) => top.every((s) => !row[`s${s.client_id}`]))

  return (
    <div>
      <h4 className='mb-3 text-[10px] font-semibold tracking-[0.14em] text-muted-foreground uppercase'>
        Order trend · top five
      </h4>
      {empty ? (
        <InsightEmpty>No orders to chart in this period.</InsightEmpty>
      ) : (
        <div className='h-[280px]' onMouseLeave={() => onActive(null)}>
          <ResponsiveContainer width='100%' height='100%'>
            <AreaChart data={data} margin={{ top: 6, right: 8, bottom: 0, left: -8 }}>
              <defs>
                {top.map((s, i) => (
                  <linearGradient key={s.client_id} id={`area-${uid}-${s.client_id}`} x1='0' y1='0' x2='0' y2='1'>
                    <stop offset='0%' stopColor={colorOf(i)} stopOpacity={0.22} />
                    <stop offset='100%' stopColor={colorOf(i)} stopOpacity={0} />
                  </linearGradient>
                ))}
              </defs>
              <CartesianGrid vertical={false} stroke='var(--border)' strokeDasharray='3 5' />
              <XAxis
                dataKey='tick'
                axisLine={false}
                tickLine={false}
                minTickGap={18}
                tick={{ fontSize: 10, fill: 'var(--muted-foreground)' }}
                dy={6}
              />
              <YAxis
                width={40}
                axisLine={false}
                tickLine={false}
                allowDecimals={false}
                tickCount={5}
                tick={{ fontSize: 10, fill: 'var(--muted-foreground)' }}
                tickFormatter={(v: number) => compactNumber(v)}
              />
              <Tooltip
                cursor={{ stroke: 'var(--muted-foreground)', strokeWidth: 1, strokeDasharray: '3 3' }}
                wrapperStyle={{ outline: 'none', zIndex: 20 }}
                animationDuration={100}
                content={<TrendTooltip stores={top} />}
              />
              {/* Drawn last to first, so the leader sits on top. */}
              {[...top].reverse().map((s) => {
                const i = top.indexOf(s)
                const dim = active !== null && active !== s.client_id
                return (
                  <Area
                    key={s.client_id}
                    type='monotone'
                    dataKey={`s${s.client_id}`}
                    name={s.name}
                    stroke={colorOf(i)}
                    strokeWidth={active === s.client_id ? 2.75 : 2}
                    strokeOpacity={dim ? 0.18 : 1}
                    fill={`url(#area-${uid}-${s.client_id})`}
                    fillOpacity={dim ? 0.15 : 1}
                    dot={false}
                    activeDot={dim ? false : { r: 4, strokeWidth: 2, stroke: 'var(--card)', fill: colorOf(i) }}
                    isAnimationActive={!reduce}
                    animationBegin={150 + i * 120}
                    animationDuration={1100}
                    animationEasing='ease-out'
                    onMouseEnter={() => onActive(s.client_id)}
                  />
                )
              })}
            </AreaChart>
          </ResponsiveContainer>
        </div>
      )}
    </div>
  )
}

function TrendTooltip({
  active,
  payload,
  stores,
}: {
  active?: boolean
  payload?: Array<{ dataKey?: string | number; value?: number; payload?: { label?: string } }>
  stores: StoreInsight[]
}) {
  if (!active || !payload?.length) return null
  const rows = stores
    .map((s, i) => ({
      store: s,
      color: colorOf(i),
      value: Number(payload.find((p) => p.dataKey === `s${s.client_id}`)?.value ?? 0),
    }))
    .sort((a, b) => b.value - a.value)

  return (
    <div className='w-56 rounded-xl border bg-popover/95 p-3 text-xs shadow-lg backdrop-blur'>
      <div className='mb-2 font-semibold'>{payload[0].payload?.label}</div>
      <ul className='space-y-1'>
        {rows.map((r) => (
          <li key={r.store.client_id} className='grid grid-cols-[auto_1fr_auto] items-center gap-x-2'>
            <span className='size-2 rounded-full' style={{ background: r.color }} />
            <span className='truncate text-muted-foreground'>{r.store.name}</span>
            <span className='font-semibold tabular-nums'>{r.value.toLocaleString()}</span>
          </li>
        ))}
      </ul>
    </div>
  )
}

/** Every leader as a horizontal bar against the first, names aligned on the left. */
function Ranking({
  stores,
  byRevenue,
  active,
  onActive,
  storeHref,
}: {
  stores: StoreInsight[]
  byRevenue: boolean
  active: number | null
  onActive: (id: number | null) => void
  storeHref?: (id: number) => string
}) {
  const reduce = useReducedMotion() ?? false
  const valueOf = (s: StoreInsight) => (byRevenue ? (s.revenue ?? 0) : s.orders)
  const lead = Math.max(1, ...stores.map(valueOf))

  return (
    <div>
      <div className='mb-3 flex items-baseline justify-between'>
        <h4 className='text-[10px] font-semibold tracking-[0.14em] text-muted-foreground uppercase'>
          {byRevenue ? 'Order value' : 'Orders'}
        </h4>
        <span className='text-[10px] text-muted-foreground'>{byRevenue ? 'SAR' : 'count'}</span>
      </div>

      <ol className='space-y-1' onMouseLeave={() => onActive(null)}>
        {stores.map((s, i) => {
          const value = valueOf(s)
          const width = Math.max(2, (value / lead) * 100)
          const href = storeHref?.(s.client_id)
          const isActive = active === s.client_id
          const delay = reduce ? 0 : 0.25 + i * 0.06

          return (
            <motion.li
              key={s.client_id}
              initial={{ opacity: 0, y: 6 }}
              animate={{ opacity: active !== null && !isActive ? 0.45 : 1, y: 0 }}
              transition={{ opacity: { duration: 0.12 }, y: { duration: 0.45, delay, ease: EASE } }}
              onMouseEnter={() => onActive(s.client_id)}
              onClick={href ? () => router.visit(href) : undefined}
              className={cn(
                'grid grid-cols-[1.25rem_1.75rem_minmax(0,8.5rem)_1fr_auto] items-center gap-x-2.5 rounded-lg px-1.5 py-1.5 transition-colors duration-100',
                isActive && 'bg-muted/70',
                href && 'cursor-pointer'
              )}
            >
              <span className='text-center text-[11px] font-semibold text-muted-foreground tabular-nums'>
                {i === 0 ? <Crown className='mx-auto size-3.5 text-viz-gold' strokeWidth={2.5} /> : i + 1}
              </span>

              <span className='size-7 overflow-hidden rounded-md border bg-background'>
                {s.logo ? (
                  <img src={s.logo} alt='' className='size-full object-cover' loading='lazy' />
                ) : (
                  <span
                    className='grid size-full place-items-center text-[9px] font-bold text-white'
                    style={{ background: colorOf(i) }}
                  >
                    {initials(s.name)}
                  </span>
                )}
              </span>

              <span className='min-w-0'>
                <span className='block truncate text-[12.5px] leading-tight font-medium'>{s.name}</span>
                <span className='block truncate text-[10px] text-muted-foreground tabular-nums'>
                  {byRevenue && `${s.orders.toLocaleString()} orders · `}
                  {percent(s.delivered, s.orders)} delivered
                </span>
              </span>

              <span className='h-2.5 overflow-hidden rounded-full bg-muted'>
                <motion.span
                  className='block h-full rounded-full'
                  style={{
                    background: `linear-gradient(90deg, color-mix(in oklab, ${colorOf(i)} 70%, transparent), ${colorOf(i)})`,
                  }}
                  initial={{ width: reduce ? `${width}%` : '0%' }}
                  animate={{ width: `${width}%` }}
                  transition={{ duration: 1, delay: delay + 0.1, ease: EASE }}
                />
              </span>

              <span className='w-16 text-right text-[12.5px] font-semibold tabular-nums'>
                <CountUp
                  value={value}
                  format={(v) => (byRevenue ? formatSar(v).replace('SAR ', '') : Math.round(v).toLocaleString())}
                />
              </span>
            </motion.li>
          )
        })}
      </ol>
    </div>
  )
}

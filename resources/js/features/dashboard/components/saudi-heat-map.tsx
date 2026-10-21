import { useEffect, useId, useMemo, useState } from 'react'
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion'
import { cn } from '@/lib/utils'
import { COUNTRY_PATH, COUNTRY_POINTS, MAP_HEIGHT, MAP_WIDTH, PARALLELS, REGIONS, type Region } from '../data/saudi-map'
import { labelWidth, landOf, placeLabels, separate } from '../lib/map-layout'
import { CountUp, formatSar, percent } from './insight-card'

/** Cities named on the map itself; the rest are named on hover. */
const LABELLED = 5
const LAND = landOf(COUNTRY_POINTS)
const EASE = [0.16, 1, 0.3, 1] as const

/** One hue, light to dark; `t` is 0..1 on a square-root scale. */
export function heat(t: number) {
  return `color-mix(in oklab, var(--viz-heat-hi) ${Math.round(t * 100)}%, var(--viz-heat-lo))`
}

export type MapCity = {
  name: string
  orders: number
  delivered: number
  revenue: number | null
  rank: number
  t: number
  region: Region | null
  /** True position on the canvas. */
  x: number
  y: number
}

/**
 * The map: Saudi Arabia drawn in, each city a dot sized and shaded by its
 * orders. Dots that would sit on each other are eased apart, labels find a
 * clear spot of their own, and everything enters as a ripple spreading out
 * from the busiest city. `replayKey` replays the entrance on new data.
 */
export function SaudiHeatMap({
  cities,
  region,
  activeCity,
  onActiveCity,
  total,
  max,
  replayKey,
}: {
  /** Busiest first. */
  cities: MapCity[]
  region: Region | 'other' | null
  activeCity: string | null
  onActiveCity: (name: string | null) => void
  total: number
  max: number
  replayKey: string
}) {
  const uid = useId().replace(/:/g, '')
  const reduce = useReducedMotion() ?? false

  // Entrance delays apply once; after that every change is a quick fade.
  const [entered, setEntered] = useState(reduce)
  useEffect(() => {
    if (reduce) return
    setEntered(false)
    const timer = window.setTimeout(() => setEntered(true), 2600)
    return () => window.clearTimeout(timer)
  }, [replayKey, reduce])

  const byName = useMemo(() => new Map(cities.map((c) => [c.name, c])), [cities])

  const placed = useMemo(
    () => separate(cities.map((c) => ({ id: c.name, x: c.x, y: c.y, r: 3 + 15 * c.t })), { land: LAND }),
    [cities]
  )

  const inRegion = (name: string) => {
    const c = byName.get(name)
    return region === null || (c?.region ?? 'other') === region
  }

  const labels = useMemo(() => {
    const targets = placed
      .filter((p) => region === null || (byName.get(p.id)?.region ?? 'other') === region)
      .slice(0, LABELLED)
      .map((p) => ({ ...p, width: labelWidth(p.id, byName.get(p.id)!.orders.toLocaleString()) }))
    return placeLabels(targets, placed, { width: MAP_WIDTH, height: MAP_HEIGHT })
  }, [placed, region, byName])

  const origin = placed[0]
  const delayOf = (px: number, py: number) =>
    reduce || !origin ? 0 : 0.55 + (Math.hypot(px - origin.px, py - origin.py) / MAP_WIDTH) * 1.2

  const hovered = placed.find((p) => p.id === activeCity) ?? null
  const hoveredCity = hovered ? byName.get(hovered.id)! : null
  const below = hovered ? hovered.py < MAP_HEIGHT * 0.32 : false

  return (
    <div className='relative'>
      <svg
        key={replayKey}
        viewBox={`0 0 ${MAP_WIDTH} ${MAP_HEIGHT}`}
        className='h-auto w-full select-none'
        role='img'
        aria-label={cities[0] ? `Orders by city. Most orders: ${cities[0].name}, ${cities[0].orders.toLocaleString()}.` : 'Orders by city'}
      >
        <defs>
          <pattern id={`dots-${uid}`} width='9' height='9' patternUnits='userSpaceOnUse'>
            <circle cx='1.5' cy='1.5' r='1.1' className='fill-foreground/[0.13]' />
          </pattern>
          <clipPath id={`country-${uid}`}>
            <path d={COUNTRY_PATH} />
          </clipPath>
          <radialGradient id={`glow-${uid}`}>
            <stop offset='0%' style={{ stopColor: 'var(--viz-heat-hi)', stopOpacity: 0.55 }} />
            <stop offset='55%' style={{ stopColor: 'var(--viz-heat-lo)', stopOpacity: 0.18 }} />
            <stop offset='100%' style={{ stopColor: 'var(--viz-heat-lo)', stopOpacity: 0 }} />
          </radialGradient>
          <linearGradient id={`land-${uid}`} x1='0' y1='0' x2='1' y2='1'>
            <stop offset='0%' className='[stop-color:var(--muted)]' />
            <stop offset='100%' className='[stop-color:var(--background)]' />
          </linearGradient>
          {/* A light sweep that crosses the land once, as the outline closes. */}
          <linearGradient id={`sweep-${uid}`} x1='0' y1='0' x2='1' y2='0'>
            <stop offset='0%' stopColor='var(--viz-heat-hi)' stopOpacity={0} />
            <stop offset='50%' stopColor='var(--viz-heat-hi)' stopOpacity={0.14} />
            <stop offset='100%' stopColor='var(--viz-heat-hi)' stopOpacity={0} />
          </linearGradient>
        </defs>

        {/* Parallels: a cartographer's grid, barely there. */}
        <motion.g initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ duration: reduce ? 0 : 0.8 }}>
          {PARALLELS.map((p) => (
            <g key={p.lat}>
              <line x1={0} x2={MAP_WIDTH} y1={p.y} y2={p.y} className='stroke-border' strokeDasharray='2 6' />
              <text x={4} y={p.y - 4} className='fill-muted-foreground/70 text-[10px] tabular-nums'>
                {p.lat}°N
              </text>
            </g>
          ))}
        </motion.g>

        <motion.g
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ duration: reduce ? 0 : 1, delay: reduce ? 0 : 0.2, ease: EASE }}
        >
          <path d={COUNTRY_PATH} fill={`url(#land-${uid})`} />
          <rect width={MAP_WIDTH} height={MAP_HEIGHT} fill={`url(#dots-${uid})`} clipPath={`url(#country-${uid})`} />
        </motion.g>

        {!reduce && (
          <g clipPath={`url(#country-${uid})`} className='pointer-events-none'>
            <motion.rect
              y={0}
              width={MAP_WIDTH * 0.35}
              height={MAP_HEIGHT}
              fill={`url(#sweep-${uid})`}
              initial={{ x: -MAP_WIDTH * 0.35 }}
              animate={{ x: MAP_WIDTH }}
              transition={{ duration: 1.8, delay: 0.3, ease: [0.45, 0, 0.2, 1] }}
            />
          </g>
        )}

        {/* The border draws itself. */}
        <motion.path
          d={COUNTRY_PATH}
          fill='none'
          className='stroke-foreground/30'
          strokeWidth={1.25}
          strokeLinejoin='round'
          initial={{ pathLength: reduce ? 1 : 0, opacity: reduce ? 1 : 0 }}
          animate={{ pathLength: 1, opacity: 1 }}
          transition={{ duration: reduce ? 0 : 1.6, ease: [0.65, 0, 0.35, 1] }}
        />

        {/* Heat: a soft glow under every city, scaled with its volume. */}
        <g style={{ mixBlendMode: 'multiply' }} className='dark:[mix-blend-mode:screen]'>
          {placed.map((p) => {
            const c = byName.get(p.id)!
            return (
              <motion.circle
                key={`glow-${p.id}`}
                cx={p.px}
                cy={p.py}
                r={p.r * 3.2}
                fill={`url(#glow-${uid})`}
                initial={{ opacity: 0 }}
                animate={{ opacity: inRegion(p.id) ? 0.35 + 0.65 * c.t : 0 }}
                transition={entered ? { duration: 0.12 } : { duration: 1, delay: delayOf(p.px, p.py) + 0.2 }}
              />
            )
          })}
        </g>

        {/* Where a dot was eased aside: a hairline back to its true location. */}
        {placed.map((p) => {
          const shift = Math.hypot(p.px - p.x, p.py - p.y)
          if (shift < 4) return null
          return (
            <motion.g
              key={`anchor-${p.id}`}
              className='pointer-events-none'
              initial={{ opacity: 0 }}
              animate={{ opacity: inRegion(p.id) ? 1 : 0.15 }}
              transition={entered ? { duration: 0.12 } : { duration: 0.5, delay: delayOf(p.px, p.py) + 0.35 }}
            >
              <line x1={p.x} y1={p.y} x2={p.px} y2={p.py} className='stroke-foreground/35' strokeWidth={0.75} />
              <circle cx={p.x} cy={p.y} r={1.5} className='fill-foreground/50' />
            </motion.g>
          )
        })}

        {/* The leading city breathes: two rings, half a beat apart. */}
        {origin && entered && inRegion(origin.id) && (
          <g className='pointer-events-none'>
            {[0, 1.2].map((begin) => (
              <circle key={begin} cx={origin.px} cy={origin.py} r={origin.r} fill='none' stroke={heat(1)} strokeWidth={1.5} opacity={0}>
                <animate attributeName='r' from={origin.r} to={origin.r * 2.6} dur='2.4s' begin={`${begin}s`} repeatCount='indefinite' />
                <animate attributeName='opacity' values='0.7;0' dur='2.4s' begin={`${begin}s`} repeatCount='indefinite' />
              </circle>
            ))}
          </g>
        )}

        {placed.map((p) => {
          const c = byName.get(p.id)!
          const isActive = activeCity === p.id
          const opacity = !inRegion(p.id) ? 0.12 : activeCity !== null && !isActive ? 0.35 : 1
          return (
            <g
              key={p.id}
              transform={`translate(${p.px} ${p.py})`}
              onMouseEnter={() => onActiveCity(p.id)}
              onMouseLeave={() => onActiveCity(null)}
            >
              <motion.g
                initial={{ scale: reduce ? 1 : 0 }}
                animate={{ scale: 1 }}
                transition={{ type: 'spring', stiffness: 320, damping: 16, delay: delayOf(p.px, p.py) }}
              >
                <motion.circle
                  r={p.r}
                  fill={heat(c.t)}
                  className='stroke-card'
                  strokeWidth={2}
                  initial={false}
                  animate={{ opacity, scale: isActive ? 1.16 : 1 }}
                  transition={{ duration: 0.12, ease: EASE }}
                />
                {isActive && (
                  <motion.circle
                    r={p.r + 5}
                    fill='none'
                    className='stroke-foreground/60'
                    strokeWidth={1.25}
                    initial={{ opacity: 0, scale: 0.8 }}
                    animate={{ opacity: 1, scale: 1 }}
                    transition={{ duration: 0.1 }}
                  />
                )}
              </motion.g>
              {/* Hit target bigger than the mark. */}
              <circle r={Math.max(p.r + 6, 12)} fill='transparent' />
            </g>
          )
        })}

        {/* Labels for the leaders, each in its own clear spot. */}
        <AnimatePresence>
          {labels.map((l, i) => {
            const c = byName.get(l.id)!
            return (
              <motion.g
                key={`label-${l.id}`}
                className='pointer-events-none'
                initial={{ opacity: 0, y: 4 }}
                animate={{ opacity: activeCity !== null && activeCity !== l.id ? 0.35 : 1, y: 0 }}
                exit={{ opacity: 0, transition: { duration: 0.15 } }}
                transition={entered ? { duration: 0.12 } : { duration: 0.5, delay: 1.5 + i * 0.09, ease: EASE }}
              >
                {l.leader && (
                  <motion.line
                    x1={l.leader.x1}
                    y1={l.leader.y1}
                    x2={l.leader.x2}
                    y2={l.leader.y2}
                    className='stroke-foreground/45'
                    strokeWidth={1}
                    initial={{ pathLength: 0 }}
                    animate={{ pathLength: 1 }}
                    transition={{ duration: 0.4, delay: entered ? 0 : 1.45 + i * 0.09 }}
                  />
                )}
                <text
                  x={l.x + 2}
                  y={l.y + l.h - 4}
                  style={{ paintOrder: 'stroke' }}
                  stroke='var(--card)'
                  strokeWidth={4}
                  strokeLinejoin='round'
                >
                  <tspan className='fill-foreground text-[12px] font-semibold'>{c.name}</tspan>
                  <tspan dx={5} className='fill-muted-foreground text-[11px] font-medium tabular-nums'>
                    {c.orders.toLocaleString()}
                  </tspan>
                </text>
              </motion.g>
            )
          })}
        </AnimatePresence>
      </svg>

      <AnimatePresence>
        {hovered && hoveredCity && (
          <motion.div
            key={hovered.id}
            initial={{ opacity: 0, scale: 0.96, y: below ? -4 : 4 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.96, transition: { duration: 0.1 } }}
            transition={{ duration: 0.12, ease: EASE }}
            className='pointer-events-none absolute z-10 w-48'
            style={{
              left: `${Math.min(80, Math.max(20, (hovered.px / MAP_WIDTH) * 100))}%`,
              top: `${((below ? hovered.py + hovered.r : hovered.py - hovered.r) / MAP_HEIGHT) * 100}%`,
            }}
          >
            <div
              className={cn(
                'rounded-xl border bg-popover/95 p-3 text-xs shadow-lg backdrop-blur',
                // Northern cities open the tooltip downwards, inside the card.
                below ? 'translate-y-[14px]' : '-translate-y-[calc(100%+14px)]',
                '-translate-x-1/2'
              )}
            >
              <CityTooltip city={hoveredCity} total={total} />
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      <div className='mt-3 flex items-center gap-3 text-[11px] text-muted-foreground'>
        <span>Fewer</span>
        <motion.span
          className='h-1.5 w-32 origin-left rounded-full'
          style={{ background: `linear-gradient(90deg, ${heat(0)}, ${heat(0.5)}, ${heat(1)})` }}
          initial={{ scaleX: reduce ? 1 : 0 }}
          animate={{ scaleX: 1 }}
          transition={{ duration: 1, delay: reduce ? 0 : 0.6, ease: EASE }}
        />
        <span>More orders</span>
        <span className='ms-auto tabular-nums'>
          Peak <CountUp value={max} />
        </span>
      </div>
    </div>
  )
}

function CityTooltip({ city, total }: { city: MapCity; total: number }) {
  const region = REGIONS.find((r) => r.key === city.region)
  return (
    <>
      <div className='mb-2 flex items-center gap-2'>
        <span className='size-2.5 rounded-full' style={{ background: heat(city.t) }} />
        <span className='font-semibold'>{city.name}</span>
        <span className='ms-auto text-muted-foreground'>#{city.rank + 1}</span>
      </div>
      <dl className='grid grid-cols-[1fr_auto] gap-x-3 gap-y-1'>
        {region && (
          <>
            <dt className='text-muted-foreground'>Region</dt>
            <dd className='text-right font-medium'>{region.label}</dd>
          </>
        )}
        <dt className='text-muted-foreground'>Orders</dt>
        <dd className='text-right font-medium tabular-nums'>{city.orders.toLocaleString()}</dd>
        <dt className='text-muted-foreground'>Share</dt>
        <dd className='text-right font-medium tabular-nums'>{percent(city.orders, total)}</dd>
        <dt className='text-muted-foreground'>Delivered</dt>
        <dd className='text-right font-medium tabular-nums'>{percent(city.delivered, city.orders)}</dd>
        {city.revenue !== null && (
          <>
            <dt className='text-muted-foreground'>Revenue</dt>
            <dd className='text-right font-medium tabular-nums'>{formatSar(city.revenue)}</dd>
          </>
        )}
      </dl>
    </>
  )
}

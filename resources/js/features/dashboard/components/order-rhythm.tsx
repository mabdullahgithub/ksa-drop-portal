import { useEffect, useId, useMemo, useState } from 'react'
import { AnimatePresence, animate, motion, useReducedMotion } from 'framer-motion'
import { Clock, Moon, Sun } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Skeleton } from '@/components/ui/skeleton'
import type { OrderRhythm as Rhythm } from '@/hooks/useDashboardInsights'
import { CountUp, InsightCard, InsightEmpty, percent } from './insight-card'

const ACCENT = 'var(--viz-c1)'
const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
const EASE = [0.16, 1, 0.3, 1] as const

/** Chart geometry: a clock face with a hole in the middle for the readout. */
const SIZE = 320
const C = SIZE / 2
const R0 = 50 // zero orders
const R1 = 122 // the busiest hour or day

type View = 'day' | 'week'
type Point = { key: number; tick: string; label: string; orders: number }

function hourLabel(h: number) {
  return `${h % 12 === 0 ? 12 : h % 12} ${h < 12 ? 'AM' : 'PM'}`
}

function points(rhythm: Rhythm, view: View): Point[] {
  return view === 'day'
    ? rhythm.hours.map((orders, h) => ({
        key: h,
        tick: h % 3 === 0 ? hourLabel(h) : '',
        label: `${hourLabel(h)} – ${hourLabel((h + 1) % 24)}`,
        orders,
      }))
    : rhythm.weekdays.map((orders, d) => ({ key: d, tick: DAYS[d].slice(0, 3), label: DAYS[d], orders }))
}

/** Clockwise from the top: midnight (or Sunday) at twelve o'clock. */
function angleOf(i: number, n: number) {
  return -Math.PI / 2 + (i / n) * Math.PI * 2
}

function polar(r: number, angle: number): [number, number] {
  return [C + r * Math.cos(angle), C + r * Math.sin(angle)]
}

/** A closed Catmull-Rom curve through the points, as cubic Béziers. */
function smoothClosed(pts: Array<[number, number]>) {
  const n = pts.length
  if (n < 3) return ''
  let d = `M${pts[0][0].toFixed(2)},${pts[0][1].toFixed(2)}`
  for (let i = 0; i < n; i++) {
    const p0 = pts[(i - 1 + n) % n]
    const p1 = pts[i]
    const p2 = pts[(i + 1) % n]
    const p3 = pts[(i + 2) % n]
    const k = 1 / 6
    d += `C${(p1[0] + (p2[0] - p0[0]) * k).toFixed(2)},${(p1[1] + (p2[1] - p0[1]) * k).toFixed(2)} ${(p2[0] - (p3[0] - p1[0]) * k).toFixed(2)},${(p2[1] - (p3[1] - p1[1]) * k).toFixed(2)} ${p2[0].toFixed(2)},${p2[1].toFixed(2)}`
  }
  return d + 'Z'
}

/** A ring segment between two angles, for the night shading and the hover wedges. */
function sector(r0: number, r1: number, a0: number, a1: number) {
  const large = a1 - a0 > Math.PI ? 1 : 0
  const [x0, y0] = polar(r1, a0)
  const [x1, y1] = polar(r1, a1)
  const [x2, y2] = polar(r0, a1)
  const [x3, y3] = polar(r0, a0)
  return `M${x0},${y0}A${r1},${r1} 0 ${large} 1 ${x1},${y1}L${x2},${y2}A${r0},${r0} 0 ${large} 0 ${x3},${y3}Z`
}

/** Rounds a ring's value to something a person would say: 40, 80, 120 — not 37, 74. */
function niceStep(max: number, rings: number) {
  const raw = max / rings
  const mag = 10 ** Math.floor(Math.log10(Math.max(raw, 1)))
  const step = [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= raw) ?? raw
  return Math.max(1, step)
}

export function OrderRhythm({ rhythm, loading }: { rhythm: Rhythm | null; loading: boolean }) {
  const [view, setView] = useState<View>('day')

  const { data, total, peak, quiet, figures } = useMemo(() => {
    if (!rhythm) return { data: [] as Point[], total: 0, peak: null, quiet: null, figures: [] }
    const data = points(rhythm, view)
    const total = data.reduce((sum, p) => sum + p.orders, 0)
    const peak = data.reduce((a, b) => (b.orders > a.orders ? b : a), data[0])
    const quiet = data.reduce((a, b) => (b.orders < a.orders ? b : a), data[0])

    const figures =
      view === 'day'
        ? [
            { label: 'Peak hour', value: hourLabel(peak.key), sub: `${percent(peak.orders, total)} of orders` },
            { label: 'Quietest hour', value: hourLabel(quiet.key), sub: `${percent(quiet.orders, total)} of orders` },
            {
              label: 'After 6 PM',
              value: percent(rhythm.hours.slice(18).reduce((s, n) => s + n, 0), total),
              sub: 'of orders come in the evening',
            },
          ]
        : [
            { label: 'Busiest day', value: DAYS[peak.key], sub: `${percent(peak.orders, total)} of orders` },
            { label: 'Quietest day', value: DAYS[quiet.key], sub: `${percent(quiet.orders, total)} of orders` },
            {
              label: 'Weekend',
              value: percent(rhythm.weekdays[5] + rhythm.weekdays[6], total),
              sub: 'of orders on Friday and Saturday',
            },
          ]

    return { data, total, peak, quiet, figures }
  }, [rhythm, view])

  return (
    <InsightCard
      eyebrow='Rhythm'
      title='Order rhythm'
      description='When customers place orders, in Riyadh time'
      icon={<Clock />}
      accent={ACCENT}
      aside={
        <div role='radiogroup' aria-label='View' className='inline-flex shrink-0 rounded-lg border bg-muted/60 p-0.5'>
          {(['day', 'week'] as const).map((v) => (
            <button
              key={v}
              type='button'
              role='radio'
              aria-checked={view === v}
              onClick={() => setView(v)}
              className={cn(
                'rounded-md px-2.5 py-1 text-[11px] font-medium transition-all',
                view === v ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
              )}
            >
              {v === 'day' ? '24 hours' : 'Week'}
            </button>
          ))}
        </div>
      }
    >
      {loading ? (
        <div className='grid items-center gap-4 sm:grid-cols-[1fr_168px]'>
          <Skeleton className='mx-auto aspect-square w-full max-w-72 rounded-full' />
          <div className='space-y-2'>
            {Array.from({ length: 3 }).map((_, i) => (
              <Skeleton key={i} className='h-16 w-full rounded-xl' />
            ))}
          </div>
        </div>
      ) : !total || !peak || !quiet ? (
        <InsightEmpty>No orders in this period.</InsightEmpty>
      ) : (
        <div className='grid items-center gap-4 sm:grid-cols-[1fr_168px]'>
          <RhythmClock key={`${view}-${total}`} data={data} view={view} total={total} peak={peak} />

          <dl className='grid gap-2'>
            {figures.map((f, i) => (
              <motion.div
                key={`${view}-${f.label}`}
                initial={{ opacity: 0, x: 10 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ duration: 0.45, delay: 0.3 + i * 0.08, ease: EASE }}
                className='rounded-xl border bg-background/50 px-3 py-2.5'
              >
                <dt className='flex items-center gap-1.5 text-[10px] font-medium tracking-wide text-muted-foreground uppercase'>
                  {i === 0 && <span className='size-1.5 rounded-full' style={{ background: ACCENT }} />}
                  {f.label}
                </dt>
                <dd className='text-lg leading-tight font-semibold tracking-tight tabular-nums'>{f.value}</dd>
                <dd className='text-[11px] text-muted-foreground'>{f.sub}</dd>
              </motion.div>
            ))}
          </dl>
        </div>
      )}
    </InsightCard>
  )
}

/**
 * A clock face: each hour (or day) is a direction, and the further the curve
 * reaches out, the more orders came in then. The hole in the middle reads out
 * the total, or whatever is under the pointer.
 */
function RhythmClock({ data, view, total, peak }: { data: Point[]; view: View; total: number; peak: Point }) {
  const uid = useId().replace(/:/g, '')
  const reduce = useReducedMotion() ?? false
  const [active, setActive] = useState<number | null>(null)
  // 0 → 1 as the curve grows out of the centre.
  const [grow, setGrow] = useState(reduce ? 1 : 0)

  useEffect(() => {
    if (reduce) return
    const controls = animate(0, 1, { duration: 1.3, delay: 0.15, ease: EASE, onUpdate: setGrow })
    return () => controls.stop()
  }, [reduce])

  const n = data.length
  const step = niceStep(peak.orders, 4)
  const top = Math.max(step * Math.ceil(peak.orders / step), 1)
  const radius = (v: number) => R0 + (v / top) * (R1 - R0)
  const rings = Array.from({ length: Math.round(top / step) }, (_, k) => (k + 1) * step)

  const curve = smoothClosed(data.map((p, i) => polar(R0 + (radius(p.orders) - R0) * grow, angleOf(i, n))))
  const baseline = smoothClosed(data.map((_, i) => polar(R0, angleOf(i, n))))

  const focus = active !== null ? data[active] : null
  const half = Math.PI / n

  return (
    <div className='relative mx-auto w-full max-w-[320px]'>
      <svg viewBox={`0 0 ${SIZE} ${SIZE}`} className='h-auto w-full select-none' onMouseLeave={() => setActive(null)}>
        <defs>
          <radialGradient id={`fill-${uid}`} cx={C} cy={C} r={R1} gradientUnits='userSpaceOnUse'>
            <stop offset={`${(R0 / R1) * 100}%`} stopColor='var(--viz-c1)' stopOpacity={0.05} />
            <stop offset='100%' stopColor='var(--viz-c1)' stopOpacity={0.5} />
          </radialGradient>
        </defs>

        {/* Night, 6 PM to 6 AM: the top half of the clock, gently shaded. */}
        {view === 'day' && (
          <motion.g initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ duration: 0.8 }}>
            <path d={sector(R0, R1 + 10, angleOf(18, 24), angleOf(30, 24))} className='fill-foreground/[0.035]' />
            {/* Beside the 12 AM and 12 PM labels. */}
            <foreignObject x={C + 19} y={C - R1 - 31} width={14} height={14}>
              <Moon className='size-3 text-muted-foreground' />
            </foreignObject>
            <foreignObject x={C + 19} y={C + R1 + 17} width={14} height={14}>
              <Sun className='size-3 text-muted-foreground' />
            </foreignObject>
          </motion.g>
        )}

        {/* Value rings, each with its order count. */}
        {rings.map((v, k) => (
          <motion.g
            key={v}
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.5, delay: reduce ? 0 : 0.1 + k * 0.08 }}
          >
            <circle cx={C} cy={C} r={radius(v)} fill='none' className='stroke-border' strokeDasharray={k === rings.length - 1 ? undefined : '2 4'} />
            <text
              x={C + 4}
              y={C - radius(v) + 10}
              className='fill-muted-foreground/80 text-[9px] tabular-nums'
              style={{ paintOrder: 'stroke' }}
              stroke='var(--card)'
              strokeWidth={3}
            >
              {v.toLocaleString()}
            </text>
          </motion.g>
        ))}
        <circle cx={C} cy={C} r={R0} fill='none' className='stroke-border' />

        {/* Clock ticks and labels around the rim. */}
        {data.map((p, i) => {
          const a = angleOf(i, n)
          const major = view === 'week' || p.key % 3 === 0
          const [x1, y1] = polar(R1 + 4, a)
          const [x2, y2] = polar(R1 + (major ? 10 : 7), a)
          const [lx, ly] = polar(R1 + 24, a)
          const isActive = active === i
          return (
            <g key={p.key}>
              <line x1={x1} y1={y1} x2={x2} y2={y2} className={isActive ? 'stroke-foreground' : 'stroke-muted-foreground/50'} strokeWidth={major ? 1.25 : 0.75} />
              {p.tick && (
                <text
                  x={lx}
                  y={ly}
                  textAnchor='middle'
                  dominantBaseline='central'
                  className={cn('text-[10px] font-medium transition-colors duration-100', isActive ? 'fill-foreground' : 'fill-muted-foreground')}
                >
                  {p.tick}
                </text>
              )}
            </g>
          )
        })}

        {/* The rhythm itself. */}
        <path d={curve} fill={`url(#fill-${uid})`} />
        <motion.path
          d={curve}
          fill='none'
          stroke='var(--viz-c1)'
          strokeWidth={2}
          strokeLinejoin='round'
          initial={{ pathLength: reduce ? 1 : 0 }}
          animate={{ pathLength: 1 }}
          transition={{ duration: 1.4, delay: 0.1, ease: [0.65, 0, 0.35, 1] }}
        />
        {grow < 0.02 && <path d={baseline} fill='none' className='stroke-border' />}

        {/* The hovered hour: a spoke out to its value. */}
        {focus && active !== null && (
          <g className='pointer-events-none'>
            {(() => {
              const a = angleOf(active, n)
              const [x0, y0] = polar(R0, a)
              const [x1, y1] = polar(radius(focus.orders), a)
              return (
                <>
                  <line x1={x0} y1={y0} x2={x1} y2={y1} className='stroke-foreground/50' strokeDasharray='2 3' />
                  <circle cx={x1} cy={y1} r={4.5} fill='var(--viz-c1)' stroke='var(--card)' strokeWidth={2} />
                </>
              )
            })()}
          </g>
        )}

        {/* The peak, marked and breathing. */}
        {grow > 0.98 &&
          (() => {
            const i = data.indexOf(peak)
            const [x, y] = polar(radius(peak.orders), angleOf(i, n))
            return (
              <g className='pointer-events-none'>
                <circle cx={x} cy={y} r={4} fill='none' stroke='var(--viz-c1)' strokeWidth={1.5}>
                  <animate attributeName='r' from='4' to='13' dur='2.2s' repeatCount='indefinite' />
                  <animate attributeName='opacity' values='0.8;0' dur='2.2s' repeatCount='indefinite' />
                </circle>
                <circle cx={x} cy={y} r={4} fill='var(--viz-c1)' stroke='var(--card)' strokeWidth={2} />
              </g>
            )
          })()}

        {/* One invisible wedge per hour or day: the hover targets. */}
        {data.map((p, i) => {
          const a = angleOf(i, n)
          return (
            <path
              key={`hit-${p.key}`}
              d={sector(R0 - 6, R1 + 30, a - half, a + half)}
              fill='transparent'
              onMouseEnter={() => setActive(i)}
            />
          )
        })}
      </svg>

      {/* The readout in the middle. */}
      <div className='pointer-events-none absolute inset-0 grid place-items-center'>
        <AnimatePresence mode='wait'>
          <motion.div
            key={focus ? focus.key : 'total'}
            initial={{ opacity: 0, scale: 0.92 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.92 }}
            transition={{ duration: 0.1 }}
            className='w-[86px] text-center'
          >
            {focus ? (
              <>
                <div className='text-lg leading-none font-bold tabular-nums'>{focus.orders.toLocaleString()}</div>
                <div className='mt-1 text-[9px] leading-tight font-medium text-muted-foreground'>
                  {view === 'day' ? hourLabel(focus.key) : DAYS[focus.key]}
                </div>
                <div className='text-[9px] text-muted-foreground tabular-nums'>{percent(focus.orders, total)}</div>
              </>
            ) : (
              <>
                <div className='text-lg leading-none font-bold tabular-nums'>
                  <CountUp value={total} />
                </div>
                <div className='mt-1 text-[9px] font-medium text-muted-foreground'>orders</div>
              </>
            )}
          </motion.div>
        </AnimatePresence>
      </div>

      <p className='mt-1 text-center text-[11px] text-muted-foreground'>
        {view === 'day'
          ? 'Further out means more orders · shaded half is night, 6 PM – 6 AM'
          : 'Further out means more orders that day'}
      </p>
    </div>
  )
}

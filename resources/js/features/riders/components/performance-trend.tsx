import { format } from 'date-fns'
import { Area, CartesianGrid, ComposedChart, Line, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { useDirection } from '@/context/direction-provider'
import { METRICS, parseDay, type PerformanceMetric } from '../data/performance'

export type TrendPoint = { day: string; rider: number | null; team: number | null }

type Props = {
  points: TrendPoint[]
  metric: PerformanceMetric
  riderName: string
}

/**
 * One rider's day-by-day line against the team, for the metric the section is
 * ranked by. The rider is the series colour; the team stays a quiet gray.
 */
export function PerformanceTrend({ points, metric, riderName }: Props) {
  const { dir } = useDirection()
  const spec = METRICS[metric]
  const rtl = dir === 'rtl'
  const dots = points.length <= 31

  return (
    <figure className='space-y-2'>
      <figcaption className='flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground'>
        <LegendKey className='bg-[var(--perf-series)]' label={riderName} />
        <LegendKey className='bg-muted-foreground/60' label={spec.teamLabel} />
      </figcaption>

      <ResponsiveContainer width='100%' height={200}>
        <ComposedChart data={points} margin={{ top: 8, right: 4, bottom: 0, left: 4 }}>
          <CartesianGrid stroke='var(--border)' vertical={false} />
          <XAxis
            dataKey='day'
            reversed={rtl}
            tickFormatter={(day: string) => format(parseDay(day), 'MMM d')}
            tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
            tickLine={false}
            axisLine={{ stroke: 'var(--border)' }}
            interval='preserveStartEnd'
            minTickGap={24}
          />
          <YAxis
            orientation={rtl ? 'right' : 'left'}
            allowDecimals={metric !== 'delivered'}
            domain={metric === 'rate' ? [0, 100] : [0, 'auto']}
            tickFormatter={spec.axis}
            tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
            tickLine={false}
            axisLine={false}
            width={40}
          />
          <Tooltip
            cursor={{ stroke: 'var(--muted-foreground)', strokeWidth: 1 }}
            content={({ active, payload }) => {
              if (!active || !payload?.length) return null
              const point = payload[0].payload as TrendPoint
              return (
                <div className='min-w-40 rounded-lg border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md'>
                  <p className='mb-1.5 text-muted-foreground'>{format(parseDay(point.day), 'EEE, MMM d')}</p>
                  <TooltipRow keyClass='bg-[var(--perf-series)]' value={point.rider} label={riderName} metric={metric} />
                  <TooltipRow keyClass='bg-muted-foreground/60' value={point.team} label={spec.teamLabel} metric={metric} />
                </div>
              )
            }}
          />
          <Line
            dataKey='team'
            name={spec.teamLabel}
            type='monotone'
            stroke='var(--muted-foreground)'
            strokeOpacity={0.6}
            strokeWidth={2}
            strokeLinecap='round'
            strokeLinejoin='round'
            dot={false}
            activeDot={{ r: 4, fill: 'var(--muted-foreground)', stroke: 'var(--card)', strokeWidth: 2 }}
            isAnimationActive={false}
          />
          <Area
            dataKey='rider'
            name={riderName}
            type='monotone'
            stroke='var(--perf-series)'
            strokeWidth={2}
            strokeLinecap='round'
            strokeLinejoin='round'
            fill='var(--perf-series)'
            fillOpacity={0.1}
            dot={dots ? { r: 4, fill: 'var(--perf-series)', stroke: 'var(--card)', strokeWidth: 2 } : false}
            activeDot={{ r: 5, fill: 'var(--perf-series)', stroke: 'var(--card)', strokeWidth: 2 }}
            animationDuration={500}
          />
        </ComposedChart>
      </ResponsiveContainer>
    </figure>
  )
}

function LegendKey({ className, label }: { className: string; label: string }) {
  return (
    <span className='inline-flex min-w-0 items-center gap-1.5'>
      <span className={`h-0.5 w-3.5 shrink-0 rounded-full ${className}`} />
      <span className='truncate'>{label}</span>
    </span>
  )
}

function TooltipRow({
  keyClass,
  value,
  label,
  metric,
}: {
  keyClass: string
  value: number | null
  label: string
  metric: PerformanceMetric
}) {
  const shown = value === null ? '—' : METRICS[metric].format(metric === 'delivered' ? Math.round(value * 10) / 10 : value)
  return (
    <div className='flex items-center gap-2 py-0.5'>
      <span className={`h-0.5 w-3 shrink-0 rounded-full ${keyClass}`} />
      <span className='font-semibold tabular-nums'>{shown}</span>
      <span className='truncate text-muted-foreground'>{label}</span>
    </div>
  )
}

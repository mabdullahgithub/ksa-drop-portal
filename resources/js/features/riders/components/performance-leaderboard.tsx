import { useMemo, useState } from 'react'
import { ChevronDown, ChevronUp } from 'lucide-react'
import { Bar, BarChart, CartesianGrid, Rectangle, ReferenceLine, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useDirection } from '@/context/direction-provider'
import { cn } from '@/lib/utils'
import {
  formatCompact,
  formatCount,
  formatRate,
  METRICS,
  successRate,
  teamAverage,
  type PerformanceMetric,
} from '../data/performance'
import type { RiderPerformanceRow } from '../data/types'
import { RiderAvatar, sar } from './rider-parts'

const TOP = 10
const ROW_HEIGHT = 40
const X_AXIS_HEIGHT = 30
const LABEL_WIDTH = 150
/** Status "critical": failed attempts mean something went wrong. */
const FAILED_COLOR = '#d03b3b'

type Props = {
  ranked: RiderPerformanceRow[]
  metric: PerformanceMetric
  view: 'chart' | 'table'
  selectedId: number | undefined
  onSelect: (id: number) => void
}

export function PerformanceLeaderboard({ ranked, metric, view, selectedId, onSelect }: Props) {
  return view === 'chart' ? (
    <LeaderboardChart ranked={ranked} metric={metric} selectedId={selectedId} onSelect={onSelect} />
  ) : (
    <LeaderboardTable ranked={ranked} selectedId={selectedId} onSelect={onSelect} />
  )
}

const MEDALS: Record<number, { fill: string; ring: string; svg: string }> = {
  1: { fill: 'bg-amber-400 text-amber-950', ring: 'ring-amber-200 dark:ring-amber-400/30', svg: '#fbbf24' },
  2: { fill: 'bg-slate-300 text-slate-800 dark:bg-slate-400 dark:text-slate-950', ring: 'ring-slate-100 dark:ring-slate-400/30', svg: '#cbd5e1' },
  3: { fill: 'bg-orange-300 text-orange-950 dark:bg-orange-400', ring: 'ring-orange-100 dark:ring-orange-400/30', svg: '#fdba74' },
}

/**
 * Gold, silver and bronze for the podium; a plain number after that.
 * `overlay` is for sitting on the corner of an avatar.
 */
export function RankBadge({ rank, overlay, className }: { rank: number; overlay?: boolean; className?: string }) {
  const medal = MEDALS[rank]

  return (
    <span
      className={cn(
        'inline-grid size-6 shrink-0 place-items-center rounded-full text-xs font-semibold tabular-nums',
        medal?.fill ?? (overlay ? 'bg-muted text-foreground' : 'text-muted-foreground'),
        overlay ? 'ring-2 ring-background' : medal && `ring-2 ${medal.ring}`,
        className
      )}
    >
      {rank}
    </span>
  )
}

type Datum = { key: string; rank: number; rider: RiderPerformanceRow; delivered: number; failed: number; value: number }

/**
 * Horizontal bars, best at the top. "Delivered" stacks failed attempts after
 * the deliveries so effort and outcome read together; cash and success rate
 * are a single series. The dashed line is the team average.
 */
function LeaderboardChart({ ranked, metric, selectedId, onSelect }: Omit<Props, 'view'>) {
  const { dir } = useDirection()
  const rtl = dir === 'rtl'
  const [showAll, setShowAll] = useState(false)
  const spec = METRICS[metric]
  const stacked = metric === 'delivered'
  // Square at the baseline, 4px round at the data end.
  const end: [number, number, number, number] = rtl ? [4, 0, 0, 4] : [0, 4, 4, 0]

  const data: Datum[] = useMemo(
    () =>
      (showAll ? ranked : ranked.slice(0, TOP)).map((rider, i) => ({
        key: String(rider.id),
        rank: i + 1,
        rider,
        delivered: rider.delivered,
        failed: rider.failed,
        value: spec.total(rider),
      })),
    [ranked, showAll, spec]
  )
  const byKey = useMemo(() => new Map(data.map((d) => [d.key, d])), [data])

  const average = ranked.length > 1 ? teamAverage(ranked, metric) : null
  const averageLabel =
    average === null
      ? ''
      : metric === 'rate'
        ? `Team ${formatRate(average)}`
        : `Avg ${metric === 'cash' ? formatCompact(average) : formatCount(Math.round(average * 10) / 10)}`

  return (
    <div>
      <div className='mb-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground'>
        {stacked ? (
          <>
            <SwatchKey className='bg-[var(--perf-series)]' label='Delivered' />
            <SwatchKey className='bg-[#d03b3b]' label='Failed attempts' />
          </>
        ) : (
          <SwatchKey className='bg-[var(--perf-series)]' label={metric === 'cash' ? 'Cash collected (SAR)' : 'Success rate'} />
        )}
        {average !== null && (
          <span className='inline-flex items-center gap-1.5'>
            <span className='w-3.5 border-t-2 border-dashed border-muted-foreground/70' />
            {metric === 'rate' ? 'Whole team' : 'Team average'}
          </span>
        )}
      </div>

      <div className='cursor-pointer'>
        <ResponsiveContainer width='100%' height={data.length * ROW_HEIGHT + X_AXIS_HEIGHT + 20}>
          <BarChart
            data={data}
            layout='vertical'
            barCategoryGap='28%'
            margin={{ top: 20, right: rtl ? 0 : 12, bottom: 0, left: rtl ? 12 : 0 }}
            onClick={(state: any) => {
              const index = state?.activeTooltipIndex
              const datum = index === null || index === undefined ? undefined : data[Number(index)]
              if (datum) onSelect(datum.rider.id)
            }}
          >
            <CartesianGrid stroke='var(--border)' horizontal={false} />
            <XAxis
              type='number'
              reversed={rtl}
              domain={metric === 'rate' ? [0, 100] : [0, 'auto']}
              allowDecimals={metric !== 'delivered'}
              tickFormatter={spec.axis}
              tick={{ fill: 'var(--muted-foreground)', fontSize: 11 }}
              tickLine={false}
              axisLine={{ stroke: 'var(--border)' }}
              height={X_AXIS_HEIGHT}
            />
            <YAxis
              type='category'
              dataKey='key'
              orientation={rtl ? 'right' : 'left'}
              width={LABEL_WIDTH}
              tickSize={0}
              tickMargin={0}
              tickLine={false}
              axisLine={false}
              interval={0}
              tick={(props: any) => (
                <RiderTick
                  x={Number(props.x)}
                  y={Number(props.y)}
                  datum={byKey.get(props.payload.value)}
                  selected={props.payload.value === String(selectedId)}
                  rtl={rtl}
                />
              )}
            />
            <Tooltip
              cursor={{ fill: 'var(--muted)', fillOpacity: 0.6 }}
              content={({ active, payload }) => {
                if (!active || !payload?.length) return null
                return <RiderTooltip datum={payload[0].payload as Datum} />
              }}
            />
            {stacked ? (
              <>
                <Bar
                  dataKey='delivered'
                  name='Delivered'
                  stackId='attempts'
                  fill='var(--perf-series)'
                  stroke='var(--card)'
                  strokeWidth={2}
                  maxBarSize={22}
                  animationDuration={500}
                  shape={(props: any) => <Rectangle {...props} radius={props.payload.failed > 0 ? 0 : end} />}
                />
                <Bar
                  dataKey='failed'
                  name='Failed attempts'
                  stackId='attempts'
                  fill={FAILED_COLOR}
                  stroke='var(--card)'
                  strokeWidth={2}
                  maxBarSize={22}
                  radius={end}
                  animationDuration={500}
                />
              </>
            ) : (
              <Bar
                dataKey='value'
                name={spec.label}
                fill='var(--perf-series)'
                maxBarSize={22}
                radius={end}
                animationDuration={500}
              />
            )}
            {average !== null && (
              <ReferenceLine
                x={average}
                stroke='var(--muted-foreground)'
                strokeOpacity={0.8}
                strokeDasharray='4 3'
                ifOverflow='extendDomain'
                label={{ value: averageLabel, position: 'top', fill: 'var(--muted-foreground)', fontSize: 11 }}
              />
            )}
          </BarChart>
        </ResponsiveContainer>
      </div>

      {ranked.length > TOP && (
        <Button variant='ghost' size='sm' className='mt-1 h-8 w-full text-xs text-muted-foreground' onClick={() => setShowAll((v) => !v)}>
          {showAll ? (
            <>
              <ChevronUp className='me-1 h-3.5 w-3.5' />
              Show top {TOP}
            </>
          ) : (
            <>
              <ChevronDown className='me-1 h-3.5 w-3.5' />
              Show all {ranked.length} riders
            </>
          )}
        </Button>
      )}
    </div>
  )
}

function SwatchKey({ className, label }: { className: string; label: string }) {
  return (
    <span className='inline-flex items-center gap-1.5'>
      <span className={cn('size-2.5 rounded-[3px]', className)} />
      {label}
    </span>
  )
}

const truncate = (text: string, max: number) => (text.length > max ? `${text.slice(0, max - 1).trimEnd()}…` : text)

/**
 * The category axis: rank (a medal for the top three) and the rider's name.
 * The tick sits on the edge facing the bars; the label area runs away from it.
 */
function RiderTick({ x, y, datum, selected, rtl }: { x: number; y: number; datum?: Datum; selected: boolean; rtl: boolean }) {
  if (!datum) return null
  const medal = MEDALS[datum.rank]
  const outer = rtl ? x + LABEL_WIDTH - 12 : x - LABEL_WIDTH + 12
  const nameX = rtl ? outer - 18 : outer + 18

  return (
    <g>
      <title>{datum.rider.name}</title>
      {medal && <circle cx={outer} cy={y} r={10} fill={medal.svg} />}
      <text
        x={outer}
        y={y}
        dy='0.35em'
        textAnchor='middle'
        fontSize={11}
        fontWeight={600}
        fill={medal ? '#1c1917' : 'var(--muted-foreground)'}
      >
        {datum.rank}
      </text>
      <text
        x={nameX}
        y={y}
        dy='0.35em'
        textAnchor={rtl ? 'end' : 'start'}
        fontSize={12.5}
        fontWeight={selected ? 600 : 400}
        fill={selected ? 'var(--foreground)' : 'var(--muted-foreground)'}
      >
        {truncate(datum.rider.name, 16)}
      </text>
    </g>
  )
}

function RiderTooltip({ datum }: { datum: Datum }) {
  const { rider } = datum
  return (
    <div className='min-w-48 rounded-lg border bg-popover px-3 py-2.5 text-xs text-popover-foreground shadow-md'>
      <div className='mb-2 flex items-center gap-2'>
        <RiderAvatar name={rider.name} photoUrl={rider.photo_url} className='size-6 text-[9px]' />
        <span className='font-semibold'>{rider.name}</span>
        <span className='ms-auto text-muted-foreground'>#{datum.rank}</span>
      </div>
      <TooltipRow value={formatCount(rider.assigned)} label='Assigned' />
      <TooltipRow keyClass='bg-[var(--perf-series)]' value={formatCount(rider.delivered)} label='Delivered' />
      <TooltipRow keyClass='bg-[#d03b3b]' value={formatCount(rider.failed)} label='Failed attempts' />
      <TooltipRow value={formatRate(successRate(rider.delivered, rider.failed))} label='Success rate' />
      <TooltipRow value={sar(rider.cod_collected)} label='Cash collected' />
      <p className='mt-1.5 border-t pt-1.5 text-[11px] text-muted-foreground'>Click for their full details</p>
    </div>
  )
}

function TooltipRow({ keyClass, value, label }: { keyClass?: string; value: string; label: string }) {
  return (
    <div className='flex items-center gap-2 py-0.5'>
      <span className={cn('h-0.5 w-3 shrink-0 rounded-full', keyClass ?? 'bg-transparent')} />
      <span className='font-semibold tabular-nums'>{value}</span>
      <span className='text-muted-foreground'>{label}</span>
    </div>
  )
}

function LeaderboardTable({ ranked, selectedId, onSelect }: Omit<Props, 'view' | 'metric'>) {
  return (
    <div className='overflow-hidden rounded-md border'>
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className='w-10'>#</TableHead>
            <TableHead>Rider</TableHead>
            <TableHead className='text-end'>Assigned</TableHead>
            <TableHead className='text-end'>Delivered</TableHead>
            <TableHead className='text-end'>Failed</TableHead>
            <TableHead className='text-end'>Success</TableHead>
            <TableHead className='hidden text-end sm:table-cell'>Cash collected</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {ranked.map((rider, i) => (
            <TableRow
              key={rider.id}
              data-state={rider.id === selectedId ? 'selected' : undefined}
              className='cursor-pointer'
              tabIndex={0}
              onClick={() => onSelect(rider.id)}
              onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault()
                  onSelect(rider.id)
                }
              }}
            >
              <TableCell>
                <RankBadge rank={i + 1} />
              </TableCell>
              <TableCell>
                <div className='flex items-center gap-2'>
                  <RiderAvatar name={rider.name} photoUrl={rider.photo_url} className='size-7 text-[10px]' />
                  <span className='font-medium'>{rider.name}</span>
                </div>
              </TableCell>
              <TableCell className='text-end tabular-nums'>{formatCount(rider.assigned)}</TableCell>
              <TableCell className='text-end tabular-nums'>{formatCount(rider.delivered)}</TableCell>
              <TableCell className={cn('text-end tabular-nums', rider.failed > 0 && 'text-red-600')}>{formatCount(rider.failed)}</TableCell>
              <TableCell className='text-end tabular-nums'>{formatRate(successRate(rider.delivered, rider.failed))}</TableCell>
              <TableCell className='hidden text-end tabular-nums sm:table-cell'>{sar(rider.cod_collected)}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  )
}

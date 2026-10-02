import { useEffect, useMemo, useState } from 'react'
import { ListChecks, Warehouse } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { cn } from '@/lib/utils'
import { cachedGet, peekCached } from '../data/api'
import { formatCount, formatRate, METRICS, OUTCOMES, rangeLabel, successRate, type PerformanceMetric } from '../data/performance'
import type { RiderPerformanceRow, RiderSummary, TeamDaily } from '../data/types'
import { RankBadge } from './performance-leaderboard'
import { PerformanceTrend } from './performance-trend'
import { RiderParcelsSheet } from './rider-parcels-sheet'
import { RiderAvatar, sar } from './rider-parts'

type Props = {
  rider: RiderPerformanceRow
  rank: number
  riderCount: number
  days: string[]
  teamDaily: TeamDaily
  range: { from: string; to: string }
  metric: PerformanceMetric
}

/**
 * The picked rider (the leader, until someone else is clicked) in full: what
 * they were given, where each parcel ended up, why attempts failed, and how
 * their days compare with the team's. One small request per rider and range;
 * the parcel list only loads when opened.
 */
export function RiderPerformanceDetails({ rider, rank, riderCount, days, teamDaily, range, metric }: Props) {
  const spec = METRICS[metric]
  const firstName = rider.name.split(/\s+/)[0] || rider.name
  const [summary, setSummary] = useState<RiderSummary | null>(null)
  const [summaryFor, setSummaryFor] = useState<number>()
  const [loading, setLoading] = useState(true)
  const [failed, setFailed] = useState(false)
  const [parcelsOpen, setParcelsOpen] = useState(false)
  const [attempt, setAttempt] = useState(0)

  // Clicking along the leaderboard asks only for the rider it stopped on.
  const riderId = useDebouncedValue(rider.id, 200)

  useEffect(() => {
    const url = `/api/riders/${riderId}/performance`
    const params = { from: range.from, to: range.to }
    const cached = peekCached<RiderSummary>(url, params)
    if (cached) {
      setSummary(cached)
      setSummaryFor(riderId)
      setFailed(false)
      setLoading(false)
      return
    }

    const controller = new AbortController()
    setLoading(true)
    cachedGet<RiderSummary>(url, params, controller.signal)
      .then((next) => {
        setSummary(next)
        setSummaryFor(riderId)
        setFailed(false)
      })
      .catch(() => {
        if (!controller.signal.aborted) setFailed(true)
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false)
      })
    return () => controller.abort()
  }, [riderId, range.from, range.to, attempt])

  // Never show one rider's breakdown under another's name; one already
  // fetched shows at once, without waiting out the debounce.
  const current =
    summaryFor === rider.id
      ? summary
      : (peekCached<RiderSummary>(`/api/riders/${rider.id}/performance`, { from: range.from, to: range.to }) ?? null)
  const busy = !current && (loading || riderId !== rider.id)

  const points = useMemo(
    () =>
      current
        ? days.map((day, i) => ({ day, rider: spec.day(current.daily, i), team: spec.team(teamDaily, i) }))
        : [],
    [current, days, spec, teamDaily]
  )

  return (
    <div className='space-y-5'>
      {/* Who */}
      <div className='flex flex-wrap items-center gap-3'>
        <span className='relative shrink-0'>
          <RiderAvatar name={rider.name} photoUrl={rider.photo_url} className='size-12 text-base' />
          <RankBadge rank={rank} overlay className='absolute -end-1 -bottom-1 size-5 text-[10px]' />
        </span>
        <div className='min-w-0 flex-1'>
          <p className='truncate font-semibold'>{rider.name}</p>
          <p className='flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground'>
            <span>
              #{rank} of {riderCount} by {spec.noun}
            </span>
            {rider.warehouse_name && (
              <span className='inline-flex items-center gap-1'>
                <Warehouse className='h-3 w-3' />
                <span dir='auto'>{rider.warehouse_name}</span>
              </span>
            )}
          </p>
        </div>
        <Button variant='outline' size='sm' disabled={!current || current.assigned === 0} onClick={() => setParcelsOpen(true)}>
          <ListChecks className='me-1 h-4 w-4' />
          View {current ? formatCount(current.assigned) : ''} parcel{current?.assigned === 1 ? '' : 's'}
        </Button>
      </div>

      <div className='grid gap-6 lg:grid-cols-3'>
        {/* Totals */}
        <div>
          <SectionTitle>Summary</SectionTitle>
          <dl className='grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3 lg:grid-cols-2'>
            <Stat label='Assigned' value={formatCount(rider.assigned)} hint='Parcels they scanned out, delivered or attempted' />
            <Stat label='Delivered' value={formatCount(rider.delivered)} active={metric === 'delivered'} />
            <Stat label='Failed attempts' value={formatCount(rider.failed)} tone={rider.failed > 0 ? 'text-red-600 dark:text-red-400' : undefined} />
            <Stat label='Cancelled' value={current ? formatCount(current.outcomes.cancelled) : '—'} hint='Assigned parcels that are cancelled now' />
            <Stat label='Success rate' value={formatRate(successRate(rider.delivered, rider.failed))} active={metric === 'rate'} />
            <Stat label='Cash collected' value={sar(rider.cod_collected)} active={metric === 'cash'} />
          </dl>
        </div>

        {/* What happened */}
        <div className={cn('min-w-0 transition-opacity duration-200', busy && current && 'opacity-60')} aria-busy={busy}>
          {failed && !busy ? (
            <div className='space-y-2 text-sm text-muted-foreground'>
              <p>Could not load this rider&rsquo;s parcels.</p>
              <Button variant='outline' size='sm' onClick={() => setAttempt((n) => n + 1)}>
                Try again
              </Button>
            </div>
          ) : !current ? (
            <BreakdownSkeleton />
          ) : (
            <>
              <SectionTitle>
                What happened to the {formatCount(current.assigned)} parcel{current.assigned === 1 ? '' : 's'}
              </SectionTitle>
              <OutcomeBar summary={current} />

              <SectionTitle className='mt-5'>Why attempts failed</SectionTitle>
              <FailedReasons summary={current} />
            </>
          )}
        </div>

        {/* Day by day */}
        <div className='min-w-0'>
          <SectionTitle>
            Daily {spec.noun}
            <span className='font-normal text-muted-foreground'> · {firstName} vs team</span>
          </SectionTitle>
          {days.length <= 1 ? (
            <p className='grid h-[200px] place-items-center rounded-md bg-muted/40 px-3 text-center text-xs text-muted-foreground'>
              Pick more than one day to see the day-by-day trend.
            </p>
          ) : current ? (
            <PerformanceTrend points={points} metric={metric} riderName={firstName} />
          ) : (
            <Skeleton className='h-[224px] rounded-md' />
          )}
        </div>
      </div>

      {current && (
        <RiderParcelsSheet
          key={`${rider.id}:${range.from}:${range.to}`}
          open={parcelsOpen}
          onOpenChange={setParcelsOpen}
          riderId={rider.id}
          riderName={rider.name}
          range={range}
          rangeText={rangeLabel(range.from, range.to)}
          summary={current}
        />
      )}
    </div>
  )
}

function SectionTitle({ children, className }: { children: React.ReactNode; className?: string }) {
  return <p className={cn('mb-3 text-sm font-medium', className)}>{children}</p>
}

function Stat({ label, value, hint, active, tone }: { label: string; value: string; hint?: string; active?: boolean; tone?: string }) {
  return (
    <div title={hint}>
      <dt className='flex items-center gap-1.5 text-xs text-muted-foreground'>
        <span className={cn('size-1.5 shrink-0 rounded-full', active ? 'bg-[var(--perf-series)]' : 'bg-transparent')} />
        {label}
      </dt>
      <dd className={cn('mt-0.5 ps-3 text-lg font-semibold', tone)}>{value}</dd>
    </div>
  )
}

/**
 * Part-to-whole: one bar split by where each assigned parcel is now, with a
 * 2px gap between parts, and every part named and counted underneath.
 */
function OutcomeBar({ summary }: { summary: RiderSummary }) {
  const total = summary.assigned
  const parts = OUTCOMES.filter((o) => summary.outcomes[o.value] > 0)

  if (total === 0) {
    return <p className='text-sm text-muted-foreground'>No parcels in these dates.</p>
  }

  return (
    <div>
      <div className='flex h-3 gap-[2px] overflow-hidden rounded-[4px]' role='img' aria-label='Parcels by outcome'>
        {parts.map((o) => (
          <span
            key={o.value}
            className={cn('h-full min-w-1 transition-[flex-grow] duration-500', o.swatch)}
            style={{ flexGrow: summary.outcomes[o.value], flexBasis: 0 }}
            title={`${o.label}: ${summary.outcomes[o.value]}`}
          />
        ))}
      </div>
      <ul className='mt-3 space-y-1.5'>
        {parts.map((o) => {
          const count = summary.outcomes[o.value]
          return (
            <li key={o.value} className='flex items-center gap-2 text-sm' title={o.hint}>
              <span className={cn('size-2.5 shrink-0 rounded-[3px]', o.swatch)} />
              <span className='min-w-0 flex-1 truncate'>{o.label}</span>
              <span className='font-semibold tabular-nums'>{formatCount(count)}</span>
              <span className='w-11 text-end text-xs text-muted-foreground tabular-nums'>{Math.round((count / total) * 100)}%</span>
            </li>
          )
        })}
      </ul>
    </div>
  )
}

function FailedReasons({ summary }: { summary: RiderSummary }) {
  const reasons = summary.failed_reasons
  if (reasons.length === 0) {
    return <p className='text-sm text-muted-foreground'>No failed attempts in these dates.</p>
  }

  const max = Math.max(...reasons.map((r) => r.count))

  return (
    <ul className='space-y-2'>
      {reasons.map((r) => (
        <li key={r.reason} className='text-sm'>
          <div className='flex items-baseline justify-between gap-2'>
            <span className='min-w-0 truncate'>{r.label}</span>
            <span className='font-semibold tabular-nums'>{formatCount(r.count)}</span>
          </div>
          <div className='mt-1 h-1.5'>
            <div className='h-full rounded-e-[4px] bg-[#d03b3b]/80' style={{ width: `${(r.count / max) * 100}%` }} />
          </div>
        </li>
      ))}
    </ul>
  )
}

function BreakdownSkeleton() {
  return (
    <div className='space-y-3'>
      <Skeleton className='h-4 w-48' />
      <Skeleton className='h-3 w-full rounded-[4px]' />
      {Array.from({ length: 4 }).map((_, i) => (
        <Skeleton key={i} className='h-4' style={{ width: `${85 - i * 12}%` }} />
      ))}
    </div>
  )
}

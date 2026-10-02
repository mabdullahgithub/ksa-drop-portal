import { useEffect, useMemo, useState } from 'react'
import { Banknote, BarChart3, PackageCheck, RotateCw, Table2, Target, Trophy, X } from 'lucide-react'
import { DateRangeFilter } from '@/components/date-range-filter'
import { EmptyState } from '@/components/empty-state'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { BUSINESS_TIMEZONE_LABEL } from '@/lib/business-time'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { cn } from '@/lib/utils'
import { cachedGet, peekCached } from '../data/api'
import {
  DEFAULT_PRESET,
  MAX_DAYS,
  METRICS,
  presetRange,
  RANGE_PRESETS,
  rangeDays,
  rangeLabel,
  rankBy,
  type PerformanceMetric,
  type RangePreset,
} from '../data/performance'
import type { RiderPerformance } from '../data/types'
import { PerformanceLeaderboard } from './performance-leaderboard'
import { RiderPerformanceDetails } from './rider-performance-details'

const METRIC_OPTIONS: { value: PerformanceMetric; label: string; icon: typeof Trophy }[] = [
  { value: 'delivered', label: 'Delivered', icon: PackageCheck },
  { value: 'cash', label: 'Cash', icon: Banknote },
  { value: 'rate', label: 'Success rate', icon: Target },
]

const PERFORMANCE_URL = '/api/riders/performance'

const PREFS_KEY = 'riders.top-performers'

type Prefs = { preset: RangePreset; metric: PerformanceMetric }

function readPrefs(): Prefs {
  const fallback: Prefs = { preset: DEFAULT_PRESET, metric: 'delivered' }
  try {
    const saved = JSON.parse(localStorage.getItem(PREFS_KEY) ?? '{}')
    return {
      preset: RANGE_PRESETS.some((p) => p.value === saved.preset) ? saved.preset : fallback.preset,
      metric: METRIC_OPTIONS.some((m) => m.value === saved.metric) ? saved.metric : fallback.metric,
    }
  } catch {
    return fallback
  }
}

function writePrefs(prefs: Prefs) {
  try {
    localStorage.setItem(PREFS_KEY, JSON.stringify(prefs))
  } catch {
    // Private window or storage blocked: the section still works, it just forgets.
  }
}

/**
 * Riders page: who delivered the most over a date range. The date filter and
 * the metric scope everything in the section — the leaderboard chart and the
 * picked rider's trend.
 */
export function TopPerformers({ onClose }: { onClose: () => void }) {
  const [prefs] = useState(readPrefs)
  const [preset, setPreset] = useState<RangePreset | 'custom'>(prefs.preset)
  const [custom, setCustom] = useState<{ from?: string; to?: string }>({})
  const [metric, setMetric] = useState<PerformanceMetric>(prefs.metric)
  const [view, setView] = useState<'chart' | 'table'>('chart')
  const [selectedId, setSelectedId] = useState<number>()
  const [data, setData] = useState<RiderPerformance | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [attempt, setAttempt] = useState(0)

  // Half a custom range (first click in the calendar) reads as that one day.
  const range = useMemo(() => {
    if (preset !== 'custom') return presetRange(preset)
    const from = custom.from ?? custom.to
    const to = custom.to ?? custom.from
    return from && to ? { from, to } : presetRange(DEFAULT_PRESET)
  }, [preset, custom])

  useEffect(() => {
    if (preset !== 'custom') writePrefs({ preset, metric })
  }, [preset, metric])

  // Clicking through presets or dates asks the server once, for where the
  // clicking stopped.
  const query = useDebouncedValue(range, 300)
  const tooLong = rangeDays(range.from, range.to) > MAX_DAYS

  useEffect(() => {
    if (rangeDays(query.from, query.to) > MAX_DAYS) return

    const params = { from: query.from, to: query.to }
    const cached = peekCached<RiderPerformance>(PERFORMANCE_URL, params)
    if (cached) {
      setData(cached)
      setError(null)
      setLoading(false)
      return
    }

    const controller = new AbortController()
    setLoading(true)
    cachedGet<RiderPerformance>(PERFORMANCE_URL, params, controller.signal)
      .then((next) => {
        setData(next)
        setError(null)
      })
      .catch((err) => {
        if (controller.signal.aborted) return
        setError(err.response?.data?.message || 'Could not load rider performance.')
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false)
      })
    return () => controller.abort()
  }, [query.from, query.to, attempt])

  const ranked = useMemo(() => (data ? rankBy(data.riders, metric) : []), [data, metric])
  const selectedIndex = Math.max(0, ranked.findIndex((r) => r.id === selectedId))
  const selected = ranked[selectedIndex]

  const pickDates = ({ from, to }: { from?: string; to?: string }) => {
    if (!from && !to) {
      setPreset(DEFAULT_PRESET)
      setCustom({})
      return
    }
    setPreset('custom')
    setCustom({ from, to })
  }

  const periodName = preset === 'custom' ? 'Custom dates' : RANGE_PRESETS.find((p) => p.value === preset)!.long
  const firstLoad = loading && !data
  // Showing an older range while the new one is on its way.
  const stale = loading || range.from !== query.from || range.to !== query.to

  return (
    <section
      aria-labelledby='top-performers-title'
      className='rounded-xl border bg-card shadow-xs [--perf-series:#2a78d6] dark:[--perf-series:#3987e5]'
    >
      {/* Title */}
      <div className='flex items-start gap-3 px-4 pt-4'>
        <div className='grid size-10 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'>
          <Trophy className='size-5' />
        </div>
        <div className='min-w-0 flex-1'>
          <h3 id='top-performers-title' className='font-semibold leading-tight'>
            Top performers
          </h3>
          <p className='text-sm text-muted-foreground'>
            {periodName} · {rangeLabel(range.from, range.to)}
            <span className='hidden sm:inline'> · {BUSINESS_TIMEZONE_LABEL}</span>
          </p>
        </div>
        <Button variant='ghost' size='icon' className='size-8 shrink-0' onClick={onClose} title='Hide top performers'>
          <X className='size-4' />
          <span className='sr-only'>Hide top performers</span>
        </Button>
      </div>

      {/* Filters: scope everything below */}
      <div className='flex flex-wrap items-center gap-2 border-b px-4 pt-4 pb-4'>
        <Segmented
          label='Dates'
          value={preset}
          options={RANGE_PRESETS.map((p) => ({ value: p.value, label: p.label }))}
          onChange={(value) => {
            setPreset(value)
            setCustom({})
          }}
        />
        <DateRangeFilter
          label='Custom dates'
          from={preset === 'custom' ? custom.from : range.from}
          to={preset === 'custom' ? custom.to : range.to}
          onChange={pickDates}
          className={cn(preset !== 'custom' && 'border-border')}
        />
        <Segmented label='Rank by' value={metric} options={METRIC_OPTIONS} onChange={setMetric} />
      </div>

      {error && data && (
        <p className='flex items-center gap-2 px-4 pt-3 text-sm text-red-600'>
          {error} Showing the last dates that loaded.
          <Button variant='link' size='sm' className='h-auto p-0 text-red-600' onClick={() => setAttempt((n) => n + 1)}>
            Try again
          </Button>
        </p>
      )}

      <div className='p-4'>
        {tooLong ? (
          <div className='rounded-lg border'>
            <EmptyState
              bot='droid'
              title={`Pick ${MAX_DAYS} days or fewer.`}
              description='Rider performance covers up to three months at a time.'
              action={
                <Button variant='outline' size='sm' onClick={() => pickDates({})}>
                  Back to last 7 days
                </Button>
              }
              className='py-10'
            />
          </div>
        ) : firstLoad ? (
          <SectionSkeleton />
        ) : error && !data ? (
          <div className='flex flex-col items-center gap-3 py-10 text-center text-sm text-muted-foreground'>
            {error}
            <Button variant='outline' size='sm' onClick={() => setAttempt((n) => n + 1)}>
              <RotateCw className='me-1 h-3.5 w-3.5' />
              Try again
            </Button>
          </div>
        ) : ranked.length === 0 ? (
          <div className={cn('transition-opacity duration-200', stale && 'opacity-60')}>
            <EmptyState
              bot='droid'
              title='No deliveries in these dates.'
              description='Riders show up here once they deliver a parcel or record a failed attempt.'
              action={
                preset !== '30d' && (
                  <Button variant='outline' size='sm' onClick={() => setPreset('30d')}>
                    Show last 30 days
                  </Button>
                )
              }
              className='py-10'
            />
          </div>
        ) : (
          <div className={cn('space-y-6 transition-opacity duration-200', stale && 'pointer-events-none opacity-60')} aria-busy={stale}>
            <div className='min-w-0'>
              <div className='mb-3 flex items-center justify-between gap-2'>
                <div>
                  <p className='text-sm font-medium'>Leaderboard</p>
                  <p className='text-xs text-muted-foreground'>
                    {ranked.length} rider{ranked.length === 1 ? '' : 's'} ranked by {METRICS[metric].noun}
                  </p>
                </div>
                <div className='flex shrink-0 items-center overflow-hidden rounded-md border'>
                  <Button
                    variant={view === 'chart' ? 'secondary' : 'ghost'}
                    size='sm'
                    className='h-8 rounded-none px-2.5'
                    onClick={() => setView('chart')}
                    title='Chart view'
                    aria-pressed={view === 'chart'}
                  >
                    <BarChart3 className='h-4 w-4' />
                  </Button>
                  <Button
                    variant={view === 'table' ? 'secondary' : 'ghost'}
                    size='sm'
                    className='h-8 rounded-none px-2.5'
                    onClick={() => setView('table')}
                    title='Table view'
                    aria-pressed={view === 'table'}
                  >
                    <Table2 className='h-4 w-4' />
                  </Button>
                </div>
              </div>
              <PerformanceLeaderboard ranked={ranked} metric={metric} view={view} selectedId={selected?.id} onSelect={setSelectedId} />
            </div>

            {selected && data && (
              <div className='min-w-0 border-t pt-6'>
                <RiderPerformanceDetails
                  rider={selected}
                  rank={selectedIndex + 1}
                  riderCount={data.riders.length}
                  days={data.days}
                  teamDaily={data.team_daily}
                  range={{ from: data.from, to: data.to }}
                  metric={metric}
                />
              </div>
            )}
          </div>
        )}
      </div>
    </section>
  )
}

function Segmented<T extends string>({
  label,
  value,
  options,
  onChange,
  className,
}: {
  label: string
  value: T | string
  options: { value: T; label: string; icon?: typeof Trophy }[]
  onChange: (value: T) => void
  className?: string
}) {
  return (
    <div role='group' aria-label={label} className={cn('flex shrink-0 items-center overflow-hidden rounded-md border', className)}>
      {options.map(({ value: option, label: text, icon: Icon }) => (
        <Button
          key={option}
          variant={value === option ? 'secondary' : 'ghost'}
          size='sm'
          className='h-9 rounded-none px-3 text-sm font-normal data-[active=true]:font-medium'
          data-active={value === option}
          aria-pressed={value === option}
          onClick={() => onChange(option)}
        >
          {Icon && <Icon className='me-1 h-3.5 w-3.5' />}
          {text}
        </Button>
      ))}
    </div>
  )
}

function SectionSkeleton() {
  return (
    <div className='space-y-6'>
      <div className='space-y-4'>
        <Skeleton className='h-4 w-40' />
        {Array.from({ length: 5 }).map((_, i) => (
          <div key={i} className='flex items-center gap-3'>
            <Skeleton className='size-5 rounded-full' />
            <Skeleton className='h-3.5 w-24' />
            <Skeleton className='h-5' style={{ width: `${60 - i * 10}%` }} />
          </div>
        ))}
      </div>
      <div className='grid gap-6 border-t pt-6 lg:grid-cols-3'>
        {Array.from({ length: 3 }).map((_, i) => (
          <Skeleton key={i} className='h-[220px] rounded-lg' />
        ))}
      </div>
    </div>
  )
}

import { addDays, differenceInCalendarDays, format, parse, startOfMonth } from 'date-fns'
import { businessToday } from '@/lib/business-time'
import type { DailySeries, ParcelOutcome, RiderParcelEvent, RiderPerformanceRow, TeamDaily } from './types'

/**
 * Where a rider's parcels ended up, in the order the breakdown bar stacks
 * them. Colours are validated as adjacent pairs in both themes; the legend
 * always prints each label and count beside them.
 */
export const OUTCOMES: { value: ParcelOutcome; label: string; hint: string; swatch: string }[] = [
  { value: 'delivered', label: 'Delivered', hint: 'Delivered by this rider', swatch: 'bg-[var(--perf-series)]' },
  { value: 'out_for_delivery', label: 'Out with rider', hint: 'Scanned out, not finished yet', swatch: 'bg-[#eda100] dark:bg-[#c98500]' },
  { value: 'attempt_fail', label: 'Failed, to retry', hint: 'Last attempt failed; the rider still has it', swatch: 'bg-[#d03b3b]' },
  { value: 'cancelled', label: 'Cancelled', hint: 'Cancelled by an admin or the rider', swatch: 'bg-slate-500 dark:bg-slate-400' },
  { value: 'returned', label: 'Returned', hint: 'The customer did not take it; going back to the merchant', swatch: 'bg-[#4a3aa7] dark:bg-[#9085e9]' },
  { value: 'handed_back', label: 'Handed back', hint: 'Unassigned by an admin, or taken over by another rider', swatch: 'bg-zinc-300 dark:bg-zinc-600' },
  { value: 'other', label: 'Other', hint: 'Any other status', swatch: 'bg-zinc-200 dark:bg-zinc-700' },
]

export const RIDER_ACTIONS: Record<RiderParcelEvent['action'], { label: string; dot: string }> = {
  out_for_delivery: { label: 'Out for delivery', dot: 'bg-[#eda100] dark:bg-[#c98500]' },
  delivered: { label: 'Delivered', dot: 'bg-[var(--perf-series)]' },
  attempt_failed: { label: 'Failed attempt', dot: 'bg-[#d03b3b]' },
  returned: { label: 'Marked returned', dot: 'bg-[#4a3aa7] dark:bg-[#9085e9]' },
  cancelled: { label: 'Marked cancelled', dot: 'bg-slate-500 dark:bg-slate-400' },
  returned_to_hub: { label: 'Handed back at the hub', dot: 'bg-zinc-400 dark:bg-zinc-500' },
}

export type RangePreset = 'today' | '7d' | '30d' | 'month'

export const RANGE_PRESETS: { value: RangePreset; label: string; long: string }[] = [
  { value: 'today', label: 'Today', long: 'Today' },
  { value: '7d', label: '7 days', long: 'Last 7 days' },
  { value: '30d', label: '30 days', long: 'Last 30 days' },
  { value: 'month', label: 'This month', long: 'This month' },
]

export const DEFAULT_PRESET: RangePreset = '7d'

/** Longest range the server answers (RiderPerformance::MAX_DAYS). */
export const MAX_DAYS = 92

/** Days in a range, both ends counted. */
export const rangeDays = (from: string, to: string) => differenceInCalendarDays(parseDay(to), parseDay(from)) + 1

const DAY = 'yyyy-MM-dd'

export const parseDay = (day: string) => parse(day, DAY, new Date())

/** A preset as KSA calendar days, both ends inclusive. */
export function presetRange(preset: RangePreset): { from: string; to: string } {
  const today = businessToday()
  const to = format(today, DAY)
  const from = {
    today: today,
    '7d': addDays(today, -6),
    '30d': addDays(today, -29),
    month: startOfMonth(today),
  }[preset]

  return { from: format(from, DAY), to }
}

export function rangeLabel(from: string, to: string) {
  const start = parseDay(from)
  const end = parseDay(to)
  if (from === to) return format(start, 'MMM d, yyyy')
  if (start.getFullYear() !== end.getFullYear()) return `${format(start, 'MMM d, yyyy')} – ${format(end, 'MMM d, yyyy')}`
  return `${format(start, 'MMM d')} – ${format(end, 'MMM d, yyyy')}`
}

/** Delivered out of every finished attempt, 0–100. Null when there were none. */
export const successRate = (delivered: number, failed: number) =>
  delivered + failed > 0 ? (delivered / (delivered + failed)) * 100 : null

const count = new Intl.NumberFormat('en-US')
const percent = new Intl.NumberFormat('en-US', { maximumFractionDigits: 1 })
const compact = new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 })

export const formatCount = (n: number) => count.format(n)
export const formatRate = (rate: number | null) => (rate === null ? '—' : `${percent.format(rate)}%`)
export const formatCompact = (n: number) => compact.format(n)

export type PerformanceMetric = 'delivered' | 'cash' | 'rate'

type MetricSpec = {
  label: string
  /** "#1 of 8 by …" */
  noun: string
  /** The rider's total over the range. */
  total: (row: RiderPerformanceRow) => number
  /** One rider on one day (index into `days`). Null when there is nothing to measure. */
  day: (daily: DailySeries, i: number) => number | null
  /** The whole team on one day, for the trend's comparison line. */
  team: (team: TeamDaily, i: number) => number | null
  /** Longest bar in the leaderboard. */
  max: (rows: RiderPerformanceRow[]) => number
  format: (n: number) => string
  axis: (n: number) => string
  teamLabel: string
}

/** Per rider who made an attempt that day; 0 when nobody did. */
const perWorkingRider = (total: number, riders: number) => (riders > 0 ? total / riders : 0)

export const METRICS: Record<PerformanceMetric, MetricSpec> = {
  delivered: {
    label: 'Delivered',
    noun: 'deliveries',
    total: (r) => r.delivered,
    day: (d, i) => d.delivered[i],
    team: (t, i) => perWorkingRider(t.delivered[i], t.riders[i]),
    max: (rows) => Math.max(0, ...rows.map((r) => r.delivered)),
    format: formatCount,
    axis: formatCompact,
    teamLabel: 'Team average',
  },
  cash: {
    label: 'Cash collected',
    noun: 'cash collected',
    total: (r) => r.cod_collected,
    day: (d, i) => d.cod_collected[i],
    team: (t, i) => perWorkingRider(t.cod_collected[i], t.riders[i]),
    max: (rows) => Math.max(0, ...rows.map((r) => r.cod_collected)),
    format: (n) => `SAR ${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`,
    axis: formatCompact,
    teamLabel: 'Team average',
  },
  rate: {
    label: 'Success rate',
    noun: 'success rate',
    total: (r) => successRate(r.delivered, r.failed) ?? 0,
    day: (d, i) => successRate(d.delivered[i], d.failed[i]),
    team: (t, i) => successRate(t.delivered[i], t.failed[i]),
    max: () => 100,
    format: (n) => formatRate(n),
    axis: (n) => `${n}%`,
    teamLabel: 'Whole team',
  },
}

/**
 * The leaderboard's reference line: the mean rider for counts and cash, the
 * whole team's rate for success rate. Null when nobody worked.
 */
export function teamAverage(rows: RiderPerformanceRow[], metric: PerformanceMetric): number | null {
  if (!rows.length) return null
  if (metric === 'rate') {
    return successRate(
      rows.reduce((sum, r) => sum + r.delivered, 0),
      rows.reduce((sum, r) => sum + r.failed, 0)
    )
  }
  return rows.reduce((sum, r) => sum + METRICS[metric].total(r), 0) / rows.length
}

/**
 * Best first by the chosen metric. Ties go to more deliveries, then fewer
 * failed attempts, so a 100% rate from one parcel ranks below one from ten.
 */
export function rankBy(rows: RiderPerformanceRow[], metric: PerformanceMetric) {
  const { total } = METRICS[metric]
  return [...rows].sort(
    (a, b) => total(b) - total(a) || b.delivered - a.delivered || a.failed - b.failed || a.name.localeCompare(b.name)
  )
}

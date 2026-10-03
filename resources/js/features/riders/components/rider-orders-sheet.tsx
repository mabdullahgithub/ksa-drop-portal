import { useEffect, useMemo, useState } from 'react'
import { addDays, format } from 'date-fns'
import { DateRangeFilter } from '@/components/date-range-filter'
import { businessToday } from '@/lib/business-time'
import { cn } from '@/lib/utils'
import { cachedGet } from '../data/api'
import { MAX_DAYS, rangeLabel } from '../data/performance'
import type { ParcelOutcome, RiderRow, RiderSummary } from '../data/types'
import { RiderParcelsSheet } from './rider-parcels-sheet'

/** Shortcuts beside the date picker. The longest the server answers is a quarter. */
const PERIODS = [
  { value: 'today', label: 'Today', days: 1 },
  { value: '7d', label: '7 days', days: 7 },
  { value: '30d', label: '30 days', days: 30 },
  { value: '90d', label: '90 days', days: 90 },
] as const

type Period = (typeof PERIODS)[number]['value']

const DEFAULT_PERIOD: Period = '30d'

/** A period as KSA calendar days, both ends inclusive, ending today. */
function periodRange(period: Period) {
  const today = businessToday()
  const days = PERIODS.find((p) => p.value === period)!.days

  return { from: format(addDays(today, 1 - days), 'yyyy-MM-dd'), to: format(today, 'yyyy-MM-dd') }
}

type Props = {
  rider: RiderRow
  /** The filter to open on: delivered, failed… Everything when absent. */
  outcome?: ParcelOutcome
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * One rider's orders, straight from their card: everything they handled
 * between two dates, or only what they delivered, what failed, and so on —
 * each with the rider's updates, the reason an attempt failed, and its photo
 * and location. The same list Top performers opens, with its own dates: a
 * shortcut, or any range picked from the calendar.
 */
export function RiderOrdersSheet({ rider, outcome, open, onOpenChange }: Props) {
  const [period, setPeriod] = useState<Period | 'custom'>(DEFAULT_PERIOD)
  const [custom, setCustom] = useState<{ from?: string; to?: string }>({})
  const [summary, setSummary] = useState<RiderSummary | null>(null)

  // Half a custom range (first click in the calendar) reads as that one day.
  const range = useMemo(() => {
    if (period !== 'custom') return periodRange(period)
    const from = custom.from ?? custom.to
    const to = custom.to ?? custom.from
    return from && to ? { from, to } : periodRange(DEFAULT_PERIOD)
  }, [period, custom])

  const pickDates = ({ from, to }: { from?: string; to?: string }) => {
    if (!from && !to) {
      setPeriod(DEFAULT_PERIOD)
      setCustom({})
      return
    }
    setPeriod('custom')
    setCustom({ from, to })
  }

  // The counts beside the filters; the list itself loads on its own.
  useEffect(() => {
    if (!open) return
    const controller = new AbortController()
    setSummary(null)
    cachedGet<RiderSummary>(`/api/riders/${rider.id}/performance`, range, controller.signal)
      .then(setSummary)
      .catch(() => {
        // The filters show without counts.
      })
    return () => controller.abort()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, rider.id, range.from, range.to])

  return (
    <RiderParcelsSheet
      open={open}
      onOpenChange={onOpenChange}
      riderId={rider.id}
      riderName={rider.name}
      title={`${rider.name}’s orders`}
      range={range}
      rangeText={rangeLabel(range.from, range.to)}
      summary={summary}
      initialOutcome={outcome}
      alwaysShow={['delivered', 'attempt_fail']}
      toolbar={
        <div className='mt-1 flex flex-wrap items-center gap-2'>
          <div className='grid min-w-64 flex-1 grid-cols-4 gap-1 rounded-lg bg-muted p-1'>
            {PERIODS.map((option) => (
              <button
                key={option.value}
                type='button'
                aria-pressed={period === option.value}
                onClick={() => {
                  setPeriod(option.value)
                  setCustom({})
                }}
                className={cn(
                  'h-8 rounded-md text-sm font-medium transition-colors',
                  period === option.value ? 'bg-background shadow-xs' : 'text-muted-foreground hover:text-foreground'
                )}
              >
                {option.label}
              </button>
            ))}
          </div>
          <DateRangeFilter
            label='Custom dates'
            from={period === 'custom' ? custom.from : undefined}
            to={period === 'custom' ? custom.to : undefined}
            onChange={pickDates}
            maxDays={MAX_DAYS}
            className='h-10'
          />
        </div>
      }
    />
  )
}

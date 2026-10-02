import { useEffect, useState } from 'react'
import { addDays, format } from 'date-fns'
import { businessToday } from '@/lib/business-time'
import { cn } from '@/lib/utils'
import { cachedGet } from '../data/api'
import { rangeLabel } from '../data/performance'
import type { ParcelOutcome, RiderRow, RiderSummary } from '../data/types'
import { RiderParcelsSheet } from './rider-parcels-sheet'

/** How far back the list looks. The longest the server answers is a quarter. */
const PERIODS = [
  { value: 'today', label: 'Today', days: 1 },
  { value: '7d', label: '7 days', days: 7 },
  { value: '30d', label: '30 days', days: 30 },
  { value: '90d', label: '90 days', days: 90 },
] as const

type Period = (typeof PERIODS)[number]['value']

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
 * One rider's orders, straight from their card: everything they handled in a
 * period, or only what they delivered, what failed, and so on — each with
 * the rider's updates, the reason an attempt failed, and its photo and
 * location. The same list Top performers opens, with its own period.
 */
export function RiderOrdersSheet({ rider, outcome, open, onOpenChange }: Props) {
  const [period, setPeriod] = useState<Period>('30d')
  const [summary, setSummary] = useState<RiderSummary | null>(null)
  const range = periodRange(period)

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
        <div className='mt-1 grid grid-cols-4 gap-1 rounded-lg bg-muted p-1'>
          {PERIODS.map((option) => (
            <button
              key={option.value}
              type='button'
              aria-pressed={period === option.value}
              onClick={() => setPeriod(option.value)}
              className={cn(
                'h-8 rounded-md text-sm font-medium transition-colors',
                period === option.value ? 'bg-background shadow-xs' : 'text-muted-foreground hover:text-foreground'
              )}
            >
              {option.label}
            </button>
          ))}
        </div>
      }
    />
  )
}

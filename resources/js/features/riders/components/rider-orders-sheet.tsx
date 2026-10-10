import { useEffect, useMemo, useState } from 'react'
import { cachedGet } from '../data/api'
import { rangeLabel } from '../data/performance'
import type { ParcelOutcome, RiderRow, RiderSummary } from '../data/types'
import { DEFAULT_PERIOD, PeriodPicker, rangeOf, type PeriodChoice } from './period-picker'
import { RiderParcelsSheet } from './rider-parcels-sheet'

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
 * each with its status, the rider's latest update and note, and its photo
 * and location. The same list Top performers opens, with its own dates: a
 * shortcut, or any range picked from the calendar.
 */
export function RiderOrdersSheet({ rider, outcome, open, onOpenChange }: Props) {
  const [period, setPeriod] = useState<PeriodChoice>({ period: DEFAULT_PERIOD })
  const [summary, setSummary] = useState<RiderSummary | null>(null)

  const range = useMemo(() => rangeOf(period), [period])

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
      toolbar={<PeriodPicker value={period} onChange={setPeriod} />}
    />
  )
}

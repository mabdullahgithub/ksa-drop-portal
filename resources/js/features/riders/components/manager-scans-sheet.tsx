import { useEffect, useMemo, useRef, useState } from 'react'
import axios from 'axios'
import { Loader2, Search as SearchIcon, X } from 'lucide-react'
import { EmptyState } from '@/components/empty-state'
import { SearchBeam } from '@/components/search-beam'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { ScanRow, type StockScan } from '@/features/inventory/components/stock-scans-sheet'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { cn } from '@/lib/utils'
import { formatCount, rangeLabel } from '../data/performance'
import type { ManagerScanFilter, ManagerScanTotals, RiderRow } from '../data/types'
import { DEFAULT_PERIOD, PeriodPicker, rangeOf, type PeriodChoice } from './period-picker'
import { Chip } from './rider-parcels-sheet'

/** `totals`: on the first page only. */
type ScansPage = { scans: StockScan[]; next_page: number | null; totals: ManagerScanTotals | null }

type Props = {
  rider: RiderRow
  /** The filter to open on. Everything when absent. */
  filter?: ManagerScanFilter
  /** Open on today, as the card counts it, instead of the last 30 days. */
  today?: boolean
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * One inventory manager's scans, straight from their card: every parcel
 * they scanned out of and in to the warehouse between two dates, and what
 * each scan did to stock. The same rows as the Inventory page's scan log,
 * theirs only, with a rider sheet's dates.
 */
export function ManagerScansSheet({ rider, filter: initialFilter, today, open, onOpenChange }: Props) {
  const [period, setPeriod] = useState<PeriodChoice>({ period: today ? 'today' : DEFAULT_PERIOD })
  const [filter, setFilter] = useState<ManagerScanFilter | 'all'>(initialFilter ?? 'all')
  const [query, setQuery] = useState('')
  const search = useDebouncedValue(query.trim(), 350)
  const [scans, setScans] = useState<StockScan[]>([])
  const [nextPage, setNextPage] = useState<number | null>(null)
  const [totals, setTotals] = useState<ManagerScanTotals | null>(null)
  const [loading, setLoading] = useState(false)
  const [failed, setFailed] = useState(false)

  const range = useMemo(() => rangeOf(period), [period])

  // A "load more" that lands after the filters changed belongs to the old list.
  const listKey = `${range.from}|${range.to}|${filter}|${search}`
  const currentKey = useRef(listKey)
  currentKey.current = listKey

  const load = async (page: number, signal?: AbortSignal) => {
    const key = listKey
    setLoading(true)
    try {
      const { data } = await axios.get<ScansPage>(`/api/riders/${rider.id}/stock-scans`, {
        params: {
          from: range.from,
          to: range.to,
          direction: filter === 'out' || filter === 'in' ? filter : undefined,
          unmatched: filter === 'unmatched' ? 1 : undefined,
          search: search || undefined,
          page,
        },
        signal,
      })
      if (currentKey.current !== key) return
      setScans((list) => (page === 1 ? data.scans : [...list, ...data.scans]))
      setNextPage(data.next_page)
      if (data.totals) setTotals(data.totals)
      setFailed(false)
    } catch {
      if (!signal?.aborted && currentKey.current === key) setFailed(true)
    } finally {
      if (!signal?.aborted && currentKey.current === key) setLoading(false)
    }
  }

  // The counts are of the dates alone: other dates, other counts.
  useEffect(() => setTotals(null), [range.from, range.to])

  useEffect(() => {
    if (!open) return
    const controller = new AbortController()
    load(1, controller.signal)
    return () => controller.abort()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, listKey])

  const filtering = filter !== 'all' || query.trim() !== ''
  const reset = () => {
    setFilter('all')
    setQuery('')
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className='flex w-full flex-col gap-0 sm:max-w-xl'>
        <SheetHeader className='border-b'>
          <SheetTitle>{rider.name}&rsquo;s scans</SheetTitle>
          <SheetDescription>
            {totals &&
              `${formatCount(totals.out.parcels)} out (${formatCount(totals.out.pieces)} pcs) · ${formatCount(totals.in.parcels)} in (${formatCount(totals.in.pieces)} pcs) · `}
            {rangeLabel(range.from, range.to)}
          </SheetDescription>

          <PeriodPicker value={period} onChange={setPeriod} />

          <div className='mt-2 flex flex-wrap gap-1.5'>
            <Chip
              active={filter === 'all'}
              onClick={() => setFilter('all')}
              label='All'
              count={totals ? totals.out.parcels + totals.in.parcels : undefined}
            />
            <Chip active={filter === 'out'} onClick={() => setFilter('out')} label='Out' count={totals?.out.parcels} swatch='bg-orange-500' />
            <Chip active={filter === 'in'} onClick={() => setFilter('in')} label='In' count={totals?.in.parcels} swatch='bg-green-600' />
            <Chip active={filter === 'unmatched'} onClick={() => setFilter('unmatched')} label='Not linked' count={totals?.unmatched} />
          </div>

          <div className='relative mt-2'>
            <SearchIcon className='pointer-events-none absolute top-1/2 z-10 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground inset-s-2.5' />
            <SearchBeam>
              <Input
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder='Search tracking, order number, SKU or product…'
                className='h-9 ps-8 text-sm'
                maxLength={60}
              />
            </SearchBeam>
          </div>
        </SheetHeader>

        <div className={cn('flex-1 overflow-y-auto transition-opacity', loading && scans.length > 0 && nextPage === null && 'opacity-60')}>
          {failed && scans.length === 0 ? (
            <div className='flex flex-col items-center gap-3 py-12 text-sm text-muted-foreground'>
              Could not load the scans.
              <Button variant='outline' size='sm' onClick={() => load(1)}>
                Try again
              </Button>
            </div>
          ) : loading && scans.length === 0 ? (
            <div className='space-y-4 p-4'>
              {Array.from({ length: 5 }).map((_, i) => (
                <Skeleton key={i} className='h-16 w-full' />
              ))}
            </div>
          ) : scans.length === 0 ? (
            <EmptyState
              bot='droid'
              title='No scans found.'
              description={filtering ? 'No scan matches this search or filter.' : `${rider.name} scanned no parcels in these dates.`}
              action={
                filtering && (
                  <Button variant='outline' size='sm' onClick={reset}>
                    <X className='me-1 h-3.5 w-3.5' />
                    Reset filters
                  </Button>
                )
              }
              className='py-12'
            />
          ) : (
            <>
              <ul className='divide-y'>
                {scans.map((scan) => (
                  <ScanRow key={scan.id} scan={scan} hideScanner />
                ))}
              </ul>
              {nextPage !== null && (
                <div className='border-t p-3'>
                  <Button variant='ghost' size='sm' className='w-full' disabled={loading} onClick={() => load(nextPage)}>
                    {loading ? <Loader2 className='me-1 h-4 w-4 animate-spin' /> : null}
                    {loading ? 'Loading…' : 'Load more scans'}
                  </Button>
                </div>
              )}
            </>
          )}
        </div>
      </SheetContent>
    </Sheet>
  )
}

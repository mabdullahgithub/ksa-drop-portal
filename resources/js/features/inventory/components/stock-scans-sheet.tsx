import { useEffect, useRef, useState } from 'react'
import axios from 'axios'
import { format } from 'date-fns'
import { ArrowDownToLine, ArrowUpFromLine, Loader2, Search as SearchIcon, X } from 'lucide-react'
import { EmptyState } from '@/components/empty-state'
import { SearchBeam } from '@/components/search-beam'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { toBusinessTime } from '@/lib/business-time'
import { cn } from '@/lib/utils'

/** One item in a scanned parcel. Not matched: linked to no product, so no stock changed for it. */
type ScanItem = { name: string; sku: string | null; quantity: number; matched: boolean; stock_after: number | null }

/** One parcel an inventory manager scanned OUT of or IN to the warehouse. */
type StockScan = {
  id: number
  direction: 'out' | 'in'
  occurred_at: string
  pieces: number
  unmatched: number
  scanned_by: string | null
  /** The rider who had the parcel when it was scanned. */
  parcel_rider: string | null
  items: ScanItem[]
  parcel: {
    tracking_number: string | null
    order_number: string | null
    courier_label: string
    status_label: string
    receiver_name: string | null
  } | null
}

type ScansPage = { scans: StockScan[]; next_page: number | null }

const FILTERS = [
  { value: 'all', label: 'All' },
  { value: 'out', label: 'Out' },
  { value: 'in', label: 'In' },
  { value: 'unmatched', label: 'Not linked' },
] as const

type Filter = (typeof FILTERS)[number]['value']

const when = (iso: string) => format(toBusinessTime(iso), 'MMM d, HH:mm')

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Every parcel the inventory managers scanned at the warehouse, newest
 * first, and what each scan did to stock. "Not linked" finds the scans with
 * an item that matched no product: the parcel went through, but that item's
 * stock didn't move — fix its SKU and the next one will.
 */
export function StockScansSheet({ open, onOpenChange }: Props) {
  const [filter, setFilter] = useState<Filter>('all')
  const [query, setQuery] = useState('')
  const search = useDebouncedValue(query.trim(), 350)
  const [scans, setScans] = useState<StockScan[]>([])
  const [nextPage, setNextPage] = useState<number | null>(null)
  const [loading, setLoading] = useState(false)
  const [failed, setFailed] = useState(false)

  // A "load more" that lands after the filters changed belongs to the old list.
  const listKey = `${filter}|${search}`
  const currentKey = useRef(listKey)
  currentKey.current = listKey

  const load = async (page: number, signal?: AbortSignal) => {
    const key = listKey
    setLoading(true)
    try {
      const { data } = await axios.get<ScansPage>('/api/stock-scans', {
        params: {
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
      setFailed(false)
    } catch {
      if (!signal?.aborted && currentKey.current === key) setFailed(true)
    } finally {
      if (!signal?.aborted && currentKey.current === key) setLoading(false)
    }
  }

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
          <SheetTitle>Scan log</SheetTitle>
          <SheetDescription>
            Parcels the inventory managers scanned out of and in to the warehouse, and what each scan did to stock.
          </SheetDescription>

          <div className='mt-2 flex flex-wrap gap-1.5'>
            {FILTERS.map((option) => (
              <button
                key={option.value}
                type='button'
                aria-pressed={filter === option.value}
                onClick={() => setFilter(option.value)}
                className={cn(
                  'h-8 rounded-full border px-3 text-sm font-medium transition-colors',
                  filter === option.value ? 'border-foreground bg-foreground text-background' : 'text-muted-foreground hover:text-foreground'
                )}
              >
                {option.label}
              </button>
            ))}
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
              title={filtering ? 'No scans found.' : 'No scans yet.'}
              description={
                filtering
                  ? 'No scan matches this search or filter.'
                  : 'Add an inventory manager on the Riders page. Their OUT and IN scans show here.'
              }
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
                  <ScanRow key={scan.id} scan={scan} />
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

function ScanRow({ scan }: { scan: StockScan }) {
  const out = scan.direction === 'out'
  const Icon = out ? ArrowUpFromLine : ArrowDownToLine

  return (
    <li className='px-4 py-3'>
      <div className='flex flex-wrap items-center gap-x-2 gap-y-1'>
        <Badge
          variant='outline'
          className={cn(
            'gap-1',
            out ? 'border-orange-300 text-orange-700 dark:text-orange-400' : 'border-green-300 text-green-700 dark:text-green-400'
          )}
        >
          <Icon className='h-3 w-3' />
          {out ? 'Out' : 'In'}
        </Badge>
        <span className='font-mono text-sm font-medium' dir='ltr'>
          {scan.parcel?.tracking_number ?? '—'}
        </span>
        <span className='ms-auto text-xs text-muted-foreground tabular-nums'>{when(scan.occurred_at)}</span>
      </div>

      <p className='mt-1 text-xs text-muted-foreground'>
        {[
          scan.parcel?.order_number && `Order ${scan.parcel.order_number}`,
          scan.parcel?.courier_label,
          scan.parcel?.status_label,
          scan.scanned_by && `by ${scan.scanned_by}`,
          !out && scan.parcel_rider && `from ${scan.parcel_rider}`,
        ]
          .filter(Boolean)
          .join(' · ')}
      </p>

      <ul className='mt-2 space-y-1'>
        {scan.items.map((item, index) => (
          <li key={index} className='flex items-baseline gap-2 text-sm'>
            <span className={cn('shrink-0 font-medium tabular-nums', out ? 'text-orange-700 dark:text-orange-400' : 'text-green-700 dark:text-green-400')}>
              {out ? '−' : '+'}
              {item.quantity}
            </span>
            <span className='min-w-0 flex-1'>
              <span className='line-clamp-1'>{item.name}</span>
              {item.sku && (
                <span className='font-mono text-xs text-muted-foreground' dir='ltr'>
                  {item.sku}
                </span>
              )}
            </span>
            {item.matched ? (
              <span className={cn('shrink-0 text-xs tabular-nums', (item.stock_after ?? 0) < 0 ? 'font-medium text-red-600' : 'text-muted-foreground')}>
                {item.stock_after} left
              </span>
            ) : (
              <Badge
                variant='outline'
                className='shrink-0 border-amber-300 text-amber-700 dark:text-amber-400'
                title='This item is linked to no product, so no stock changed for it. Give the order item and the product the same SKU.'
              >
                Not linked
              </Badge>
            )}
          </li>
        ))}
      </ul>
    </li>
  )
}

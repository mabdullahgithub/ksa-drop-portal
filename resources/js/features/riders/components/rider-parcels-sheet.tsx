import { useEffect, useRef, useState } from 'react'
import { format } from 'date-fns'
import { ArrowRightLeft, Ban, Loader2, Search as SearchIcon, Undo2, X } from 'lucide-react'
import { EmptyState } from '@/components/empty-state'
import { SearchBeam } from '@/components/search-beam'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { toBusinessTime } from '@/lib/business-time'
import { cn } from '@/lib/utils'
import { cachedGet } from '../data/api'
import { formatCount, OUTCOMES, RIDER_ACTIONS } from '../data/performance'
import type { ParcelOutcome, RiderParcel, RiderParcelsPage, RiderSummary } from '../data/types'
import { sar } from './rider-parts'

const PAYMENT = { cash: 'cash', card: 'card', transfer: 'bank transfer' } as Record<string, string>

const when = (iso: string | null) => (iso ? format(toBusinessTime(iso), 'MMM d, HH:mm') : '—')

type Props = {
  open: boolean
  onOpenChange: (open: boolean) => void
  riderId: number
  riderName: string
  range: { from: string; to: string }
  rangeText: string
  /** For the filter counts, which are already known. */
  summary: RiderSummary
}

/**
 * Every parcel the rider handled in the range and what happened to it: their
 * own updates in order, then how it ended if it left their hands. Nothing
 * loads until the sheet opens; then one page at a time, filtered and
 * searched on the server.
 */
export function RiderParcelsSheet({ open, onOpenChange, riderId, riderName, range, rangeText, summary }: Props) {
  const [outcome, setOutcome] = useState<ParcelOutcome | 'all'>('all')
  const [query, setQuery] = useState('')
  const search = useDebouncedValue(query.trim(), 350)
  const [parcels, setParcels] = useState<RiderParcel[]>([])
  const [nextPage, setNextPage] = useState<number | null>(null)
  const [loading, setLoading] = useState(false)
  const [failed, setFailed] = useState(false)

  // A "load more" that lands after the filters changed belongs to the old list.
  const listKey = `${outcome}|${search}`
  const currentKey = useRef(listKey)
  currentKey.current = listKey

  const load = async (page: number, signal?: AbortSignal) => {
    const key = listKey
    setLoading(true)
    try {
      const data = await cachedGet<RiderParcelsPage>(
        `/api/riders/${riderId}/parcels`,
        { from: range.from, to: range.to, outcome: outcome === 'all' ? undefined : outcome, search: search || undefined, page },
        signal
      )
      if (currentKey.current !== key) return
      setParcels((list) => (page === 1 ? data.parcels : [...list, ...data.parcels]))
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

  const filtering = outcome !== 'all' || query.trim() !== ''
  const reset = () => {
    setOutcome('all')
    setQuery('')
  }

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className='flex w-full flex-col gap-0 sm:max-w-xl'>
        <SheetHeader className='border-b'>
          <SheetTitle>{riderName}&rsquo;s parcels</SheetTitle>
          <SheetDescription>
            {formatCount(summary.assigned)} parcel{summary.assigned === 1 ? '' : 's'} · {rangeText}
          </SheetDescription>

          <div className='mt-2 flex flex-wrap gap-1.5'>
            <Chip active={outcome === 'all'} onClick={() => setOutcome('all')} label='All' count={summary.assigned} />
            {OUTCOMES.filter((o) => summary.outcomes[o.value] > 0).map((o) => (
              <Chip
                key={o.value}
                active={outcome === o.value}
                onClick={() => setOutcome(o.value)}
                label={o.label}
                count={summary.outcomes[o.value]}
                swatch={o.swatch}
              />
            ))}
          </div>

          <div className='relative mt-2'>
            <SearchIcon className='pointer-events-none absolute top-1/2 z-10 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground inset-s-2.5' />
            <SearchBeam>
              <Input
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder='Search tracking or order number…'
                className='h-9 ps-8 text-sm'
                maxLength={60}
              />
            </SearchBeam>
          </div>
        </SheetHeader>

        <div className={cn('flex-1 overflow-y-auto transition-opacity', loading && parcels.length > 0 && nextPage === null && 'opacity-60')}>
          {failed && parcels.length === 0 ? (
            <div className='flex flex-col items-center gap-3 py-12 text-sm text-muted-foreground'>
              Could not load the parcels.
              <Button variant='outline' size='sm' onClick={() => load(1)}>
                Try again
              </Button>
            </div>
          ) : loading && parcels.length === 0 ? (
            <ListSkeleton />
          ) : parcels.length === 0 ? (
            <EmptyState
              bot='droid'
              title='No parcels found.'
              description='No parcel matches this search or filter.'
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
                {parcels.map((parcel) => (
                  <ParcelItem key={parcel.id} parcel={parcel} />
                ))}
              </ul>
              {nextPage !== null && (
                <div className='border-t p-3'>
                  <Button variant='ghost' size='sm' className='w-full' disabled={loading} onClick={() => load(nextPage)}>
                    {loading ? <Loader2 className='me-1 h-4 w-4 animate-spin' /> : null}
                    {loading ? 'Loading…' : 'Load more parcels'}
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

function Chip({
  active,
  onClick,
  label,
  count,
  swatch,
}: {
  active: boolean
  onClick: () => void
  label: string
  count: number
  swatch?: string
}) {
  return (
    <button
      type='button'
      aria-pressed={active}
      onClick={onClick}
      className={cn(
        'inline-flex h-7 items-center gap-1.5 rounded-full border px-2.5 text-xs transition-colors',
        active ? 'border-foreground/30 bg-secondary font-medium text-foreground' : 'text-muted-foreground hover:bg-muted/60'
      )}
    >
      {swatch && <span className={cn('size-2 rounded-full', swatch)} />}
      {label}
      <span className='tabular-nums'>{formatCount(count)}</span>
    </button>
  )
}

function ListSkeleton() {
  return (
    <ul className='divide-y'>
      {Array.from({ length: 5 }).map((_, i) => (
        <li key={i} className='space-y-2 px-4 py-4'>
          <div className='flex justify-between'>
            <Skeleton className='h-4 w-36' />
            <Skeleton className='h-5 w-20 rounded-full' />
          </div>
          <Skeleton className='h-3 w-48' />
          <Skeleton className='h-3 w-40' />
        </li>
      ))}
    </ul>
  )
}

function ParcelItem({ parcel }: { parcel: RiderParcel }) {
  const outcome = OUTCOMES.find((o) => o.value === parcel.outcome)!

  return (
    <li className='px-4 py-3.5'>
      <div className='flex items-start justify-between gap-3'>
        <div className='min-w-0'>
          <p className='truncate font-mono text-sm font-medium' dir='ltr'>
            {parcel.tracking_number ?? `Shipment ${parcel.id}`}
          </p>
          <p className='text-xs text-muted-foreground'>
            {[parcel.order_number && `Order ${parcel.order_number}`, parcel.city, parcel.cod_amount > 0 ? `COD ${sar(parcel.cod_amount)}` : 'Prepaid']
              .filter(Boolean)
              .join(' · ')}
          </p>
        </div>
        <span className='inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium' title={outcome.hint}>
          <span className={cn('size-2 rounded-full', outcome.swatch)} />
          {outcome.label}
        </span>
      </div>

      <ol className='mt-3 space-y-2 border-s ps-4'>
        {parcel.events.map((event, i) => {
          const action = RIDER_ACTIONS[event.action]
          return (
            <li key={i} className='relative text-xs'>
              <span className={cn('absolute top-1 -start-[21px] size-2.5 rounded-full ring-2 ring-background', action.dot)} />
              <div className='flex flex-wrap items-baseline gap-x-2'>
                <span className='font-medium'>{action.label}</span>
                <span className='text-muted-foreground tabular-nums'>{when(event.occurred_at)}</span>
              </div>
              {event.action === 'attempt_failed' && <p className='text-muted-foreground'>{event.reason ?? 'No reason given'}</p>}
              {event.action === 'delivered' && event.cod_amount !== null && event.cod_amount > 0 && (
                <p className='text-muted-foreground'>
                  Collected {sar(event.cod_amount)}
                  {event.payment_method && ` by ${PAYMENT[event.payment_method] ?? event.payment_method}`}
                </p>
              )}
              {event.note && <p className='mt-0.5 rounded bg-muted/60 px-2 py-1 text-muted-foreground'>&ldquo;{event.note}&rdquo;</p>}
            </li>
          )
        })}

        <Ending parcel={parcel} />
      </ol>
    </li>
  )
}

/** How the parcel left the rider's hands, when it did. */
function Ending({ parcel }: { parcel: RiderParcel }) {
  if (parcel.outcome === 'cancelled' || parcel.outcome === 'returned') {
    const Icon = parcel.outcome === 'cancelled' ? Ban : Undo2
    return (
      <li className='relative text-xs'>
        <span className='absolute top-0.5 -start-[23px] grid size-3.5 place-items-center rounded-full bg-background'>
          <Icon className='size-3 text-muted-foreground' />
        </span>
        <div className='flex flex-wrap items-baseline gap-x-2'>
          <span className='font-medium'>{parcel.outcome === 'cancelled' ? 'Cancelled' : 'Returned'}</span>
          <span className='text-muted-foreground tabular-nums'>{when(parcel.cancelled_at)}</span>
        </div>
        {parcel.cancel_reason && <p className='text-muted-foreground'>{parcel.cancel_reason}</p>}
      </li>
    )
  }

  if (parcel.outcome === 'handed_back') {
    return (
      <li className='relative text-xs'>
        <span className='absolute top-0.5 -start-[23px] grid size-3.5 place-items-center rounded-full bg-background'>
          <ArrowRightLeft className='size-3 text-muted-foreground' />
        </span>
        <span className='font-medium'>{parcel.held_by ? `Taken over by ${parcel.held_by}` : 'Unassigned by an admin'}</span>
        <span className='text-muted-foreground'> · {parcel.status_label}</span>
      </li>
    )
  }

  return null
}

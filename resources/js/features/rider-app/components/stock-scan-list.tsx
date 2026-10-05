import { AlertCircle, CheckCircle2, Loader2, RotateCw } from 'lucide-react'
import { statusText, useI18n, type TFunction } from '../i18n'
import type { StockDirection, StockSessionItem } from '../types'

/** What this go has counted so far, and what it couldn't. */
export function stockTotals(items: StockSessionItem[], direction: StockDirection) {
  const counted = items.filter((item) => item.state === direction)

  return {
    parcels: counted.length,
    pieces: counted.reduce((sum, item) => sum + (item.scan?.pieces ?? 0), 0),
    problems: items.filter((item) => item.state !== direction && item.state !== 'working').length,
  }
}

/** Why a parcel wasn't counted. */
export function problemText(t: TFunction, item: StockSessionItem): string {
  switch (item.state) {
    case 'already_out':
      return t('stock_already_out')
    case 'already_in':
      return t('stock_already_in')
    case 'finished':
      return t('stock_finished', { status: item.parcel ? statusText(t, item.parcel.status) : '' })
    case 'not_found':
      return t('claim_not_found')
    case 'error':
      return t('claim_error')
    default:
      return ''
  }
}

export function StockStateIcon({ item, direction, className }: { item: StockSessionItem; direction: StockDirection; className?: string }) {
  const size = className ?? 'h-5 w-5'

  if (item.state === 'working') return <Loader2 className={`${size} shrink-0 animate-spin text-muted-foreground`} />
  if (item.state === direction) return <CheckCircle2 className={`${size} shrink-0 text-green-600 dark:text-green-400`} />
  if (item.state === 'error') return <RotateCw className={`${size} shrink-0 text-red-600 dark:text-red-400`} />
  return <AlertCircle className={`${size} shrink-0 text-red-600 dark:text-red-400`} />
}

type Props = {
  direction: StockDirection
  items: StockSessionItem[]
  onRetry: (code: string) => void
}

/** The parcels scanned OUT or IN in this go, newest first. */
export function StockScanList({ direction, items, onRetry }: Props) {
  const { t } = useI18n()

  if (items.length === 0) {
    return <p className='px-1 py-6 text-center text-[15px] leading-snug text-muted-foreground'>{t(direction === 'out' ? 'stock_scan_hint_out' : 'stock_scan_hint_in')}</p>
  }

  return (
    <ul className='space-y-2'>
      {items.map((item) => {
        const parcel = item.scan?.parcel ?? item.parcel
        const ok = item.state === direction

        return (
          <li key={item.code}>
            <button
              type='button'
              disabled={item.state !== 'error'}
              onClick={() => onRetry(item.code)}
              className='flex w-full items-center gap-3 rounded-2xl bg-canvas px-3.5 py-3 text-start'
            >
              <StockStateIcon item={item} direction={direction} />
              <span className='min-w-0 flex-1'>
                <span className='block truncate font-mono text-[15px] font-semibold' dir='ltr'>
                  {parcel?.tracking_number ?? item.code}
                </span>
                {ok || item.state === 'working' ? (
                  parcel && (
                    <span className='block truncate text-[13px] text-muted-foreground'>
                      {[parcel.order_number, parcel.courier_label, parcel.receiver_name].filter(Boolean).join(' · ')}
                    </span>
                  )
                ) : (
                  // Not counted: why, under the number, where there's room to say it.
                  <span className='block text-[13px] font-semibold leading-snug text-red-600 dark:text-red-400'>{problemText(t, item)}</span>
                )}
              </span>
              {ok && (
                <span className='shrink-0 text-end text-[13px] font-semibold'>
                  <span className='block text-green-700 dark:text-green-400'>{t('pieces', { n: item.scan?.pieces ?? 0 })}</span>
                  {/* Linked to no product: the parcel went through, its stock didn't change. */}
                  {!!item.scan?.unmatched && (
                    <span className='block text-amber-700 dark:text-amber-400'>{t('stock_unmatched', { n: item.scan.unmatched })}</span>
                  )}
                </span>
              )}
            </button>
          </li>
        )
      })}
    </ul>
  )
}

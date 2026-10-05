import { useEffect, useState } from 'react'
import { ArrowDownToLine, ArrowLeft, ArrowUpFromLine, Check, ChevronRight, Ellipsis, Flashlight, FlashlightOff, Keyboard, ScanLine } from 'lucide-react'
import { SheetDescription, SheetTitle } from '@/components/ui/sheet'
import { cn } from '@/lib/utils'
import { useI18n } from '../i18n'
import { useBackToClose } from '../lib/back-button'
import { normalizeScannedCode } from '../lib/barcode'
import { cssColor, useStatusBarColor } from '../lib/status-bar'
import { useBarcodeCamera } from '../lib/use-barcode-camera'
import type { EntryMethod, StockDirection, StockSessionItem } from '../types'
import { BottomSheet } from './bottom-sheet'
import { problemText, StockScanList, StockStateIcon, stockTotals } from './stock-scan-list'

/** OUT is the brand's orange, IN is green — on the frame, the corner and the buttons that are on. */
export const STOCK_COLORS: Record<StockDirection, string> = { out: 'var(--brand)', in: '#16a34a' }

export const STOCK_ICONS = { out: ArrowUpFromLine, in: ArrowDownToLine } as const

type Props = {
  direction: StockDirection
  /** This go's scans, newest first. */
  items: StockSessionItem[]
  /** The frame flashes green or red with the last scan's result. */
  flash: 'ok' | 'error' | null
  onDetected: (code: string, entry: EntryMethod) => void
  /** Send again a scan that failed on the network. */
  onRetry: (code: string) => void
  onClose: () => void
}

/**
 * The inventory manager's scan screen: the camera in a frame on a plain
 * page, what the last scan did under it, and the controls at the bottom —
 * type a number, the light, and the big button that ends the go. It keeps
 * reading until then; the dots at the top list everything scanned so far.
 */
export function StockScanner({ direction, items, flash, onDetected, onRetry, onClose }: Props) {
  const { t } = useI18n()
  const [typing, setTyping] = useState(false)
  const [typed, setTyped] = useState('')
  const [listOpen, setListOpen] = useState(false)

  // The camera stays on under the list, but doesn't read while it's open.
  const { videoRef, camera, torchAvailable, torchOn, toggleTorch, handled } = useBarcodeCamera(listOpen, (code) => onDetected(code, 'camera'))

  // No camera to read: the number is typed instead.
  useEffect(() => {
    if (camera === 'denied' || camera === 'unavailable') setTyping(true)
  }, [camera])

  useBackToClose(listOpen, () => setListOpen(false))
  useStatusBarColor(cssColor('--surface'))

  const submitTyped = (event: React.FormEvent) => {
    event.preventDefault()
    const code = normalizeScannedCode(typed)
    if (!code) return
    handled(code)
    setTyped('')
    onDetected(code, 'manual')
  }

  const color = STOCK_COLORS[direction]
  const frame = flash === 'ok' ? '#16a34a' : flash === 'error' ? '#dc2626' : color
  const Icon = STOCK_ICONS[direction]
  const totals = stockTotals(items, direction)
  const summary = t(direction === 'out' ? 'stock_count_out' : 'stock_count_in', { n: totals.parcels, p: totals.pieces })
  const last = items[0]

  return (
    <div className='fixed inset-0 z-40 flex flex-col bg-surface'>
      {/* Back · which way these scans count · everything scanned so far */}
      <header className='flex items-center justify-between px-4 pt-[calc(env(safe-area-inset-top)+12px)]'>
        <button type='button' onClick={onClose} aria-label={t('close')} className='flex h-11 w-11 items-center justify-center rounded-full active:bg-foreground/5'>
          <ArrowLeft className='h-6 w-6 rtl:rotate-180' />
        </button>

        <h1 className='flex items-center gap-2 text-[18px] font-bold tracking-wide rtl:tracking-normal'>
          <span className='flex h-7 w-7 items-center justify-center rounded-[9px] text-white' style={{ background: color }}>
            <Icon className='h-4 w-4' strokeWidth={2.5} />
          </span>
          {t(direction === 'out' ? 'stock_out' : 'stock_in')}
        </h1>

        <button
          type='button'
          onClick={() => setListOpen(true)}
          aria-label={t('stock_scan_list')}
          className='relative flex h-11 w-11 items-center justify-center rounded-full active:bg-foreground/5'
        >
          <Ellipsis className='h-6 w-6' />
          {items.length > 0 && (
            <span
              className='absolute -end-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[11px] font-bold text-white'
              style={{ background: totals.problems > 0 ? '#dc2626' : color }}
              dir='ltr'
            >
              {items.length}
            </span>
          )}
        </button>
      </header>

      {/* The camera, inside its frame */}
      <div className='min-h-0 flex-1 px-6 pt-5'>
        <div className='relative mx-auto h-full max-w-md p-[18px]'>
          <span
            aria-hidden
            className='pointer-events-none absolute inset-y-0 inset-s-0 w-16 rounded-s-[40px] border-y-[3px] border-s-[3px] transition-colors'
            style={{ borderColor: frame }}
          />
          <span
            aria-hidden
            className='pointer-events-none absolute inset-y-0 inset-e-0 w-16 rounded-e-[40px] border-y-[3px] border-e-[3px] transition-colors'
            style={{ borderColor: frame }}
          />

          <div className='relative h-full overflow-hidden rounded-[28px] bg-black'>
            <video ref={videoRef} className='absolute inset-0 h-full w-full object-cover' playsInline muted autoPlay />

            {camera === 'running' && <span aria-hidden className='scan-sweep absolute inset-x-0 h-0.5 bg-white/90 shadow-[0_0_14px_2px_rgb(255_255_255/0.6)]' />}

            {/* The last scan's result, washed over the picture for a moment. */}
            <span
              aria-hidden
              className={cn(
                'pointer-events-none absolute inset-0 transition-colors',
                flash === 'ok' ? 'bg-green-500/30' : flash === 'error' ? 'bg-red-500/30' : 'bg-transparent'
              )}
            />

            {camera !== 'running' && (
              <p className='absolute inset-0 flex items-center justify-center px-6 text-center text-[15px] leading-snug text-white/85'>
                {t(camera === 'starting' ? 'camera_starting' : camera === 'denied' ? 'camera_denied' : 'camera_unavailable')}
              </p>
            )}
          </div>
        </div>
      </div>

      {/* What to do, or what the last scan did */}
      <div className='px-6 pt-4'>
        {typing && (
          <form onSubmit={submitTyped} className='mx-auto mb-3 flex max-w-md gap-2 rounded-full bg-canvas p-1.5'>
            <input
              autoFocus
              value={typed}
              onChange={(e) => setTyped(e.target.value)}
              placeholder={t('tracking_or_order')}
              autoCapitalize='characters'
              autoCorrect='off'
              spellCheck={false}
              enterKeyHint='search'
              className='h-11 min-w-0 flex-1 rounded-full border-0 bg-transparent px-4 font-mono outline-none placeholder:text-muted-foreground/70'
            />
            <button
              type='submit'
              disabled={!typed.trim()}
              className='h-11 rounded-full px-5 text-[15px] font-semibold text-white transition-transform active:scale-95 disabled:opacity-50'
              style={{ background: color }}
            >
              {t('find')}
            </button>
          </form>
        )}

        <div className='mx-auto flex min-h-[76px] max-w-md flex-col items-center justify-center text-center'>
          {last ? (
            <LastScan item={last} direction={direction} onRetry={onRetry} />
          ) : (
            <p className='max-w-[17rem] text-[16px] leading-snug text-muted-foreground'>{t('stock_scan_place')}</p>
          )}

          {items.length > 0 && (
            <button type='button' onClick={() => setListOpen(true)} className='mt-1.5 flex items-center gap-1 rounded-full px-3 py-1 text-[13px] font-semibold active:bg-foreground/5'>
              <span>{summary}</span>
              {totals.problems > 0 && <span className='text-red-600 dark:text-red-400'>· {t('stock_problems', { n: totals.problems })}</span>}
              <ChevronRight className='h-4 w-4 text-muted-foreground rtl:rotate-180' />
            </button>
          )}
        </div>
      </div>

      {/* Type a number · the light · and, in the corner, done */}
      <div className='relative h-[180px] shrink-0 pb-[env(safe-area-inset-bottom)]'>
        <div className='absolute inset-s-6 top-1/2 flex -translate-y-1/2 gap-4'>
          <RoundButton label={t('type_number')} on={typing} color={color} onClick={() => setTyping((open) => !open)}>
            <Keyboard className='h-[22px] w-[22px]' />
          </RoundButton>
          <RoundButton label={t('light')} on={torchOn} color={color} disabled={!torchAvailable} onClick={toggleTorch}>
            {torchOn ? <Flashlight className='h-[22px] w-[22px]' /> : <FlashlightOff className='h-[22px] w-[22px]' />}
          </RoundButton>
        </div>

        {/* The corner the big button sits in: a drop of colour off the screen's edge. */}
        <div className='absolute inset-e-0 top-0 h-[180px] w-[140px] rtl:-scale-x-100'>
          <svg aria-hidden viewBox='0 0 140 180' className='h-full w-full'>
            <path d='M140 4C140 38 108 54.2 89 43.2A54 54 0 1 0 89 136.8C108 125.8 140 142 140 176Z' fill={frame} className='transition-colors' />
          </svg>
          <button
            type='button'
            onClick={onClose}
            aria-label={t('batch_done')}
            className='absolute left-5 top-12 flex h-[84px] w-[84px] items-center justify-center rounded-full bg-surface shadow-[0_8px_24px_-8px_rgb(0_0_0/0.35)] transition-transform active:scale-95'
          >
            {totals.parcels > 0 ? <Check className='h-9 w-9 rtl:-scale-x-100' strokeWidth={2.5} /> : <ScanLine className='h-9 w-9' strokeWidth={2} />}
          </button>
        </div>
      </div>

      <BottomSheet open={listOpen} onClose={() => setListOpen(false)} className='border-0 bg-surface px-5 pb-[calc(env(safe-area-inset-bottom)+16px)]'>
        <div className='mx-auto mt-2.5 h-1.5 w-10 rounded-full bg-foreground/15' />
        <SheetTitle className='mt-5 text-[20px] font-bold'>{t('stock_scan_list')}</SheetTitle>
        <SheetDescription className='mt-0.5 text-[14px] text-muted-foreground'>
          {summary}
          {totals.problems > 0 && <span className='font-semibold text-red-600 dark:text-red-400'> · {t('stock_problems', { n: totals.problems })}</span>}
        </SheetDescription>

        <div className='mt-4 max-h-[55dvh] overflow-y-auto overscroll-contain'>
          <StockScanList direction={direction} items={items} onRetry={onRetry} />
        </div>

        <button
          type='button'
          onClick={onClose}
          className='mt-4 h-14 w-full rounded-full text-[16px] font-semibold text-white transition-transform active:scale-[0.98]'
          style={{ background: color }}
        >
          {t('batch_done')}
        </button>
      </BottomSheet>
    </div>
  )
}

/** The newest scan: counted (and how many pieces), or why not. Tap to send it again if the network dropped it. */
function LastScan({ item, direction, onRetry }: { item: StockSessionItem; direction: StockDirection; onRetry: (code: string) => void }) {
  const { t } = useI18n()
  const parcel = item.scan?.parcel ?? item.parcel
  const ok = item.state === direction
  const working = item.state === 'working'

  return (
    <button type='button' disabled={item.state !== 'error'} onClick={() => onRetry(item.code)} className='flex max-w-full flex-col items-center'>
      <span className='flex max-w-full items-center gap-2'>
        <StockStateIcon item={item} direction={direction} className='h-6 w-6' />
        <span className='truncate font-mono text-[17px] font-bold' dir='ltr'>
          {parcel?.tracking_number ?? item.code}
        </span>
        {ok && <span className='shrink-0 text-[15px] font-semibold text-green-700 dark:text-green-400'>{t('pieces', { n: item.scan?.pieces ?? 0 })}</span>}
      </span>

      {ok || working ? (
        <span className='mt-0.5 max-w-full truncate text-[14px] text-muted-foreground'>
          {[parcel?.order_number, parcel?.courier_label, parcel?.receiver_name].filter(Boolean).join(' · ')}
          {/* Linked to no product: the parcel went through, its stock didn't change. */}
          {ok && !!item.scan?.unmatched && (
            <span className='font-semibold text-amber-700 dark:text-amber-400'> · {t('stock_unmatched', { n: item.scan.unmatched })}</span>
          )}
        </span>
      ) : (
        <span className='mt-0.5 text-[15px] font-semibold leading-snug text-red-600 dark:text-red-400'>{problemText(t, item)}</span>
      )}
    </button>
  )
}

/** One of the small round controls. Filled with the direction's colour while it's on. */
function RoundButton({
  label,
  on,
  color,
  disabled,
  onClick,
  children,
}: {
  label: string
  on: boolean
  color: string
  disabled?: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <button
      type='button'
      onClick={onClick}
      disabled={disabled}
      aria-label={label}
      aria-pressed={on}
      className={cn(
        'flex h-14 w-14 items-center justify-center rounded-full shadow-[0_10px_24px_-10px_rgb(0_0_0/0.35)] transition-transform active:scale-95 disabled:opacity-40',
        on ? 'text-white' : 'bg-canvas'
      )}
      style={on ? { background: color } : undefined}
    >
      {children}
    </button>
  )
}

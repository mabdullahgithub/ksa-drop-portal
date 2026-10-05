import { useCallback, useEffect, useRef, useState } from 'react'
import { Loader2 } from 'lucide-react'
import { toast } from 'sonner'
import { EmptyState } from '@/components/empty-state'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { cn } from '@/lib/utils'
import { api, ApiError } from '../api'
import { BottomNav, type Tab } from '../components/bottom-nav'
import { FilterSheet } from '../components/filter-sheet'
import { HomeHeader } from '../components/home-header'
import { STOCK_COLORS as COLORS, STOCK_ICONS as ICONS, StockScanner } from '../components/stock-scanner'
import { reasonText, useI18n, type Lang } from '../i18n'
import { useBackToClose, useExitGuard } from '../lib/back-button'
import { platform, uuid, vibrate } from '../lib/device'
import { usePresence } from '../lib/presence'
import { PullIndicator, usePullToRefresh } from '../lib/pull-to-refresh'
import { cssColor, useStatusBarColor } from '../lib/status-bar'
import { reloadForNewBuild } from '../lib/update'
import type { EntryMethod, Me, StockDirection, StockHome, StockScan, StockScanResponse, StockScansPage, StockSessionItem } from '../types'
import { ProfileView } from './profile-view'

/** Which scans the log shows. */
type LogFilter = 'all' | StockDirection

const LOG_FILTERS: LogFilter[] = ['all', 'out', 'in']

/**
 * The inventory manager's app, laid out like the rider's: Home and Profile.
 * Home keeps its header, the search bar and the two things the app does —
 * OUT and IN — at the top; the log of scans runs underneath, loading older
 * ones as it's scrolled. OUT scans parcels as they leave the warehouse, IN
 * as they come back, and each scan moves the stock of what is inside
 * (POST /rider/api/stock/scan).
 */
export function StockShell() {
  const { t, lang } = useI18n()
  usePresence()

  const [tab, setTab] = useState<Tab>('home')
  const [me, setMe] = useState<Me | null>(null)
  const [today, setToday] = useState<StockHome['today'] | null>(null)
  const [error, setError] = useState<string | null>(null)

  // The log: searched and filtered on the server, a page at a time.
  const [query, setQuery] = useState('')
  const search = useDebouncedValue(query.trim(), 350)
  const [filter, setFilter] = useState<LogFilter>('all')
  const [filtering, setFiltering] = useState(false)
  const [scans, setScans] = useState<StockScan[] | null>(null)
  const [nextPage, setNextPage] = useState<number | null>(null)
  const [loadingMore, setLoadingMore] = useState(false)
  const listRequest = useRef(0)
  const sentinel = useRef<HTMLDivElement>(null)

  // Which way the camera is scanning; null while it's closed.
  const [direction, setDirection] = useState<StockDirection | null>(null)
  const [items, setItems] = useState<StockSessionItem[]>([])
  const [flash, setFlash] = useState<'ok' | 'error' | null>(null)
  const flashTimer = useRef<number | undefined>(undefined)
  const seen = useRef(new Set<string>())
  const entries = useRef(new Map<string, EntryMethod>())
  // Only a retry of a scan that failed on the network reuses its id.
  const retryIds = useRef(new Map<string, string>())
  // Parcels counted in this go, and the code each was scanned by.
  const counted = useRef(new Map<number, string>())

  const load = useCallback(async () => {
    try {
      const [meResult, home] = await Promise.all([api.get<Me>('/rider/api/me'), api.get<StockHome>('/rider/api/stock')])
      setMe(meResult)
      setToday(home.today)
      setError(null)
    } catch (e) {
      setError(reasonText(t, (e as ApiError).code) ?? t('something_wrong'))
    }
  }, [t])

  const loadScans = useCallback(
    async (page: number) => {
      // The first page starts the list over: an older page still on its way belongs to the list before.
      const request = page === 1 ? ++listRequest.current : listRequest.current
      setLoadingMore(page > 1)

      const params = new URLSearchParams({ page: String(page) })
      if (search) params.set('search', search)
      if (filter !== 'all') params.set('direction', filter)

      try {
        const data = await api.get<StockScansPage>(`/rider/api/stock/scans?${params}`)
        if (request !== listRequest.current) return

        setScans((list) => {
          if (page === 1 || !list) return data.scans
          const have = new Set(list.map((scan) => scan.id))
          return [...list, ...data.scans.filter((scan) => !have.has(scan.id))]
        })
        setNextPage(data.next_page)
        setError(null)
      } catch (e) {
        if (request !== listRequest.current) return
        // Stop asking for more: Try again starts the list over.
        setNextPage(null)
        setError(reasonText(t, (e as ApiError).code) ?? t('something_wrong'))
      } finally {
        if (request === listRequest.current) setLoadingMore(false)
      }
    },
    [search, filter, t]
  )

  const refresh = useCallback(() => Promise.all([load(), loadScans(1)]), [load, loadScans])

  useEffect(() => {
    load()
  }, [load])

  // A new search or filter is a new list.
  useEffect(() => {
    setScans(null)
    setNextPage(null)
    loadScans(1)
  }, [loadScans])

  // The next page, as the end of the list comes into view.
  useEffect(() => {
    const end = sentinel.current
    if (!end || nextPage === null || loadingMore) return

    const observer = new IntersectionObserver((seenEntries) => seenEntries[0].isIntersecting && loadScans(nextPage), { rootMargin: '400px' })
    observer.observe(end)
    return () => observer.disconnect()
  }, [nextPage, loadingMore, loadScans, scans])

  // Back from another app: refresh.
  useEffect(() => {
    const onVisible = () => document.visibilityState === 'visible' && refresh()
    document.addEventListener('visibilitychange', onVisible)
    return () => document.removeEventListener('visibilitychange', onVisible)
  }, [refresh])

  const showFlash = (kind: 'ok' | 'error') => {
    setFlash(kind)
    window.clearTimeout(flashTimer.current)
    flashTimer.current = window.setTimeout(() => setFlash(null), 700)
    vibrate(kind === 'ok' ? 70 : [60, 80, 60])
  }

  const send = async (way: StockDirection, code: string, entry: EntryMethod) => {
    const clientUuid = retryIds.current.get(code) ?? uuid()
    retryIds.current.set(code, clientUuid)

    let item: StockSessionItem
    try {
      const response = await api.post<StockScanResponse>('/rider/api/stock/scan', {
        direction: way,
        code,
        client_uuid: clientUuid,
        entry_method: entry,
        occurred_at: new Date().toISOString(),
      })
      retryIds.current.delete(code)
      item = { code, state: response.result, scan: response.scan, parcel: response.scan?.parcel ?? response.parcel }
    } catch (error) {
      if ((error as ApiError).status === 404) {
        retryIds.current.delete(code)
        item = { code, state: 'not_found' }
      } else {
        item = { code, state: 'error' }
      }
    }

    const parcelId = item.parcel?.id

    if (item.state === way && parcelId != null) {
      counted.current.set(parcelId, code)
    }

    // A label carries the parcel's number more than once (waybill barcode,
    // order barcode). The camera reading the second one right after the
    // first isn't a problem to show: the parcel is already counted here.
    if (item.state === `already_${way}` && parcelId != null && counted.current.has(parcelId) && counted.current.get(parcelId) !== code) {
      setItems((list) => list.filter((other) => other.code !== code))
      return
    }

    setItems((list) => list.map((other) => (other.code === code ? item : other)))
    showFlash(item.state === way ? 'ok' : 'error')
  }

  // A toast still up from the last go would cover the top of the scan screen.
  const openScanner = (way: StockDirection) => {
    toast.dismiss()
    setDirection(way)
  }

  const onDetected = async (code: string, entry: EntryMethod) => {
    if (!direction) return

    const key = code.toUpperCase()
    if (seen.current.has(key)) return
    seen.current.add(key)
    entries.current.set(code, entry)

    setItems((list) => [{ code, state: 'working' }, ...list])
    await send(direction, code, entry)
  }

  const retry = (code: string) => {
    if (!direction) return
    setItems((list) => list.map((other) => (other.code === code ? { code, state: 'working' } : other)))
    send(direction, code, entries.current.get(code) ?? 'camera')
  }

  const closeScanner = () => {
    const done = items.filter((item) => item.state === direction).length
    if (direction && done > 0) toast.success(t(direction === 'out' ? 'stock_summary_out' : 'stock_summary_in', { n: done }))

    setDirection(null)
    setItems([])
    seen.current.clear()
    entries.current.clear()
    retryIds.current.clear()
    counted.current.clear()
    refresh()
  }

  // Back with nothing open would close the app (Android): ask first.
  useExitGuard(platform() === 'android', () => toast(t('exit_confirm'), { id: 'exit-confirm', duration: 2500 }))
  useBackToClose(direction !== null, closeScanner)
  useBackToClose(filtering, () => setFiltering(false))

  // After a deploy this phone is still running the old build. Reload into
  // the new one — from Home with the camera closed, so no scan is lost.
  const liveBuild = me?.build
  useEffect(() => {
    if (tab === 'home' && direction === null) reloadForNewBuild(liveBuild)
  }, [liveBuild, tab, direction])

  // Pull down on Home or Profile to reload; not while the camera is open.
  const pull = usePullToRefresh(refresh, direction === null)

  useEffect(() => {
    window.scrollTo(0, 0)
  }, [tab])

  // Home's orange header runs up under the status bar.
  useStatusBarColor(tab === 'home' && direction === null ? cssColor('--brand') : null)

  // A hairline under the frosted top once the log is scrolled under it.
  const [scrolled, setScrolled] = useState(false)
  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 4)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  const narrowed = search !== '' || filter !== 'all'

  return (
    <div className='min-h-dvh'>
      <PullIndicator {...pull} />

      {tab === 'home' ? (
        <>
          {/* Header, search, and OUT and IN stay at the top; the log scrolls
              up under them, frosted. */}
          <div className='sticky top-0 z-20'>
            <div
              aria-hidden
              className={cn(
                'absolute inset-0 -z-10 bg-canvas/20 backdrop-blur-[10px] backdrop-saturate-150 transition-shadow',
                scrolled && 'shadow-[0_1px_0_rgb(0_0_0/0.06)] dark:shadow-[0_1px_0_rgb(255_255_255/0.06)]'
              )}
            />
            <HomeHeader
              me={me}
              onProfile={() => setTab('profile')}
              query={query}
              onQuery={setQuery}
              filterActive={filter !== 'all'}
              onFilter={() => setFiltering(true)}
              headline={t('stock_headline')}
              searchPlaceholder={t('stock_search_placeholder')}
            />

            <section className='px-4 pb-3 pt-5'>
              <p className='px-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground'>{t('today')}</p>
              <div className='mt-1.5 grid grid-cols-2 gap-2'>
                <DirectionTile direction='out' parcels={today?.out.parcels} onClick={() => openScanner('out')} />
                <DirectionTile direction='in' parcels={today?.in.parcels} onClick={() => openScanner('in')} />
              </div>
            </section>
          </div>

          <FilterSheet
            open={filtering}
            onClose={() => setFiltering(false)}
            title={t('stock_filter_title')}
            value={filter}
            onChange={setFilter}
            options={LOG_FILTERS.map((value) => ({
              value,
              label: value === 'all' ? t('stock_filter_all') : t(value === 'out' ? 'stock_out' : 'stock_in'),
            }))}
          />

          <main className='px-4 pb-[calc(env(safe-area-inset-bottom)+96px)] pt-1'>
            {error && (
              <div className='glass-tint rounded-2xl p-3.5 text-sm' style={{ '--tint': '#dc2626' } as React.CSSProperties}>
                {error}
                <button type='button' onClick={refresh} className='ms-2 font-semibold underline'>
                  {t('retry')}
                </button>
              </div>
            )}

            <ScanLog scans={scans} error={error} lang={lang} empty={narrowed ? t('no_match') : t('stock_none')} matching={narrowed} />

            {/* Coming into view asks for the next page. */}
            <div ref={sentinel} aria-hidden className='h-px' />
            {loadingMore && (
              <div className='flex justify-center py-4'>
                <Loader2 className='h-5 w-5 animate-spin text-muted-foreground' />
              </div>
            )}
          </main>
        </>
      ) : (
        <ProfileView me={me} onPhotoChanged={(photoUrl) => setMe((m) => (m ? { ...m, rider: { ...m.rider, photo_url: photoUrl } } : m))} />
      )}

      <BottomNav tab={tab} onTab={setTab} />

      {direction && (
        <StockScanner direction={direction} items={items} flash={flash} onDetected={onDetected} onRetry={retry} onClose={closeScanner} />
      )}
    </div>
  )
}

/** OUT or IN: tap to scan that way. Under it, how many parcels went that way today. */
function DirectionTile({ direction, parcels, onClick }: { direction: StockDirection; parcels: number | undefined; onClick: () => void }) {
  const { t } = useI18n()
  const Icon = ICONS[direction]
  const out = direction === 'out'

  return (
    <button
      type='button'
      onClick={onClick}
      aria-label={`${t(out ? 'stock_out' : 'stock_in')} — ${t(out ? 'stock_out_hint' : 'stock_in_hint')}`}
      className='glass glass-press flex items-center gap-3 rounded-2xl px-3 py-2.5 text-start'
    >
      <span className='flex h-12 w-12 shrink-0 items-center justify-center rounded-[15px] text-white' style={{ background: COLORS[direction] }}>
        <Icon className='h-6 w-6' strokeWidth={2.5} />
      </span>
      <span className='min-w-0'>
        <span className='block text-[21px] font-extrabold leading-tight tracking-tight rtl:tracking-normal'>{t(out ? 'stock_out' : 'stock_in')}</span>
        <span className='block truncate text-[12px] leading-tight text-muted-foreground'>{t('stock_today_n', { n: parcels ?? '–' })}</span>
      </span>
    </button>
  )
}

/** The day in Riyadh, for telling today's scans from older ones. */
const riyadhDay = (date: Date) => date.toLocaleDateString('en-CA', { timeZone: 'Asia/Riyadh' })

/** The manager's own scans, newest first: to look at, not to act on. */
function ScanLog({
  scans,
  error,
  lang,
  empty,
  matching,
}: {
  scans: StockScan[] | null
  error: string | null
  lang: Lang
  empty: string
  /** Empty because of the search or the filter, not because nothing was scanned yet. */
  matching: boolean
}) {
  const { t } = useI18n()
  const today = riyadhDay(new Date())

  // Western digits in both languages, Riyadh time. An older scan says its day too.
  const when = (iso: string) => {
    const at = new Date(iso)
    return at.toLocaleString(lang === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', {
      timeZone: 'Asia/Riyadh',
      hour: '2-digit',
      minute: '2-digit',
      ...(riyadhDay(at) === today ? {} : { day: 'numeric', month: 'short' }),
    })
  }

  if (scans === null) {
    return error ? null : (
      <div className='flex justify-center py-10'>
        <Loader2 className='h-6 w-6 animate-spin text-muted-foreground' />
      </div>
    )
  }

  if (scans.length === 0) {
    return <EmptyState bot='pill' state={matching ? 'default' : 'working'} size='lg' title={empty} className='py-12' />
  }

  return (
    <ul className='mt-3 space-y-2'>
      {scans.map((scan) => {
        const Icon = ICONS[scan.direction]
        const parcel = scan.parcel

        return (
          <li key={scan.id} className='glass-lite flex items-center gap-3 rounded-2xl px-3.5 py-3'>
            <span
              className='flex h-9 w-9 shrink-0 items-center justify-center rounded-[11px] text-white'
              style={{ background: COLORS[scan.direction] }}
            >
              <Icon className='h-[18px] w-[18px]' strokeWidth={2.25} />
            </span>
            <div className='min-w-0 flex-1'>
              <div className='flex items-center gap-2'>
                <p className='truncate font-mono text-[14px] font-semibold' dir='ltr'>
                  {parcel?.tracking_number ?? '—'}
                </p>
                <span className='ms-auto shrink-0 text-xs text-muted-foreground' dir='ltr'>
                  {when(scan.occurred_at)}
                </span>
              </div>
              <p className='mt-0.5 truncate text-[13px] text-muted-foreground'>
                {[parcel?.order_number, parcel?.courier_label, parcel?.receiver_name].filter(Boolean).join(' · ')}
              </p>
              <div className='mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs'>
                <span className={cn('font-semibold', scan.direction === 'out' ? 'text-orange-700 dark:text-orange-300' : 'text-green-700 dark:text-green-400')}>
                  {t(scan.direction === 'out' ? 'stock_out' : 'stock_in')} · {t('pieces', { n: scan.pieces })}
                </span>
                {scan.direction === 'in' && scan.parcel_rider && (
                  <span className='text-muted-foreground'>{t('stock_from_rider', { name: scan.parcel_rider })}</span>
                )}
                {scan.unmatched > 0 && (
                  <span className='font-semibold text-amber-700 dark:text-amber-400'>{t('stock_unmatched', { n: scan.unmatched })}</span>
                )}
              </div>
            </div>
          </li>
        )
      })}
    </ul>
  )
}

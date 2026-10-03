import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Banknote, CheckCircle2, ChevronRight, Loader2, Undo2, Wallet, type LucideIcon } from 'lucide-react'
import { toast } from 'sonner'
import { EmptyState } from '@/components/empty-state'
import { cn } from '@/lib/utils'
import { api, ApiError } from '../api'
import { BatchPanel } from '../components/batch-panel'
import { BottomNav, type Tab } from '../components/bottom-nav'
import { cashLabel, CashSheet, hasPay, payLabel, type MoneySide } from '../components/cash-sheet'
import { DateRangeForm } from '../components/date-range-form'
import { FilterSheet } from '../components/filter-sheet'
import { HomeHeader } from '../components/home-header'
import { addressLine, failedWhy, StatusBadge } from '../components/parcel-parts'
import { Scanner } from '../components/scanner'
import { UpdateSheet, type UpdateRequest } from '../components/update-sheet'
import { money, reasonText, useI18n, type Lang } from '../i18n'
import { useBackToClose, useExitGuard } from '../lib/back-button'
import { periodDates } from '../lib/days'
import { platform, uuid, vibrate } from '../lib/device'
import { usePresence } from '../lib/presence'
import { PullIndicator, usePullToRefresh } from '../lib/pull-to-refresh'
import { cssColor, useStatusBarColor } from '../lib/status-bar'
import { reloadForNewBuild } from '../lib/update'
import type {
  BatchItem,
  ClaimResponse,
  EntryMethod,
  HistoryDates,
  HistoryEvent,
  HistoryRange,
  HistoryResponse,
  Me,
  Parcel,
  RiderCash,
  RiderPay,
  ScanMode,
} from '../types'
import { ProfileView, supportLink } from './profile-view'

type List = 'with_me' | 'delivered'

const MODE_KEY = 'rider_scan_mode'

function savedMode(): ScanMode {
  try {
    return localStorage.getItem(MODE_KEY) === 'batch' ? 'batch' : 'single'
  } catch {
    return 'single'
  }
}

/**
 * The signed-in app: Home (parcels with me / delivered) and Profile, with
 * Scan in the bottom bar. Scanner, update sheet and the cash sheet open over
 * either tab.
 */
export function AppShell() {
  const { t } = useI18n()
  usePresence()

  const [tab, setTab] = useState<Tab>('home')
  const [list, setList] = useState<List>('with_me')
  const [range, setRange] = useState<HistoryRange>('today')
  // The rider's own dates, shown while the range is 'custom'.
  const [dates, setDates] = useState<HistoryDates | null>(null)

  const [me, setMe] = useState<Me | null>(null)
  const [parcels, setParcels] = useState<Parcel[] | null>(null)
  const [delivered, setDelivered] = useState<HistoryResponse | null>(null)
  const deliveredRequest = useRef(0)
  const [error, setError] = useState<string | null>(null)

  const [scanning, setScanning] = useState(false)
  const [request, setRequest] = useState<UpdateRequest | null>(null)
  const [cashOpen, setCashOpen] = useState(false)
  // The one account the cash sheet shows: the tile tapped on Home. Both from Profile.
  const [cashSide, setCashSide] = useState<MoneySide>()

  const openCash = (side?: MoneySide) => {
    setCashSide(side)
    setCashOpen(true)
  }

  // Scanning picks parcels up (POST /rider/api/claim): one by one opens the
  // update sheet after each; batch keeps the camera going and lists them.
  const [mode, setModeState] = useState<ScanMode>(savedMode)
  const [claiming, setClaiming] = useState(false)
  const [flash, setFlash] = useState<'ok' | 'error' | null>(null)
  const [batch, setBatch] = useState<BatchItem[]>([])
  const flashTimer = useRef<number | undefined>(undefined)
  const batchSeen = useRef(new Set<string>())
  const batchEntry = useRef(new Map<string, EntryMethod>())
  // Only a retry of a scan that failed on the network reuses its id.
  const retryIds = useRef(new Map<string, string>())

  const setMode = (next: ScanMode) => {
    setModeState(next)
    try {
      localStorage.setItem(MODE_KEY, next)
    } catch {
      // Not remembered; fine.
    }
  }

  const load = useCallback(async () => {
    try {
      const [meResult, parcelResult] = await Promise.all([
        api.get<Me>('/rider/api/me'),
        api.get<{ parcels: Parcel[] }>('/rider/api/parcels'),
      ])
      setMe(meResult)
      setParcels(parcelResult.parcels)
      setError(null)
    } catch (e) {
      setError(reasonText(t, (e as ApiError).code) ?? t('something_wrong'))
    }
  }, [t])

  const loadDelivered = useCallback(async (which: HistoryRange, between: HistoryDates | null) => {
    const request = ++deliveredRequest.current
    const period = which === 'custom' && between ? `from=${between.from}&to=${between.to}` : `range=${which}`
    try {
      const response = await api.get<HistoryResponse>(`/rider/api/history?action=delivered&${period}`)
      // A slow answer for a period the rider has since left must not replace the one on screen.
      if (request === deliveredRequest.current) setDelivered(response)
    } catch (e) {
      if (request === deliveredRequest.current) setError(reasonText(t, (e as ApiError).code) ?? t('something_wrong'))
    }
  }, [t])

  const refresh = useCallback(
    () => Promise.all([load(), list === 'delivered' ? loadDelivered(range, dates) : undefined]),
    [load, loadDelivered, list, range, dates]
  )

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    if (list === 'delivered') {
      setDelivered(null)
      loadDelivered(range, dates)
    }
  }, [list, range, dates, loadDelivered])

  // Back from the dialer, maps or WhatsApp: refresh.
  useEffect(() => {
    const onVisible = () => document.visibilityState === 'visible' && refresh()
    document.addEventListener('visibilitychange', onVisible)
    return () => document.removeEventListener('visibilitychange', onVisible)
  }, [refresh])

  const open = (code: string, entry: EntryMethod, preview?: Parcel) => setRequest({ id: Date.now(), code, entry, preview })

  const showFlash = (kind: 'ok' | 'error') => {
    setFlash(kind)
    window.clearTimeout(flashTimer.current)
    flashTimer.current = window.setTimeout(() => setFlash(null), 700)
    vibrate(kind === 'ok' ? 70 : [60, 80, 60])
  }

  const claim = async (code: string, entry: EntryMethod): Promise<ClaimResponse | 'error'> => {
    const clientUuid = retryIds.current.get(code) ?? uuid()
    retryIds.current.set(code, clientUuid)
    try {
      const response = await api.post<ClaimResponse>('/rider/api/claim', {
        code,
        client_uuid: clientUuid,
        entry_method: entry,
        occurred_at: new Date().toISOString(),
      })
      retryIds.current.delete(code)
      return response
    } catch (error) {
      const e = error as ApiError
      if (e.status === 404) {
        retryIds.current.delete(code)
        return { result: 'not_found' }
      }
      return 'error'
    }
  }

  const setBatchItem = (code: string, item: BatchItem) => setBatch((items) => items.map((i) => (i.code === code ? item : i)))

  const claimInBatch = async (code: string, entry: EntryMethod) => {
    setBatchItem(code, { code, state: 'working' })
    const response = await claim(code, entry)
    const ok = response !== 'error' && (response.result === 'claimed' || response.result === 'already_mine')
    setBatchItem(code, response === 'error' ? { code, state: 'error' } : { code, state: response.result, parcel: response.parcel })
    showFlash(ok ? 'ok' : 'error')
  }

  const onDetected = async (code: string, entry: EntryMethod) => {
    if (mode === 'batch') {
      const key = code.toUpperCase()
      if (batchSeen.current.has(key)) return
      batchSeen.current.add(key)
      batchEntry.current.set(code, entry)
      setBatch((items) => [{ code, state: 'working' }, ...items])
      await claimInBatch(code, entry)
      return
    }

    setClaiming(true)
    const response = await claim(code, entry)
    setClaiming(false)

    if (response === 'error') {
      showFlash('error')
      toast.error(t('offline'))
      return
    }
    if (!response.parcel) {
      showFlash('error')
      toast.error(t('not_found', { code }))
      return
    }

    showFlash(response.result === 'claimed' || response.result === 'already_mine' ? 'ok' : 'error')
    if (response.result === 'claimed') {
      toast.success(t('picked_up'))
      load()
    }
    open(response.parcel.tracking_number, entry, response.parcel)
  }

  const closeScanner = () => {
    setScanning(false)
    const picked = batch.filter((item) => item.state === 'claimed').length
    if (picked > 0) toast.success(t('batch_summary', { n: picked }))
    setBatch([])
    batchSeen.current.clear()
    batchEntry.current.clear()
    refresh()
  }

  // Back with nothing open would close the app (Android): ask first.
  useExitGuard(platform() === 'android', () => toast(t('exit_confirm'), { id: 'exit-confirm', duration: 2500 }))
  useBackToClose(scanning, closeScanner)
  useBackToClose(request !== null, () => setRequest(null))
  useBackToClose(cashOpen, () => setCashOpen(false))

  // After a deploy this phone is still running the old build. Reload into
  // the new one — from Home with nothing open, so no update is lost.
  const liveBuild = me?.build
  useEffect(() => {
    if (tab === 'home' && !scanning && request === null && !cashOpen) reloadForNewBuild(liveBuild)
  }, [liveBuild, tab, scanning, request, cashOpen])

  // Pull down on Home or Profile to reload; not while the camera or a parcel is open.
  const pull = usePullToRefresh(refresh, !scanning && request === null)

  useEffect(() => {
    window.scrollTo(0, 0)
  }, [tab])

  // Home's orange header runs up under the status bar.
  useStatusBarColor(tab === 'home' ? cssColor('--brand') : null)

  return (
    <div className='min-h-dvh'>
      <PullIndicator {...pull} />

      {tab === 'home' ? (
        <HomeView
          me={me}
          parcels={parcels}
          delivered={delivered}
          list={list}
          onList={setList}
          range={range}
          onRange={setRange}
          dates={dates}
          onDates={(next) => {
            setDates(next)
            setRange('custom')
          }}
          error={error}
          onRefresh={refresh}
          onOpen={open}
          onProfile={() => setTab('profile')}
          onCash={openCash}
        />
      ) : (
        <ProfileView
          me={me}
          onPhotoChanged={(photoUrl) => setMe((m) => (m ? { ...m, rider: { ...m.rider, photo_url: photoUrl } } : m))}
          onCash={() => openCash()}
        />
      )}

      <BottomNav tab={tab} onTab={setTab} onScan={() => setScanning(true)} held={me?.today.held} />

      {scanning && (
        <Scanner
          paused={request !== null || claiming}
          busy={claiming}
          flash={flash}
          mode={mode}
          onModeChange={setMode}
          onDetected={onDetected}
          onClose={closeScanner}
          panel={
            mode === 'batch' ? (
              <BatchPanel
                items={batch}
                onRetry={(code) => claimInBatch(code, batchEntry.current.get(code) ?? 'camera')}
                onDone={closeScanner}
              />
            ) : undefined
          }
        />
      )}

      <UpdateSheet
        request={request}
        onClose={() => setRequest(null)}
        onUpdated={refresh}
        helpLink={
          me?.support
            ? (tracking) =>
                supportLink(me.support!.whatsapp_digits, t('support_parcel_message', { name: me.rider.name, phone: me.rider.phone, tracking }))
            : undefined
        }
      />

      <CashSheet
        open={cashOpen}
        onClose={() => setCashOpen(false)}
        only={cashSide}
        cash={me?.cash ?? null}
        pay={me?.pay ?? null}
        onLoaded={(balances) => setMe((m) => (m ? { ...m, ...balances } : m))}
      />
    </div>
  )
}

type HomeProps = {
  me: Me | null
  parcels: Parcel[] | null
  delivered: HistoryResponse | null
  list: List
  onList: (list: List) => void
  range: HistoryRange
  onRange: (range: HistoryRange) => void
  dates: HistoryDates | null
  /** The rider picked their own dates: the range becomes 'custom'. */
  onDates: (dates: HistoryDates) => void
  error: string | null
  onRefresh: () => void
  onOpen: (code: string, entry: EntryMethod, preview?: Parcel) => void
  onProfile: () => void
  onCash: (side: MoneySide) => void
}

type ParcelFilter = 'all' | 'out_for_delivery' | 'attempt_fail' | 'to_return' | 'cod'

const PARCEL_FILTERS: ParcelFilter[] = ['all', 'out_for_delivery', 'attempt_fail', 'to_return', 'cod']

function passesFilter(parcel: Parcel, filter: ParcelFilter): boolean {
  // Nothing is collected for a parcel that's going back.
  if (filter === 'cod') return parcel.cod_amount > 0 && !parcel.to_return
  if (filter === 'to_return') return parcel.to_return
  return filter === 'all' || parcel.status === filter
}

/** Cash is what the rider has to hand in; card and transfer reach KSA Drop directly. */
const paidInCash = (event: HistoryEvent) => event.payment_method === 'cash' && (event.cod_amount ?? 0) > 0

/** Lower case with Western digits, so "٠٥٥" finds "055". */
const searchable = (value: string) => value.replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d))).toLowerCase().trim()

/** A name, or part of a phone, tracking or order number — spaces and dashes in numbers don't matter. */
function matchesSearch(query: string, fields: (string | null | undefined)[]): boolean {
  const q = searchable(query)
  if (!q) return true
  const digits = q.replace(/\D/g, '')
  return fields.some((field) => {
    if (!field) return false
    const text = searchable(field)
    return text.includes(q) || (digits.length >= 3 && text.replace(/\D/g, '').includes(digits))
  })
}

function HomeView({ me, parcels, delivered, list, onList, range, onRange, dates, onDates, error, onRefresh, onOpen, onProfile, onCash }: HomeProps) {
  const { t, lang } = useI18n()
  const today = me?.today

  const [query, setQuery] = useState('')
  const [filter, setFilter] = useState<ParcelFilter>('all')
  // Delivered list: only the parcels paid in cash.
  const [cashOnly, setCashOnly] = useState(false)
  const [filtering, setFiltering] = useState(false)

  useBackToClose(filtering, () => setFiltering(false))

  // A number at the top is a shortcut to its parcels.
  const showParcels = (next: ParcelFilter) => {
    onList('with_me')
    setFilter(next)
    window.scrollTo(0, 0)
  }

  const showDeliveredToday = (cash: boolean) => {
    onList('delivered')
    onRange('today')
    setCashOnly(cash)
    window.scrollTo(0, 0)
  }

  const showDeliveredBetween = (next: HistoryDates) => {
    onList('delivered')
    onDates(next)
    setCashOnly(false)
    setFiltering(false)
    window.scrollTo(0, 0)
  }

  // The tiles are the only way between the lists, so the one whose parcels
  // are on screen stays lit — whichever period Delivered is showing.
  const showing = (which: ParcelFilter) => list === 'with_me' && filter === which
  const showingDelivered = (cash: boolean) => list === 'delivered' && cashOnly === cash

  const shownParcels = useMemo(
    () =>
      parcels?.filter(
        (parcel) =>
          passesFilter(parcel, filter) &&
          matchesSearch(query, [parcel.receiver.name, parcel.receiver.phone, parcel.tracking_number, parcel.order_number, addressLine(parcel)])
      ) ?? null,
    [parcels, filter, query]
  )

  // The period's deliveries, or only the ones paid in cash.
  const periodDelivered = useMemo(() => (delivered && cashOnly ? delivered.events.filter(paidInCash) : (delivered?.events ?? null)), [delivered, cashOnly])

  const shownDates = periodDates(range, dates)

  const shownDelivered = useMemo(
    () =>
      periodDelivered?.filter((event) =>
        matchesSearch(query, [event.receiver_name, event.recipient_name, event.tracking_number, event.order_number, event.city])
      ) ?? null,
    [periodDelivered, query]
  )

  const searching = query.trim() !== ''

  // A hairline under the frosted top once the list is scrolled under it.
  const [scrolled, setScrolled] = useState(false)
  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 4)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  return (
    <>
      {/* Header, search and today's numbers stay at the top; the list
          scrolls up under them, frosted. */}
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
          onProfile={onProfile}
          query={query}
          onQuery={setQuery}
          filterActive={list === 'with_me' ? filter !== 'all' : range !== 'today' || cashOnly}
          onFilter={() => setFiltering(true)}
        />

        <section className='px-4 pb-3 pt-5'>
          <p className='px-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground'>{t('today')}</p>
          <div className='mt-1.5 grid grid-cols-4 gap-2'>
            <Stat
              label={t('with_me')}
              value={today?.held}
              active={list === 'with_me' && filter !== 'attempt_fail'}
              onClick={() => showParcels('all')}
            />
            <Stat
              label={t('delivered_today')}
              value={today?.delivered}
              tone='text-green-600 dark:text-green-400'
              active={showingDelivered(false)}
              onClick={() => showDeliveredToday(false)}
            />
            <Stat
              label={t('failed_today')}
              value={today?.failed}
              tone={today?.failed ? 'text-red-600' : undefined}
              active={showing('attempt_fail')}
              onClick={() => showParcels('attempt_fail')}
            />
            <Stat
              label={t('cash_today')}
              value={today ? money(today.cash_collected, '').trim() : undefined}
              tone='text-brand'
              small
              active={showingDelivered(true)}
              onClick={() => showDeliveredToday(true)}
            />
          </div>
        </section>
      </div>

      {list === 'with_me' ? (
        <FilterSheet
          open={filtering}
          onClose={() => setFiltering(false)}
          title={t('filter_title')}
          value={filter}
          onChange={setFilter}
          options={PARCEL_FILTERS.map((value) => ({
            value,
            label: t(`filter_${value}`),
            count: parcels?.filter((parcel) => passesFilter(parcel, value)).length,
          }))}
        >
          <DateRangeForm title={t('dates_delivered_title')} initial={shownDates} onApply={showDeliveredBetween} />
        </FilterSheet>
      ) : (
        <FilterSheet
          open={filtering}
          onClose={() => setFiltering(false)}
          title={t('period_title')}
          value={range}
          onChange={onRange}
          options={RANGES.map((value) => ({ value, label: t(`range_${value}`) }))}
        >
          <DateRangeForm
            title={t('dates_title')}
            initial={shownDates}
            onApply={(next) => {
              onDates(next)
              setFiltering(false)
            }}
          />
        </FilterSheet>
      )}

      <main className='px-4 pb-[calc(env(safe-area-inset-bottom)+96px)] pt-1'>
        {me && <MoneyTiles cash={me.cash} pay={me.pay} onOpen={onCash} />}

        {error && (
          <div className='glass-tint mt-3 rounded-2xl p-3.5 text-sm' style={{ '--tint': '#dc2626' } as React.CSSProperties}>
            {error}
            <button type='button' onClick={onRefresh} className='ms-2 font-semibold underline'>
              {t('retry')}
            </button>
          </div>
        )}

        {/* Parcels that ended returned or cancelled and still have to go
            back. Tap to see only those; tap again for everything with me. */}
        {list === 'with_me' && !!today?.to_return && (
          <button
            type='button'
            onClick={() => showParcels(filter === 'to_return' ? 'all' : 'to_return')}
            aria-pressed={filter === 'to_return'}
            className='glass-tint glass-press mt-3 flex w-full items-center gap-2.5 rounded-2xl px-3.5 py-3 text-start text-sm font-semibold'
            style={{ '--tint': '#d97706' } as React.CSSProperties}
          >
            <Undo2 className='h-5 w-5 shrink-0' />
            <span className='min-w-0 flex-1'>{t('to_return_notice', { n: today.to_return })}</span>
            {filter !== 'to_return' && <ChevronRight className='h-4 w-4 shrink-0 rtl:rotate-180' />}
          </button>
        )}

        {/* The period is picked from the filter button, never on the page.
            Only said here: the period holds more deliveries than the list. */}
        {list === 'delivered' && delivered && delivered.summary.count > delivered.events.length && (
          <p className='mt-3 px-1 text-[13px] leading-snug text-muted-foreground'>{t('delivered_newest', { n: delivered.events.length })}</p>
        )}

        {list === 'with_me' ? (
          <WithMeList
            parcels={shownParcels}
            error={error}
            empty={searching || filter !== 'all' ? { text: t('no_match'), matching: true } : { text: t('no_parcels'), matching: false }}
            onOpen={onOpen}
          />
        ) : (
          <DeliveredList
            events={shownDelivered}
            showDay={shownDates.from !== shownDates.to}
            lang={lang}
            empty={searching || cashOnly ? { text: t('no_match'), matching: true } : { text: t('no_delivered'), matching: false }}
            onOpen={onOpen}
          />
        )}
      </main>
    </>
  )
}

function WithMeList({
  parcels,
  error,
  empty,
  onOpen,
}: {
  parcels: Parcel[] | null
  error: string | null
  empty: EmptyInfo
  onOpen: HomeProps['onOpen']
}) {
  const { t } = useI18n()

  if (parcels === null) {
    return error ? null : <Spinner />
  }

  if (parcels.length === 0) {
    return <Empty {...empty} />
  }

  return (
    <ul className='mt-3 space-y-2'>
      {parcels.map((parcel) => (
        <li key={parcel.id}>
          <button
            type='button'
            onClick={() => onOpen(parcel.tracking_number, 'manual', parcel)}
            className='glass-lite glass-press flex w-full items-center gap-2 rounded-2xl px-3.5 py-3 text-start'
          >
            <div className='min-w-0 flex-1'>
              <div className='flex items-center gap-2'>
                <p className='truncate text-[15px] font-semibold'>{parcel.receiver.name}</p>
                <StatusBadge status={parcel.status} className='ms-auto' />
              </div>
              <p className='mt-0.5 truncate text-[13px] text-muted-foreground'>{addressLine(parcel)}</p>
              {failedWhy(t, parcel) && (
                <p className='mt-0.5 truncate text-[13px] font-medium text-red-600 dark:text-red-400'>{failedWhy(t, parcel)}</p>
              )}
              <div className='mt-1 flex items-center gap-3 text-xs'>
                <span className='font-mono text-muted-foreground' dir='ltr'>{parcel.tracking_number}</span>
                {parcel.to_return ? (
                  <span className='font-semibold text-amber-700 dark:text-amber-400'>{t('return_to_hub')}</span>
                ) : (
                  <>
                    {parcel.cod_amount > 0 && (
                      <span className='font-semibold text-orange-700 dark:text-orange-300' dir='ltr'>
                        {money(parcel.cod_amount, parcel.currency)}
                      </span>
                    )}
                    {parcel.attempts > 0 && <span className='font-semibold text-red-600'>{t('attempts', { n: parcel.attempts })}</span>}
                  </>
                )}
              </div>
            </div>
            <ChevronRight className='h-4 w-4 shrink-0 text-muted-foreground rtl:rotate-180' />
          </button>
        </li>
      ))}
    </ul>
  )
}

/** The ready-made periods; the rider's own dates are the filter sheet's date fields. */
const RANGES: Exclude<HistoryRange, 'custom'>[] = ['today', 'yesterday', 'week']

function DeliveredList({
  events,
  showDay,
  lang,
  empty,
  onOpen,
}: {
  events: HistoryEvent[] | null
  /** The period is longer than a day, so each row says which day. */
  showDay: boolean
  lang: Lang
  empty: EmptyInfo
  onOpen: HomeProps['onOpen']
}) {
  const { t } = useI18n()

  // Western digits in both languages, Riyadh time.
  const when = (iso: string) =>
    new Date(iso).toLocaleString(lang === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', {
      timeZone: 'Asia/Riyadh',
      hour: '2-digit',
      minute: '2-digit',
      ...(showDay ? { day: 'numeric', month: 'short' } : {}),
    })

  if (events === null) {
    return <Spinner />
  }

  if (events.length === 0) {
    return <Empty {...empty} />
  }

  return (
    <ul className='mt-3 space-y-2'>
      {events.map((event) => (
        <li key={event.id}>
          <button
            type='button'
            disabled={!event.tracking_number}
            onClick={() => event.tracking_number && onOpen(event.tracking_number, 'manual')}
            className='glass-lite glass-press flex w-full items-center gap-3 rounded-2xl px-3.5 py-3 text-start'
          >
            <CheckCircle2 className='h-5 w-5 shrink-0 text-green-600' />
            <div className='min-w-0 flex-1'>
              <div className='flex items-center gap-2'>
                <p className='truncate text-[15px] font-semibold'>{event.receiver_name ?? '—'}</p>
                <span className='ms-auto shrink-0 text-xs text-muted-foreground' dir='ltr'>
                  {when(event.occurred_at)}
                </span>
              </div>
              <div className='mt-1 flex items-center gap-3 text-xs'>
                <span className='font-mono text-muted-foreground' dir='ltr'>{event.tracking_number}</span>
                {event.cod_amount !== null && event.cod_amount > 0 && (
                  <span className='font-semibold text-green-700 dark:text-green-400' dir='ltr'>
                    {money(event.cod_amount, event.currency)}
                    {event.payment_method && event.payment_method !== 'cash' && ` · ${t(`pay_${event.payment_method}`)}`}
                  </span>
                )}
              </div>
              {event.recipient_name && (
                <p className='mt-0.5 truncate text-xs text-muted-foreground'>{t('delivered_to', { name: event.recipient_name })}</p>
              )}
            </div>
          </button>
        </li>
      ))}
    </ul>
  )
}

/** Today's number, and a button: tap it to see the parcels behind it. */
function Stat({
  label,
  value,
  tone,
  small,
  active,
  onClick,
}: {
  label: string
  value: number | string | undefined
  tone?: string
  small?: boolean
  /** The list below is showing this tile's parcels. */
  active: boolean
  onClick: () => void
}) {
  return (
    <button
      type='button'
      onClick={onClick}
      aria-pressed={active}
      className={cn('glass-press rounded-2xl px-1 py-2 text-center', active ? 'glass-tint' : 'glass')}
    >
      <p className={cn('font-bold tabular-nums leading-tight', small ? 'text-sm leading-7' : 'text-xl', !active && tone)} dir='ltr'>
        {value ?? '–'}
      </p>
      <p className={cn('truncate text-[10.5px] leading-tight', active ? 'text-white/85' : 'text-muted-foreground')}>{label}</p>
    </button>
  )
}

/**
 * The rider's money above the lists, a tile each way: what KSA Drop owes them
 * (once pay is set up) and the cash they owe KSA Drop. Never netted. Each
 * opens the cash sheet with its own account alone.
 */
function MoneyTiles({ cash, pay, onOpen }: { cash: RiderCash; pay: RiderPay; onOpen: (side: MoneySide) => void }) {
  const { t } = useI18n()
  const earning = hasPay(pay)

  return (
    <div className={cn('grid gap-2', earning && 'grid-cols-2')}>
      {earning && (
        <MoneyTile
          icon={Banknote}
          color='#16a34a'
          title={t('earnings_title')}
          amount={pay.balance}
          status={t(payLabel(pay.balance))}
          tone={pay.balance > 0 ? 'text-green-700 dark:text-green-400' : 'text-muted-foreground'}
          onClick={() => onOpen('pay')}
        />
      )}
      <MoneyTile
        icon={Wallet}
        color='var(--brand)'
        title={t('payable_title')}
        amount={cash.balance}
        status={t(cashLabel(cash.balance))}
        tone={cash.balance > 0 ? 'text-brand' : 'text-green-700 dark:text-green-400'}
        onClick={() => onOpen('cash')}
      />
    </div>
  )
}

/** One balance: what it is, the amount (always shown, zero too), and how it stands. */
function MoneyTile({
  icon: Icon,
  color,
  title,
  amount,
  status,
  tone,
  onClick,
}: {
  icon: LucideIcon
  color: string
  title: string
  amount: number
  status: string
  tone: string
  onClick: () => void
}) {
  // A minus when it runs the other way: paid extra, or paid in advance.
  const figure = `${amount < 0 ? '−' : ''}${money(Math.abs(amount), '').trim()}`

  return (
    <button type='button' onClick={onClick} className='glass-lite glass-press min-w-0 rounded-2xl px-3.5 py-3 text-start'>
      <span className='flex items-center gap-2'>
        <span className='flex h-7 w-7 shrink-0 items-center justify-center rounded-[9px] text-white' style={{ background: color }}>
          <Icon className='h-4 w-4' />
        </span>
        <span className='min-w-0 flex-1 truncate text-[13px] font-semibold'>{title}</span>
        <ChevronRight className='h-4 w-4 shrink-0 text-muted-foreground rtl:rotate-180' />
      </span>
      <span className='mt-2 flex'>
        <span className={cn('flex items-baseline gap-1 font-bold leading-tight tabular-nums', tone)} dir='ltr'>
          <span className='text-[11px] font-semibold'>SAR</span>
          {/* Two tiles share a small phone's width: long amounts step down. */}
          <span className={figure.length > 8 ? 'text-[clamp(15px,4.6vw,18px)]' : 'text-[clamp(18px,5.6vw,22px)]'}>{figure}</span>
        </span>
      </span>
      <span className='mt-0.5 block text-xs leading-snug text-muted-foreground'>{status}</span>
    </button>
  )
}

function Spinner() {
  return (
    <div className='flex justify-center py-10'>
      <Loader2 className='h-6 w-6 animate-spin text-muted-foreground' />
    </div>
  )
}

/** What an empty list says, and whether it's empty because of a search or filter. */
type EmptyInfo = { text: string; matching: boolean }

/** The portal's empty-list bot: on the move when there's nothing yet (go scan some), calm when nothing matches. */
function Empty({ text, matching }: EmptyInfo) {
  return <EmptyState bot='pill' state={matching ? 'default' : 'working'} size='lg' title={text} className='py-12' />
}

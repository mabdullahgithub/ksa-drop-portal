import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { CheckCircle2, ChevronRight, Loader2, X } from 'lucide-react'
import { toast } from 'sonner'
import { EmptyState } from '@/components/empty-state'
import { cn } from '@/lib/utils'
import { api, ApiError } from '../api'
import { BatchPanel } from '../components/batch-panel'
import { BottomNav, type Tab } from '../components/bottom-nav'
import { FilterSheet } from '../components/filter-sheet'
import { HomeHeader } from '../components/home-header'
import { addressLine, StatusBadge } from '../components/parcel-parts'
import { Scanner } from '../components/scanner'
import { UpdateSheet, type UpdateRequest } from '../components/update-sheet'
import { money, reasonText, useI18n, type Lang } from '../i18n'
import { useBackToClose } from '../lib/back-button'
import { uuid, vibrate } from '../lib/device'
import { usePresence } from '../lib/presence'
import { PullIndicator, usePullToRefresh } from '../lib/pull-to-refresh'
import { cssColor, useStatusBarColor } from '../lib/status-bar'
import type { BatchItem, ClaimResponse, EntryMethod, HistoryRange, HistoryResponse, Me, Parcel, ScanMode } from '../types'
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
 * Scan in the bottom bar. Scanner and update sheet open over either tab.
 */
export function AppShell() {
  const { t } = useI18n()
  usePresence()

  const [tab, setTab] = useState<Tab>('home')
  const [list, setList] = useState<List>('with_me')
  const [range, setRange] = useState<HistoryRange>('today')

  const [me, setMe] = useState<Me | null>(null)
  const [parcels, setParcels] = useState<Parcel[] | null>(null)
  const [delivered, setDelivered] = useState<HistoryResponse | null>(null)
  const [error, setError] = useState<string | null>(null)

  const [scanning, setScanning] = useState(false)
  const [request, setRequest] = useState<UpdateRequest | null>(null)

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

  const loadDelivered = useCallback(async (which: HistoryRange) => {
    try {
      setDelivered(await api.get<HistoryResponse>(`/rider/api/history?action=delivered&range=${which}`))
    } catch (e) {
      setError(reasonText(t, (e as ApiError).code) ?? t('something_wrong'))
    }
  }, [t])

  const refresh = useCallback(
    () => Promise.all([load(), list === 'delivered' ? loadDelivered(range) : undefined]),
    [load, loadDelivered, list, range]
  )

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    if (list === 'delivered') {
      setDelivered(null)
      loadDelivered(range)
    }
  }, [list, range, loadDelivered])

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

  useBackToClose(scanning, closeScanner)
  useBackToClose(request !== null, () => setRequest(null))

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
          error={error}
          onRefresh={refresh}
          onOpen={open}
          onProfile={() => setTab('profile')}
        />
      ) : (
        <ProfileView
          me={me}
          onPhotoChanged={(photoUrl) => setMe((m) => (m ? { ...m, rider: { ...m.rider, photo_url: photoUrl } } : m))}
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
  error: string | null
  onRefresh: () => void
  onOpen: (code: string, entry: EntryMethod, preview?: Parcel) => void
  onProfile: () => void
}

type ParcelFilter = 'all' | 'out_for_delivery' | 'attempt_fail' | 'cod'

const PARCEL_FILTERS: ParcelFilter[] = ['all', 'out_for_delivery', 'attempt_fail', 'cod']

function passesFilter(parcel: Parcel, filter: ParcelFilter): boolean {
  if (filter === 'cod') return parcel.cod_amount > 0
  return filter === 'all' || parcel.status === filter
}

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

function HomeView({ me, parcels, delivered, list, onList, range, onRange, error, onRefresh, onOpen, onProfile }: HomeProps) {
  const { t, lang } = useI18n()
  const today = me?.today

  const [query, setQuery] = useState('')
  const [filter, setFilter] = useState<ParcelFilter>('all')
  const [filtering, setFiltering] = useState(false)

  useBackToClose(filtering, () => setFiltering(false))

  const shownParcels = useMemo(
    () =>
      parcels?.filter(
        (parcel) =>
          passesFilter(parcel, filter) &&
          matchesSearch(query, [parcel.receiver.name, parcel.receiver.phone, parcel.tracking_number, parcel.order_number, addressLine(parcel)])
      ) ?? null,
    [parcels, filter, query]
  )

  const shownDelivered = useMemo(
    () =>
      delivered && {
        ...delivered,
        events: delivered.events.filter((event) =>
          matchesSearch(query, [event.receiver_name, event.recipient_name, event.tracking_number, event.order_number, event.city])
        ),
      },
    [delivered, query]
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
          filterActive={list === 'with_me' ? filter !== 'all' : range !== 'today'}
          onFilter={() => setFiltering(true)}
        />

        <section className='px-4 pb-3 pt-5'>
          <p className='px-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground'>{t('today')}</p>
          <div className='mt-1.5 grid grid-cols-4 gap-2'>
            <Stat label={t('with_me')} value={today?.held} />
            <Stat label={t('delivered_today')} value={today?.delivered} tone='text-green-600 dark:text-green-400' />
            <Stat label={t('failed_today')} value={today?.failed} tone={today?.failed ? 'text-red-600' : undefined} />
            <Stat label={t('cash_today')} value={today ? money(today.cod_collected, '').trim() : undefined} tone='text-brand' small />
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
        />
      ) : (
        <FilterSheet
          open={filtering}
          onClose={() => setFiltering(false)}
          title={t('period_title')}
          value={range}
          onChange={onRange}
          options={RANGES.map((value) => ({ value, label: t(`range_${value}`) }))}
        />
      )}

      <main className='px-4 pb-[calc(env(safe-area-inset-bottom)+96px)] pt-1'>
        {/* With me / Delivered */}
        <div className='glass grid grid-cols-2 rounded-full p-1'>
          {(['with_me', 'delivered'] as const).map((option) => (
            <button
              key={option}
              type='button'
              onClick={() => onList(option)}
              aria-pressed={list === option}
              className={cn(
                'h-9 rounded-full text-sm font-semibold transition-colors',
                list === option ? 'glass-strong text-foreground' : 'border border-transparent text-muted-foreground'
              )}
            >
              {t(option === 'with_me' ? 'tab_with_me' : 'tab_delivered')}
              {option === 'with_me' && parcels && <span className='ms-1 text-muted-foreground'>({parcels.length})</span>}
            </button>
          ))}
        </div>

        {error && (
          <div className='glass-tint mt-3 rounded-2xl p-3.5 text-sm' style={{ '--tint': '#dc2626' } as React.CSSProperties}>
            {error}
            <button type='button' onClick={onRefresh} className='ms-2 font-semibold underline'>
              {t('retry')}
            </button>
          </div>
        )}

        {list === 'with_me' && filter !== 'all' && (
          <button
            type='button'
            onClick={() => setFilter('all')}
            className='mt-3 inline-flex h-8 items-center gap-1.5 rounded-full bg-ink pe-2.5 ps-3.5 text-[13px] font-semibold text-white dark:bg-white dark:text-ink'
          >
            {t(`filter_${filter}`)}
            <X className='h-4 w-4' />
          </button>
        )}

        {list === 'delivered' && <DeliveredHead range={range} onRange={onRange} summary={delivered?.summary ?? null} />}

        {list === 'with_me' ? (
          <WithMeList
            parcels={shownParcels}
            error={error}
            empty={searching || filter !== 'all' ? { text: t('no_match'), matching: true } : { text: t('no_parcels'), matching: false }}
            onOpen={onOpen}
          />
        ) : (
          <DeliveredList
            delivered={shownDelivered}
            range={range}
            lang={lang}
            empty={searching ? { text: t('no_match'), matching: true } : { text: t('no_delivered'), matching: false }}
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
              <div className='mt-1 flex items-center gap-3 text-xs'>
                <span className='font-mono text-muted-foreground' dir='ltr'>{parcel.tracking_number}</span>
                {parcel.cod_amount > 0 && (
                  <span className='font-semibold text-orange-700 dark:text-orange-300' dir='ltr'>
                    {money(parcel.cod_amount, parcel.currency)}
                  </span>
                )}
                {parcel.attempts > 0 && <span className='font-semibold text-red-600'>{t('attempts', { n: parcel.attempts })}</span>}
              </div>
            </div>
            <ChevronRight className='h-4 w-4 shrink-0 text-muted-foreground rtl:rotate-180' />
          </button>
        </li>
      ))}
    </ul>
  )
}

const RANGES: HistoryRange[] = ['today', 'yesterday', 'week']

/** The Delivered list's period and totals, above the scrolling list. */
function DeliveredHead({
  range,
  onRange,
  summary,
}: {
  range: HistoryRange
  onRange: (range: HistoryRange) => void
  summary: HistoryResponse['summary'] | null
}) {
  const { t } = useI18n()

  return (
    <>
      <div className='mt-3 flex gap-2'>
        {RANGES.map((option) => (
          <button
            key={option}
            type='button'
            onClick={() => onRange(option)}
            aria-pressed={range === option}
            className={cn(
              'glass-press h-8 rounded-full px-3.5 text-[13px] font-semibold',
              range === option ? 'glass-tint' : 'glass-lite'
            )}
          >
            {t(`range_${option}`)}
          </button>
        ))}
      </div>

      {summary && (
        <div className='glass mt-3 flex items-center justify-between rounded-2xl px-3.5 py-3 text-sm'>
          <span className='font-semibold text-green-700 dark:text-green-400'>{t('delivered_summary', { n: summary.count })}</span>
          <span className='text-muted-foreground'>
            {t('cash_label')}{' '}
            <span className='font-semibold text-foreground' dir='ltr'>
              {money(summary.cod_collected, 'SAR')}
            </span>
          </span>
        </div>
      )}
    </>
  )
}

function DeliveredList({
  delivered,
  range,
  lang,
  empty,
  onOpen,
}: {
  delivered: HistoryResponse | null
  range: HistoryRange
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
      ...(range === 'week' ? { day: 'numeric', month: 'short' } : {}),
    })

  if (delivered === null) {
    return <Spinner />
  }

  if (delivered.events.length === 0) {
    return <Empty {...empty} />
  }

  return (
    <ul className='mt-3 space-y-2'>
      {delivered.events.map((event) => (
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

function Stat({ label, value, tone, small }: { label: string; value: number | string | undefined; tone?: string; small?: boolean }) {
  return (
    <div className='glass rounded-2xl px-1 py-2 text-center'>
      <p className={cn('font-bold tabular-nums leading-tight', small ? 'text-sm leading-7' : 'text-xl', tone)} dir='ltr'>
        {value ?? '–'}
      </p>
      <p className='truncate text-[10.5px] leading-tight text-muted-foreground'>{label}</p>
    </div>
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

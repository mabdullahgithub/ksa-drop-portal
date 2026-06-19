import { useState, useEffect, type FormEvent, useRef } from 'react'
import { Head } from '@inertiajs/react'
import axios from 'axios'
import { cn } from '@/lib/utils'
import {
  Search, Truck, MapPin, ClipboardCheck, Home,
  CheckCircle2, AlertTriangle, Clock, Copy, Check,
  RotateCcw, Ban, PackageCheck, Loader2, Moon, Sun,
  type LucideIcon,
} from 'lucide-react'

// ── Types ──────────────────────────────────────────────────────────────────────

interface TrackingEvent {
  status: string
  description: string
  location: string | null
  timestamp: string
}

interface ShipmentData {
  order_id: number
  order_number: string
  customer_name: string | null
  tracking_number: string | null
  courier: string
  status: string
  status_label: string
  status_color: string
  tracking_history: TrackingEvent[]
  shipped_at: string | null
  delivered_at: string | null
}

type Phase = 'idle' | 'searching' | 'found' | 'not_found'

// ── Assets ─────────────────────────────────────────────────────────────────────

const ILLUSTRATION = '/images/tracking/3d-isometric-supply-chain-logistics-from-online-order-to-package-delivery.png'
const LOGO_COLOR   = '/images/email/logo.png'
const LOGO_WHITE   = '/images/email/logo-white.png'

// ── Constants ──────────────────────────────────────────────────────────────────

const PATH_STEPS: { key: string; label: string; icon: LucideIcon }[] = [
  { key: 'info_received',    label: 'Order Processed',   icon: ClipboardCheck },
  { key: 'in_transit',       label: 'In Transit',        icon: Truck },
  { key: 'out_for_delivery', label: 'Out for Delivery',  icon: MapPin },
  { key: 'delivered',        label: 'Delivered',         icon: Home },
]

const HALTED  = ['cancelled', 'failed', 'returned']
const PROBLEM = ['exception', 'attempt_fail', ...HALTED]

const STATUS_BADGE: Record<string, string> = {
  gray:   'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600',
  blue:   'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800',
  indigo: 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-800',
  orange: 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-900/30 dark:text-orange-300 dark:border-orange-800',
  red:    'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-800',
  green:  'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
  yellow: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
}

// ── Helpers ────────────────────────────────────────────────────────────────────

function stepIndex(status: string): number {
  switch (status) {
    case 'info_received':    return 0
    case 'in_transit':
    case 'exception':        return 1
    case 'out_for_delivery':
    case 'attempt_fail':
    case 'returned':         return 2
    case 'delivered':        return 3
    default:                 return -1
  }
}

function fmtDate(v: string | null) {
  if (!v) return '—'
  const d = new Date(v)
  return Number.isNaN(d.getTime()) ? v : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

function fmtDateTime(v: string | null) {
  if (!v) return '—'
  const d = new Date(v)
  return Number.isNaN(d.getTime()) ? v : d.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' })
}

function tlIcon(status: string): LucideIcon {
  if (status === 'delivered')                   return PackageCheck
  if (status === 'returned')                    return RotateCcw
  if (['cancelled', 'failed'].includes(status)) return Ban
  if (PROBLEM.includes(status))                 return AlertTriangle
  if (status === 'out_for_delivery')            return MapPin
  if (status === 'in_transit')                  return Truck
  if (status === 'info_received')               return ClipboardCheck
  return Clock
}

// ── Sub-components ─────────────────────────────────────────────────────────────

function ProgressStepper({ status }: { status: string }) {
  const halted    = HALTED.includes(status)
  const current   = stepIndex(status)
  const delivered = status === 'delivered'
  const activeLine = halted ? 'bg-amber-400' : 'bg-orange-500'
  const activeNode = halted
    ? 'border-amber-400 bg-amber-400 text-white shadow-md shadow-amber-200 dark:shadow-amber-900/30'
    : 'border-orange-500 bg-orange-500 text-white shadow-md shadow-orange-200 dark:shadow-orange-900/30'

  return (
    <div className="flex items-start py-1">
      {PATH_STEPS.map((step, i) => {
        const reached   = i <= current
        const isCurrent = i === current && !delivered
        const Icon      = step.icon
        return (
          <div key={step.key} className="flex min-w-0 flex-1 flex-col items-center">
            <div className="flex w-full items-center">
              <div className={cn('h-0.5 flex-1', i === 0 ? 'opacity-0' : reached ? activeLine : 'bg-slate-200 dark:bg-slate-700')} />
              <div className={cn(
                'relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 transition-all duration-300',
                reached ? activeNode : 'border-slate-200 bg-white text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-600',
              )}>
                {isCurrent && !halted && (
                  <span className="absolute inset-0 animate-ping rounded-full bg-orange-400/40" />
                )}
                <Icon className="relative h-4 w-4" />
              </div>
              <div className={cn('h-0.5 flex-1', i === PATH_STEPS.length - 1 ? 'opacity-0' : i < current ? activeLine : 'bg-slate-200 dark:bg-slate-700')} />
            </div>
            <p className={cn('mt-2 text-center text-[10px] font-medium leading-tight sm:text-[11px]', reached ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-600')}>
              {step.label}
            </p>
          </div>
        )
      })}
    </div>
  )
}

function DetailRow({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
  return (
    <div className="flex items-center justify-between border-b border-slate-100 py-2.5 last:border-0 dark:border-slate-700/60">
      <span className="text-sm text-slate-500 dark:text-slate-400">{label}</span>
      <span className={cn('text-sm font-medium text-slate-900 dark:text-slate-100', mono && 'font-mono text-xs')}>{value}</span>
    </div>
  )
}

function ResultsPanel({ shipment }: { shipment: ShipmentData }) {
  const [copied, setCopied] = useState(false)
  const halted    = HALTED.includes(shipment.status)
  const isProblem = PROBLEM.includes(shipment.status)
  const isDelivered = shipment.status === 'delivered'
  const history   = shipment.tracking_history ?? []

  const copyTracking = async () => {
    if (!shipment.tracking_number) return
    await navigator.clipboard.writeText(shipment.tracking_number).catch(() => {})
    setCopied(true)
    setTimeout(() => setCopied(false), 1800)
  }

  return (
    <div className="h-full overflow-y-auto bg-slate-50 dark:bg-slate-900">
      <div className="space-y-4 p-5 lg:p-6">

        {/* Order header */}
        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-700/50 dark:bg-slate-800">
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
              <p className="mb-1 text-[10px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500">Order</p>
              <p className="text-2xl font-bold text-slate-900 dark:text-slate-50">{shipment.order_number}</p>
              {shipment.customer_name && (
                <p className="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{shipment.customer_name}</p>
              )}
              {shipment.tracking_number && (
                <div className="mt-1.5 flex items-center gap-2">
                  <span className="font-mono text-xs text-slate-500 dark:text-slate-400">{shipment.tracking_number}</span>
                  <button onClick={copyTracking} className="text-slate-400 transition-colors hover:text-orange-500" aria-label="Copy">
                    {copied ? <Check className="h-3 w-3 text-orange-500" /> : <Copy className="h-3 w-3" />}
                  </button>
                </div>
              )}
            </div>
            <span className={cn('mt-1 inline-flex shrink-0 items-center rounded-full border px-3 py-1 text-xs font-semibold', STATUS_BADGE[shipment.status_color] || STATUS_BADGE.gray)}>
              {shipment.status_label}
            </span>
          </div>
          {isDelivered && (
            <div className="mt-3 flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400">
              <CheckCircle2 className="h-4 w-4" /> Delivered successfully
            </div>
          )}
        </div>

        {/* Problem banner */}
        {isProblem && (
          <div className={cn('flex items-start gap-3 rounded-2xl border p-4',
            halted ? 'border-red-200 bg-red-50 dark:border-red-800/50 dark:bg-red-900/20'
                   : 'border-amber-200 bg-amber-50 dark:border-amber-800/50 dark:bg-amber-900/20')}>
            <AlertTriangle className={cn('mt-0.5 h-5 w-5 shrink-0', halted ? 'text-red-500' : 'text-amber-500')} />
            <div>
              <p className={cn('text-sm font-semibold', halted ? 'text-red-800 dark:text-red-300' : 'text-amber-800 dark:text-amber-300')}>{shipment.status_label}</p>
              <p className={cn('mt-0.5 text-sm', halted ? 'text-red-700 dark:text-red-400' : 'text-amber-700 dark:text-amber-400')}>
                {halted ? 'This shipment is no longer in active transit. Please contact support.' : 'There was an issue. The courier will attempt to resolve it shortly.'}
              </p>
            </div>
          </div>
        )}

        {/* Progress */}
        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-700/50 dark:bg-slate-800">
          <p className="mb-4 text-[10px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500">Delivery Progress</p>
          <ProgressStepper status={shipment.status} />
        </div>

        {/* Details + Timeline grid */}
        <div className="grid gap-4 xl:grid-cols-2">
          <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-700/50 dark:bg-slate-800">
            <p className="mb-3 text-[10px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500">Shipment Details</p>
            <DetailRow label="Order #"    value={shipment.order_number} />
            {shipment.customer_name && <DetailRow label="Customer" value={shipment.customer_name} />}
            <DetailRow label="Courier"    value="J&T Express" />
            {shipment.tracking_number && <DetailRow label="Tracking #" value={shipment.tracking_number} mono />}
            <DetailRow label="Shipped"    value={fmtDate(shipment.shipped_at)} />
            <DetailRow label="Delivered"  value={fmtDate(shipment.delivered_at)} />
          </div>

          <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm dark:border-slate-700/50 dark:bg-slate-800">
            <p className="mb-4 text-[10px] font-semibold uppercase tracking-widest text-slate-400 dark:text-slate-500">Tracking History</p>
            {history.length === 0 ? (
              <div className="flex flex-col items-center py-6 text-center">
                <Clock className="h-8 w-8 text-slate-300 dark:text-slate-600" />
                <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">No updates yet.</p>
              </div>
            ) : (
              <ol className="relative">
                {history.map((event, index) => {
                  const Icon     = tlIcon(event.status)
                  const isLatest = index === 0
                  const last     = index === history.length - 1
                  return (
                    <li key={index} className="relative flex gap-3 pb-5 last:pb-0">
                      {!last && <span className="absolute left-[13px] top-8 h-[calc(100%-1rem)] w-px bg-slate-200 dark:bg-slate-700" aria-hidden />}
                      <div className={cn(
                        'relative z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full',
                        isLatest ? 'bg-orange-500 text-white ring-4 ring-orange-100 dark:ring-orange-900/40' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400',
                      )}>
                        <Icon className="h-3.5 w-3.5" />
                      </div>
                      <div className="flex-1 pt-0.5">
                        <p className={cn('text-sm', isLatest ? 'font-semibold text-slate-900 dark:text-slate-50' : 'font-medium text-slate-700 dark:text-slate-300')}>
                          {event.description}
                        </p>
                        <div className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-400 dark:text-slate-500">
                          {event.location && <span className="inline-flex items-center gap-1"><MapPin className="h-3 w-3" />{event.location}</span>}
                          <span>{fmtDateTime(event.timestamp)}</span>
                        </div>
                      </div>
                    </li>
                  )
                })}
              </ol>
            )}
          </div>
        </div>

        <p className="pb-4 pt-2 text-center text-xs text-slate-400 dark:text-slate-600">
          Powered by KSA Drop · Updates provided by J&amp;T Express
        </p>
      </div>
    </div>
  )
}

// ── Main Page ──────────────────────────────────────────────────────────────────

export default function TrackingSearch() {
  const [isDark, setIsDark] = useState(() => {
    if (typeof window === 'undefined') return false
    return localStorage.getItem('trackingTheme') === 'dark'
  })
  const [phase,      setPhase]      = useState<Phase>('idle')
  const [shipment,   setShipment]   = useState<ShipmentData | null>(null)
  const [orderInput, setOrderInput] = useState('')
  const [errorMsg,   setErrorMsg]   = useState('')
  const [isDesktop,  setIsDesktop]  = useState(() => typeof window !== 'undefined' && window.innerWidth >= 768)
  const resultsRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const check = () => setIsDesktop(window.innerWidth >= 768)
    window.addEventListener('resize', check)
    return () => window.removeEventListener('resize', check)
  }, [])

  const toggleDark = () => {
    const next = !isDark
    setIsDark(next)
    localStorage.setItem('trackingTheme', next ? 'dark' : 'light')
  }

  const found    = phase === 'found'
  const searching = phase === 'searching'
  const notFound  = phase === 'not_found'

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    const trimmed = orderInput.trim()
    if (!trimmed) { setErrorMsg('Please enter an order number or tracking number.'); return }
    setErrorMsg('')
    setPhase('searching')
    try {
      const { data } = await axios.get<ShipmentData>(`/api/track/${encodeURIComponent(trimmed)}`)
      setShipment(data)
      setPhase('found')
      if (!isDesktop) setTimeout(() => resultsRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 150)
    } catch {
      setShipment(null)
      setPhase('not_found')
    }
  }

  const handleReset = () => { setPhase('idle'); setShipment(null); setOrderInput(''); setErrorMsg('') }

  // Panel dimension transitions
  const leftStyle: React.CSSProperties = isDesktop
    ? { width: found ? '44%' : '100%', transition: 'width 0.75s cubic-bezier(0.4,0,0.2,1)', flexShrink: 0, minHeight: '100vh' }
    : { width: '100%' }

  const rightWrapStyle: React.CSSProperties = isDesktop
    ? { flex: 1, overflow: 'hidden', minHeight: '100vh' }
    : { width: '100%', overflow: 'hidden' }

  const rightInnerStyle: React.CSSProperties = isDesktop
    ? { height: '100%', opacity: found ? 1 : 0, transform: found ? 'translateX(0)' : 'translateX(48px)', transition: 'opacity 0.55s ease 0.4s, transform 0.55s cubic-bezier(0.4,0,0.2,1) 0.4s', pointerEvents: found ? 'auto' : 'none' }
    : { opacity: found ? 1 : 0, maxHeight: found ? '9999px' : '0px', transition: 'opacity 0.5s ease 0.1s, max-height 0.75s ease', overflow: 'hidden' }

  const illustrationSize = found && isDesktop ? 140 : 180

  return (
    <>
      <Head title="Track Shipment — KSA Drop" />

      {/* Dark mode root — all dark: variants activate when isDark is true */}
      <div className={isDark ? 'dark' : ''}>
        <div className="relative flex min-h-screen flex-col overflow-hidden bg-gradient-to-br from-orange-50/60 via-white to-slate-50 dark:bg-none md:flex-row dark:bg-slate-950">

          {/* Dark/light toggle — fixed top-right */}
          <button
            onClick={toggleDark}
            aria-label="Toggle dark mode"
            className="fixed right-4 top-4 z-50 flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm transition-all hover:border-orange-300 hover:text-orange-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-orange-500 dark:hover:text-orange-400"
          >
            {isDark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
          </button>

          {/* ── Left / Search panel ─────────────────────────────── */}
          <div
            className="relative flex flex-col items-center justify-center border-r border-slate-100 dark:border-slate-800"
            style={leftStyle}
          >
            {/* Light-mode decorative gradient blobs */}
            <div className="pointer-events-none absolute inset-0 overflow-hidden">
              <div className="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-orange-400/10 blur-3xl dark:bg-orange-500/10" />
              <div className="absolute -bottom-20 -left-20 h-64 w-64 rounded-full bg-orange-300/8 blur-3xl dark:bg-orange-600/8" />
            </div>

            <div className="relative z-10 flex w-full max-w-sm flex-col items-center px-6 py-14 md:py-16">

              {/* Logo */}
              <div className="mb-8 flex items-center">
                <img
                  src={isDark ? LOGO_WHITE : LOGO_COLOR}
                  alt="KSA Drop"
                  className="h-10 w-auto object-contain"
                  style={{ maxWidth: 160 }}
                />
              </div>

              {/* Illustration inside a dark showcase card */}
              <div
                className="mb-8 flex items-center justify-center overflow-hidden rounded-3xl bg-slate-900 p-5 shadow-2xl"
                style={{
                  width:      illustrationSize + 40,
                  height:     illustrationSize + 40,
                  transition: 'width 0.75s cubic-bezier(0.4,0,0.2,1), height 0.75s cubic-bezier(0.4,0,0.2,1)',
                }}
              >
                <img
                  src={ILLUSTRATION}
                  alt="Supply chain logistics"
                  loading="eager"
                  style={{
                    width:      illustrationSize,
                    height:     illustrationSize,
                    objectFit:  'contain',
                    transition: 'width 0.75s cubic-bezier(0.4,0,0.2,1), height 0.75s cubic-bezier(0.4,0,0.2,1)',
                    filter:     'drop-shadow(0 12px 24px rgba(0,0,0,0.6))',
                  }}
                />
              </div>

              {/* Heading */}
              <div className="mb-7 text-center">
                <h1
                  className="font-bold text-slate-900 transition-all duration-500 dark:text-white"
                  style={{ fontSize: found && isDesktop ? '1.25rem' : '1.625rem' }}
                >
                  Track Your Shipment
                </h1>
                {(!found || !isDesktop) && (
                  <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Enter your order number or tracking number
                  </p>
                )}
              </div>

              {/* Search form */}
              <form onSubmit={handleSubmit} className="w-full" noValidate>
                <div className="relative">
                  <input
                    type="text"
                    value={orderInput}
                    onChange={(e) => { setOrderInput(e.target.value); if (errorMsg) setErrorMsg(''); if (notFound) setPhase('idle') }}
                    placeholder="#12962 or UTE01234…"
                    autoFocus
                    className={cn(
                      'h-12 w-full rounded-2xl border bg-white pl-4 pr-28 font-mono text-sm text-slate-900 placeholder:font-sans placeholder:text-slate-400',
                      'outline-none transition-all duration-200 shadow-sm',
                      'focus:ring-2 focus:ring-orange-500/20',
                      'dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500',
                      notFound || errorMsg
                        ? 'border-red-400 focus:border-red-400 dark:border-red-600'
                        : 'border-slate-200 focus:border-orange-500 dark:border-slate-600 dark:focus:border-orange-500',
                    )}
                  />
                  <button
                    type="submit"
                    disabled={searching}
                    className="absolute right-1.5 top-1.5 inline-flex h-9 items-center gap-1.5 rounded-xl bg-orange-500 px-4 text-sm font-semibold text-white shadow shadow-orange-500/30 transition-all hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2 disabled:opacity-60"
                  >
                    {searching ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
                    {searching ? 'Finding…' : 'Track'}
                  </button>
                </div>

                {errorMsg && <p className="mt-2 text-xs text-red-500">{errorMsg}</p>}
                {notFound && !errorMsg && (
                  <p className="mt-2 text-xs text-red-500">
                    No shipment found. Try the order number (e.g. #12962) or tracking number.
                  </p>
                )}
              </form>

              {/* Active tracking indicator */}
              {found && shipment && (
                <div className="mt-5 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                  <span className="h-2 w-2 animate-pulse rounded-full bg-orange-500" />
                  Tracking <span className="font-semibold text-orange-500">{shipment.order_number}</span>
                  <button onClick={handleReset} className="ml-1 text-slate-400 underline hover:text-slate-600 dark:hover:text-white">Clear</button>
                </div>
              )}

              {!found && (
                <div className="mt-8 flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                  <Truck className="h-4 w-4 text-orange-500/60" />
                  Delivery powered by J&amp;T Express
                </div>
              )}
            </div>
          </div>

          {/* ── Right / Results panel ────────────────────────────── */}
          <div style={rightWrapStyle}>
            <div ref={resultsRef} style={rightInnerStyle}>
              {shipment && <ResultsPanel shipment={shipment} />}
            </div>
          </div>

        </div>
      </div>
    </>
  )
}

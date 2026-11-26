import { useState } from 'react'
import { Link, usePage } from '@inertiajs/react'
import { ChevronRight, ShoppingCart, Package, Clock, Info, Hourglass, PackageCheck, BadgeCheck, Truck, RotateCcw, Banknote, Warehouse, Phone, Receipt, Tag } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { usePortalDashboard } from '@/hooks/usePortal'
import { BUSINESS_TIMEZONE } from '@/lib/business-time'
import { StatCard, StatCardSkeleton } from '@/components/stat-card'
import { InsightsSection } from '@/features/dashboard/components/insights-section'
import { InsightCard, InsightEmpty } from '@/features/dashboard/components/insight-card'

/** Same grid as the admin dashboard's KPI rows. */
const STAT_GRID = 'grid grid-cols-2 gap-x-2 gap-y-2.5 sm:grid-cols-3 lg:grid-cols-6'

const CHARGES_ACK_KEY = 'charges_alert_acknowledged'

const CHARGE_META: { key: string; label: string; icon: React.ReactNode; prefix?: string; suffix?: string }[] = [
  { key: 'delivery',          label: 'Delivery',    icon: <Truck     className='h-4 w-4' />, prefix: 'SAR' },
  { key: 'return',            label: 'Return',      icon: <RotateCcw className='h-4 w-4' />, prefix: 'SAR' },
  { key: 'cod',               label: 'COD',         icon: <Banknote  className='h-4 w-4' />, prefix: 'SAR' },
  { key: 'warehousing',       label: 'Warehousing', icon: <Warehouse className='h-4 w-4' />, prefix: 'SAR' },
  { key: 'call_confirmation', label: 'Call Confirm',icon: <Phone     className='h-4 w-4' />, prefix: 'SAR' },
  { key: 'vat',               label: 'VAT',         icon: <Receipt   className='h-4 w-4' />, suffix: '%'   },
  { key: 'other',             label: 'Other',       icon: <Tag       className='h-4 w-4' />, prefix: 'SAR' },
]

function ChargesStrip({ charges }: { charges: Record<string, number | null> | null }) {
  const [hidden, setHidden] = useState(() => localStorage.getItem(CHARGES_ACK_KEY) === '1')

  const acknowledge = () => {
    localStorage.setItem(CHARGES_ACK_KEY, '1')
    setHidden(true)
  }

  const remindLater = () => setHidden(true)

  return (
    <div className='space-y-3'>
      <div className='grid grid-cols-4 gap-3 sm:grid-cols-7'>
        {CHARGE_META.map((m) => {
          const raw = charges?.[m.key]
          const val = raw !== null && raw !== undefined ? raw : 0
          const display = m.prefix
            ? `${m.prefix} ${val.toFixed(2)}`
            : `${val % 1 === 0 ? val : val.toFixed(2)}${m.suffix ?? ''}`
          return (
            <div
              key={m.key}
              className='flex flex-col justify-between rounded-lg border border-muted/60 bg-card p-3 shadow-sm'
            >
              <div className='flex items-center justify-between mb-2'>
                <p className='text-[10px] font-medium uppercase tracking-wide text-muted-foreground leading-none'>
                  {m.label}
                </p>
                <span className='text-muted-foreground/60 shrink-0'>{m.icon}</span>
              </div>
              <p className='text-sm font-bold tabular-nums'>{display}</p>
            </div>
          )
        })}
      </div>

      {!hidden && (
        <div className='flex flex-wrap items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 px-3.5 py-2.5 text-blue-800 dark:border-blue-800/60 dark:bg-blue-950/40 dark:text-blue-300'>
          <Info className='mt-0.5 h-4 w-4 shrink-0' />
          <p className='flex-1 text-xs leading-relaxed min-w-0'>
            These are the charges applied to your account for each order. They will be deducted from your payouts automatically. Contact your account manager if you have any questions.
          </p>
          <div className='flex shrink-0 items-center gap-2'>
            <button
              onClick={remindLater}
              className='rounded-md border border-blue-300 px-2.5 py-1 text-xs font-medium hover:bg-blue-100 dark:border-blue-700 dark:hover:bg-blue-900/50 transition-colors'
            >
              Remind me again
            </button>
            <button
              onClick={acknowledge}
              className='rounded-md bg-blue-700 px-2.5 py-1 text-xs font-medium text-white hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 transition-colors'
            >
              Acknowledged
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

const FULFILLMENT_BADGE: Record<string, string> = {
  fulfilled: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
  pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
  unfulfilled: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
}

const PAYMENT_DOT: Record<string, string> = {
  paid: 'bg-emerald-500',
  pending: 'bg-amber-500',
  refunded: 'bg-rose-500',
}

/** "5m ago", "3h ago", "2d ago", then a short date. */
function timeAgo(value: string) {
  const then = new Date(value).getTime()
  if (Number.isNaN(then)) return ''
  const minutes = Math.round((Date.now() - then) / 60000)
  if (minutes < 1) return 'just now'
  if (minutes < 60) return `${minutes}m ago`
  if (minutes < 60 * 24) return `${Math.round(minutes / 60)}h ago`
  if (minutes < 60 * 24 * 7) return `${Math.round(minutes / 1440)}d ago`
  return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', timeZone: BUSINESS_TIMEZONE }).format(then)
}

/**
 * The last five orders, sized for the narrow column beside the rhythm cards.
 * With the orders feature each row opens that order on the Orders page
 * (`?order=` deep link) and the header links to the full list; without it
 * the rows are plain, since the Orders page would refuse them.
 */
function RecentOrders({ orders, canOpen }: { orders: any[]; canOpen: boolean }) {
  return (
    <InsightCard
      eyebrow='Latest'
      title='Recent orders'
      description='Your last five orders'
      icon={<ShoppingCart />}
      accent='var(--viz-c2)'
      className='h-full'
      aside={
        canOpen && orders.length > 0 ? (
          <Link
            href='/portal/orders'
            className='inline-flex shrink-0 items-center gap-0.5 rounded-md px-1.5 py-1 text-[11px] font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground'
          >
            View all
            <ChevronRight className='h-3 w-3' />
          </Link>
        ) : undefined
      }
    >
      {orders.length === 0 ? (
        <InsightEmpty>
          No orders yet.
          {canOpen && (
            <>
              {' '}
              <Link href='/portal/orders' className='font-medium text-foreground underline-offset-2 hover:underline'>
                Create your first order
              </Link>
            </>
          )}
        </InsightEmpty>
      ) : (
        <ul className='-mx-2 space-y-0.5'>
          {orders.map((order) => {
            const body = (
              <>
                <div className='flex items-center justify-between gap-2'>
                  <span className='flex min-w-0 items-center gap-1.5'>
                    <span
                      title={order.financial_status ? `Payment: ${order.financial_status}` : undefined}
                      className={`h-1.5 w-1.5 shrink-0 rounded-full ${PAYMENT_DOT[order.financial_status] ?? 'bg-muted-foreground/40'}`}
                    />
                    <span className='truncate text-sm font-medium'>#{order.order_number}</span>
                  </span>
                  <span className='shrink-0 text-sm font-semibold tabular-nums'>
                    <span className='me-0.5 text-[10px] font-medium text-muted-foreground'>SAR</span>
                    {parseFloat(order.total).toLocaleString()}
                  </span>
                </div>
                <div className='mt-1 flex items-center justify-between gap-2'>
                  <span className='min-w-0 truncate text-xs text-muted-foreground'>
                    {order.customer_name}
                    {order.created_at && <span className='text-muted-foreground/70'> · {timeAgo(order.created_at)}</span>}
                  </span>
                  <Badge
                    variant='outline'
                    className={`shrink-0 text-[10px] capitalize ${
                      FULFILLMENT_BADGE[order.fulfillment_status] ??
                      'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400'
                    }`}
                  >
                    {order.fulfillment_status ?? 'unknown'}
                  </Badge>
                </div>
              </>
            )

            return (
              <li key={order.id}>
                {canOpen ? (
                  <Link
                    href={`/portal/orders?order=${encodeURIComponent(order.order_number)}`}
                    aria-label={`Open order ${order.order_number}`}
                    className='block rounded-lg px-2 py-2 transition-colors hover:bg-muted/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'
                  >
                    {body}
                  </Link>
                ) : (
                  <div className='px-2 py-2'>{body}</div>
                )}
              </li>
            )
          })}
        </ul>
      )}
    </InsightCard>
  )
}

export function PortalDashboard() {
  const { data, loading } = usePortalDashboard()
  const { auth } = usePage().props as any
  const charges = auth?.client?.charges ?? null

  return (
    <>
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main>
        <div className='mb-4'>
          <h1 className='text-2xl font-bold tracking-tight'>
            Welcome{data?.client?.company_name ? `, ${data.client.company_name}` : ''}
          </h1>
          <p className='text-muted-foreground'>
            {data?.client?.type_label && (
              <Badge variant='outline' className='mr-2'>{data.client.type_label}</Badge>
            )}
            Your portal overview
          </p>
        </div>

        {loading ? (
          <div className={STAT_GRID}>
            {Array.from({ length: 6 }).map((_, i) => (
              <StatCardSkeleton key={i} />
            ))}
          </div>
        ) : (
          <div className='space-y-6'>
            {/* Charges Strip */}
            <ChargesStrip charges={charges} />

            {/* Stats Cards — same solid tiles as the admin dashboard */}
            <div className={STAT_GRID}>
              <StatCard
                icon={<ShoppingCart className='h-3.5 w-3.5' />}
                label='Total orders'
                value={data?.stats?.total_orders ?? 0}
                tone='teal'
                texture='dots'
                hint='Every order in your account.'
              />
              <StatCard
                icon={<Hourglass className='h-3.5 w-3.5' />}
                label='Pending orders'
                value={data?.stats?.pending_orders ?? 0}
                tone='orange'
                texture='dots'
                hint='Orders still waiting to be fulfilled.'
              />
              <StatCard
                icon={<PackageCheck className='h-3.5 w-3.5' />}
                label='Delivered'
                value={Number(
                  data?.stats?.by_shipment_status?.find((s: { status: string }) => s.status === 'delivered')?.count ?? 0
                )}
                tone='blue'
                texture='dots'
                hint='Shipments delivered to your customers.'
              />
              <StatCard
                icon={<Package className='h-3.5 w-3.5' />}
                label='Products'
                value={data?.stats?.total_products ?? 0}
                tone='emerald'
                texture='grid'
                hint='Every product in your catalogue.'
              />
              <StatCard
                icon={<BadgeCheck className='h-3.5 w-3.5' />}
                label='Verified products'
                value={data?.stats?.verified_products ?? 0}
                tone='violet'
                texture='grid'
                hint='Products approved by KSA Drop.'
              />
              <StatCard
                icon={<Clock className='h-3.5 w-3.5' />}
                label='Pending verification'
                value={data?.stats?.pending_verification ?? 0}
                tone='rose'
                texture='grid'
                hint='Awaiting admin review.'
              />
            </div>

            {/* Charts — the admin dashboard's insights, for this client's orders */}
            <InsightsSection scope='portal' rhythmSide={
                <RecentOrders
                  orders={data?.recent_orders ?? []}
                  canOpen={(data?.client?.portal_features ?? []).includes('orders')}
                />
              } />

          </div>
        )}
      </Main>
    </>
  )
}

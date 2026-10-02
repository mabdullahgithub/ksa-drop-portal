import { Link } from '@inertiajs/react'
import {
  ShoppingCart,
  Users,
  UserCheck,
  UserMinus,
  UserX,
  Truck,
  Warehouse,
  TrendingUp,
  CalendarClock,
  Receipt,
  PackageSearch,
  ArrowUpRight,
  RefreshCw,
  Banknote,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Header } from '@/components/layout/header'
import { StatCard, StatCardSkeleton, compactNumber } from '@/components/stat-card'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { ShipmentStatusCardsView } from '@/features/orders/components/shipment-status-cards'
import { TagStatCardsView } from '@/features/orders/components/tag-stat-cards'
import { InboxStats } from '@/features/whatsapp/components/inbox-stats'
import { useDashboard } from '@/hooks/useDashboard'


function formatCurrency(value: number) {
  return `SAR ${value.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`
}

/**
 * One labelled band of stat cards. The dashboard is read left-to-right in
 * three passes — orders, then the confirmation inbox, then clients — so each
 * band gets a heading and a way through to the page it summarises.
 */
function StatSection({
  title,
  description,
  href,
  linkLabel,
  children,
}: {
  title: string
  description: string
  href?: string
  linkLabel?: string
  children: React.ReactNode
}) {
  return (
    <section>
      <div className='mb-2 flex items-end justify-between gap-4'>
        <div>
          <h2 className='text-sm font-semibold tracking-tight'>{title}</h2>
          <p className='text-xs text-muted-foreground'>{description}</p>
        </div>
        {href && (
          <Link
            href={href}
            className='shrink-0 text-xs font-medium text-muted-foreground hover:text-foreground'
          >
            <span className='inline-flex items-center gap-1'>
              {linkLabel ?? 'View all'}
              <ArrowUpRight className='h-3 w-3' />
            </span>
          </Link>
        )}
      </div>
      {children}
    </section>
  )
}

/** Six placeholders in the same grid the real cards land in. */
function StatRowSkeleton() {
  return (
    <div className={STAT_GRID}>
      {Array.from({ length: 6 }).map((_, i) => (
        <StatCardSkeleton key={i} />
      ))}
    </div>
  )
}

/** Shared by all three bands so the columns line up down the page. */
const STAT_GRID = 'grid grid-cols-2 gap-x-2 gap-y-0.5 sm:grid-cols-3 lg:grid-cols-6'

export function Dashboard() {
  const { data, loading } = useDashboard()
  const { orders, whatsapp, clients } = data

  return (
    <>
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main>
        {/* Page heading */}
        <div className='mb-6 flex items-center justify-between'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>Dashboard</h1>
            <p className='text-sm text-muted-foreground'>Your operations at a glance</p>
          </div>
          <Button variant='outline' size='sm' onClick={() => window.location.reload()} className='gap-2'>
            <RefreshCw className='h-3.5 w-3.5' />
            Refresh
          </Button>
        </div>

        <div className='space-y-8'>
          {/* ── Orders ── */}
          {(loading || orders) && (
            <StatSection
              title='Orders'
              description='Volume and value across every client'
              href='/orders'
              linkLabel='All orders'
            >
              {loading || !orders ? (
                <StatRowSkeleton />
              ) : (
                <div className={STAT_GRID}>
                  <StatCard
                    icon={<ShoppingCart className='h-3.5 w-3.5' />}
                    label='Total orders'
                    value={orders.total_orders}
                    texture='dots'
                    tone='teal'
                    href='/orders'
                    hint='Every order in the portal, across all clients.'
                  />
                  <StatCard
                    icon={<CalendarClock className='h-3.5 w-3.5' />}
                    label='Orders today'
                    value={orders.today_orders}
                    texture='dots'
                    tone='blue'
                    href='/orders'
                    hint='Orders created since midnight, in your timezone.'
                  />
                  <StatCard
                    icon={<TrendingUp className='h-3.5 w-3.5' />}
                    label='Total revenue'
                    value={compactNumber(Math.round(orders.total_revenue))}
                    pre='SAR'
                    texture='dots'
                    tone='emerald'
                    hint={`Order value to date — ${formatCurrency(orders.total_revenue)}.`}
                  />
                  <StatCard
                    icon={<Banknote className='h-3.5 w-3.5' />}
                    label="Today's revenue"
                    value={compactNumber(Math.round(orders.today_revenue))}
                    pre='SAR'
                    texture='dots'
                    tone='violet'
                    hint={`From ${orders.today_orders} orders today — ${formatCurrency(orders.today_revenue)}.`}
                  />
                  <StatCard
                    icon={<Receipt className='h-3.5 w-3.5' />}
                    label='Avg order value'
                    value={compactNumber(Math.round(orders.average_order_value))}
                    pre='SAR'
                    texture='dots'
                    tone='orange'
                    hint={`Total revenue divided by order count — ${formatCurrency(orders.average_order_value)}.`}
                  />
                  <StatCard
                    icon={<PackageSearch className='h-3.5 w-3.5' />}
                    label='Awaiting shipment'
                    value={orders.unassigned_orders}
                    sub={`/ ${compactNumber(orders.total_orders)}`}
                    texture='dots'
                    tone='rose'
                    href='/orders'
                    hint={`No courier assigned yet. ${orders.assigned_orders.toLocaleString()} orders have a shipment.`}
                  />
                </div>
              )}

              {/* The orders page's own two card grids, rendered straight from
                  the statistics already loaded above. */}
              <div className='mt-5'>
                <TagStatCardsView tags={orders?.by_tag ?? []} loading={loading} />
              </div>

              <div className='mt-5'>
                <div className='mb-3 flex items-center gap-1.5'>
                  <Truck className='h-3.5 w-3.5 text-muted-foreground' />
                  <h3 className='text-sm font-semibold'>Shipment status distribution</h3>
                </div>
                <ShipmentStatusCardsView
                  byStatus={orders?.by_shipment_status ?? null}
                  loading={loading}
                />
              </div>
            </StatSection>
          )}

          {/* ── WhatsApp ── */}
          {(loading || whatsapp) && (
            <StatSection
              title='WhatsApp confirmations'
              description='Order confirmations opened when a call goes unanswered'
              href='/whatsapp'
              linkLabel='Open inbox'
            >
              {/* Literally the inbox's own KPI row, so the two pages can never
                  drift apart — it just links back to the inbox from here. */}
              {loading || !whatsapp ? <StatRowSkeleton /> : <InboxStats stats={whatsapp} href='/whatsapp' />}
            </StatSection>
          )}

          {/* ── Clients ── */}
          {(loading || clients) && (
            <StatSection
              title='Clients'
              description='Account status and the mix of services they buy'
              href='/client'
              linkLabel='All clients'
            >
              {loading || !clients ? (
                <StatRowSkeleton />
              ) : (
                <div className={STAT_GRID}>
                  <StatCard
                    icon={<Users className='h-3.5 w-3.5' />}
                    label='Total clients'
                    value={clients.total_clients}
                    texture='grid'
                    tone='teal'
                    href='/client'
                  />
                  <StatCard
                    icon={<UserCheck className='h-3.5 w-3.5' />}
                    label='Active'
                    value={clients.active_clients}
                    texture='grid'
                    tone='emerald'
                    href='/client'
                    hint='Can sign in and place orders.'
                  />
                  <StatCard
                    icon={<UserMinus className='h-3.5 w-3.5' />}
                    label='Inactive'
                    value={clients.inactive_clients}
                    texture='grid'
                    tone='orange'
                    href='/client'
                    hint='Dormant accounts — no access until reactivated.'
                  />
                  <StatCard
                    icon={<UserX className='h-3.5 w-3.5' />}
                    label='Suspended'
                    value={clients.suspended_clients}
                    texture='grid'
                    tone='rose'
                    href='/client'
                    hint='Blocked accounts, usually over unpaid balances.'
                  />
                  <StatCard
                    icon={<Truck className='h-3.5 w-3.5' />}
                    label='Dropshippers'
                    value={clients.dropshippers_count}
                    texture='grid'
                    tone='blue'
                    href='/client'
                    hint='Ship per order, no stock held with us.'
                  />
                  <StatCard
                    icon={<Warehouse className='h-3.5 w-3.5' />}
                    label='Fulfilment'
                    value={clients.fulfilment_count}
                    texture='grid'
                    tone='violet'
                    href='/client'
                    hint='Stock stored in our warehouse. A client can be both, so the two do not add up to the total.'
                  />
                </div>
              )}
            </StatSection>
          )}

        </div>
      </Main>
    </>
  )
}

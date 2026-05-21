import { useState } from 'react'
import { usePage } from '@inertiajs/react'
import { ShoppingCart, DollarSign, Package, Clock, Info, Truck, RotateCcw, Banknote, Warehouse, Phone, Receipt, Tag } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { usePortalDashboard } from '@/hooks/usePortal'

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

      <Main fixed>
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
          <div className='grid gap-4 md:grid-cols-2 lg:grid-cols-4'>
            {Array.from({ length: 4 }).map((_, i) => (
              <Card key={i}>
                <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                  <div className='h-4 w-24 animate-pulse rounded bg-muted' />
                </CardHeader>
                <CardContent>
                  <div className='h-7 w-12 animate-pulse rounded bg-muted' />
                </CardContent>
              </Card>
            ))}
          </div>
        ) : (
          <div className='space-y-6'>
            {/* Charges Strip */}
            <ChargesStrip charges={charges} />

            {/* Stats Cards */}
            <div className='grid gap-4 md:grid-cols-2 lg:grid-cols-4'>
              <Card>
                <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                  <CardTitle className='text-sm font-medium'>Total Orders</CardTitle>
                  <ShoppingCart className='h-4 w-4 text-muted-foreground' />
                </CardHeader>
                <CardContent>
                  <div className='text-2xl font-bold'>{data?.stats?.total_orders ?? 0}</div>
                  <p className='text-xs text-muted-foreground'>{data?.stats?.pending_orders ?? 0} pending</p>
                </CardContent>
              </Card>
              <Card>
                <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                  <CardTitle className='text-sm font-medium'>Total Revenue</CardTitle>
                  <DollarSign className='h-4 w-4 text-muted-foreground' />
                </CardHeader>
                <CardContent>
                  <div className='text-2xl font-bold'>SAR {(data?.stats?.total_revenue ?? 0).toLocaleString()}</div>
                  <p className='text-xs text-muted-foreground'>From paid orders</p>
                </CardContent>
              </Card>
              <Card>
                <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                  <CardTitle className='text-sm font-medium'>Products</CardTitle>
                  <Package className='h-4 w-4 text-muted-foreground' />
                </CardHeader>
                <CardContent>
                  <div className='text-2xl font-bold'>{data?.stats?.total_products ?? 0}</div>
                  <p className='text-xs text-muted-foreground'>{data?.stats?.verified_products ?? 0} verified</p>
                </CardContent>
              </Card>
              <Card>
                <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                  <CardTitle className='text-sm font-medium'>Pending Verification</CardTitle>
                  <Clock className='h-4 w-4 text-muted-foreground' />
                </CardHeader>
                <CardContent>
                  <div className='text-2xl font-bold'>{data?.stats?.pending_verification ?? 0}</div>
                  <p className='text-xs text-muted-foreground'>Awaiting admin review</p>
                </CardContent>
              </Card>
            </div>

            {/* Recent Orders */}
            {data?.recent_orders && data.recent_orders.length > 0 && (
              <Card>
                <CardHeader>
                  <CardTitle className='text-sm font-medium'>Recent Orders</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className='space-y-3'>
                    {data.recent_orders.map((order: any) => (
                      <div key={order.id} className='flex items-center justify-between border-b pb-2 last:border-0'>
                        <div>
                          <span className='font-medium text-sm'>{order.order_number}</span>
                          <span className='text-xs text-muted-foreground ml-2'>{order.customer_name}</span>
                        </div>
                        <div className='flex items-center gap-2'>
                          <Badge variant='outline' className={`text-xs capitalize ${{
                            fulfilled: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                            pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                            unfulfilled: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                            cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                          }[order.fulfillment_status as string] || 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400'}`}>{order.fulfillment_status}</Badge>
                          <span className='text-sm font-medium'>SAR {parseFloat(order.total).toLocaleString()}</span>
                        </div>
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            )}
          </div>
        )}
      </Main>
    </>
  )
}

import { Link } from '@inertiajs/react'
import {
  ShoppingCart,
  Users,
  TrendingUp,
  AlertCircle,
  ArrowRight,
  PlusCircle,
  FileDown,
  UserPlus,
  RefreshCw,
  Package,
  Banknote,
  CreditCard,
  Wallet,
} from 'lucide-react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Separator } from '@/components/ui/separator'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { useDashboard } from '@/hooks/useDashboard'


const financialColors: Record<string, string> = {
  paid:               'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
  pending:            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
  refunded:           'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
  partially_refunded: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
}

function paymentIcon(method: string) {
  const m = method?.toLowerCase() ?? ''
  if (m.includes('cod') || m.includes('cash')) return <Banknote className='h-4 w-4 text-amber-500' />
  if (m.includes('card') || m.includes('credit') || m.includes('debit')) return <CreditCard className='h-4 w-4 text-blue-500' />
  return <Wallet className='h-4 w-4 text-muted-foreground' />
}

function StatCardSkeleton() {
  return (
    <Card>
      <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
        <Skeleton className='h-4 w-28' />
        <Skeleton className='h-4 w-4 rounded' />
      </CardHeader>
      <CardContent>
        <Skeleton className='h-7 w-20 mb-1' />
        <Skeleton className='h-3 w-32' />
      </CardContent>
    </Card>
  )
}

function formatCurrency(value: number) {
  return `SAR ${value.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`
}

export function Dashboard() {
  const { data, loading } = useDashboard()
  const { stats } = data

  const totalPaymentMethod = stats?.by_payment_method.reduce((s, x) => s + x.count, 0) || 1
  const totalFinancial     = stats?.by_financial_status.reduce((s, x) => s + x.count, 0) || 1

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

        <div className='space-y-6'>
          {/* ── Stat cards ── */}
          <div className='grid gap-4 sm:grid-cols-2 lg:grid-cols-4'>
            {loading ? (
              Array.from({ length: 4 }).map((_, i) => <StatCardSkeleton key={i} />)
            ) : (
              <>
                <Card>
                  <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                    <CardTitle className='text-sm font-medium'>Total Orders</CardTitle>
                    <ShoppingCart className='h-4 w-4 text-muted-foreground' />
                  </CardHeader>
                  <CardContent>
                    <div className='text-2xl font-bold'>{(stats?.total_orders ?? 0).toLocaleString()}</div>
                    <p className='text-xs text-muted-foreground'>
                      {stats?.today_orders ?? 0} today
                    </p>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                    <CardTitle className='text-sm font-medium'>Total Revenue</CardTitle>
                    <TrendingUp className='h-4 w-4 text-muted-foreground' />
                  </CardHeader>
                  <CardContent>
                    <div className='text-2xl font-bold'>{formatCurrency(stats?.total_revenue ?? 0)}</div>
                    <p className='text-xs text-muted-foreground'>
                      Avg {formatCurrency(stats?.average_order_value ?? 0)} per order
                    </p>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                    <CardTitle className='text-sm font-medium'>Total Clients</CardTitle>
                    <Users className='h-4 w-4 text-muted-foreground' />
                  </CardHeader>
                  <CardContent>
                    <div className='text-2xl font-bold'>{(stats?.total_clients ?? 0).toLocaleString()}</div>
                    <p className='text-xs text-muted-foreground'>
                      {stats?.active_clients ?? 0} active
                    </p>
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                    <CardTitle className='text-sm font-medium'>Today's Revenue</CardTitle>
                    <AlertCircle className='h-4 w-4 text-muted-foreground' />
                  </CardHeader>
                  <CardContent>
                    <div className='text-2xl font-bold'>{formatCurrency(stats?.today_revenue ?? 0)}</div>
                    <p className='text-xs text-muted-foreground'>
                      From {stats?.today_orders ?? 0} orders today
                    </p>
                  </CardContent>
                </Card>
              </>
            )}
          </div>

          {/* ── Middle row: status breakdown + quick actions ── */}
          <div className='grid grid-cols-1 gap-4 lg:grid-cols-3'>

            {/* Payment method breakdown */}
            <Card className='lg:col-span-1'>
              <CardHeader>
                <CardTitle className='text-sm font-medium'>Payment Methods</CardTitle>
                <CardDescription>COD vs prepaid split</CardDescription>
              </CardHeader>
              <CardContent>
                {loading ? (
                  <div className='space-y-3'>
                    {Array.from({ length: 4 }).map((_, i) => (
                      <div key={i} className='flex items-center justify-between'>
                        <Skeleton className='h-4 w-24' />
                        <Skeleton className='h-4 w-10' />
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className='space-y-3'>
                    {(stats?.by_payment_method ?? [])
                      .sort((a, b) => b.count - a.count)
                      .map((item) => {
                        const pct = Math.round((item.count / totalPaymentMethod) * 100)
                        const label = item.payment_method || 'Unknown'
                        return (
                          <div key={label}>
                            <div className='flex items-center justify-between mb-1'>
                              <div className='flex items-center gap-2'>
                                {paymentIcon(label)}
                                <span className='text-sm capitalize'>{label}</span>
                              </div>
                              <div className='flex items-center gap-2'>
                                <span className='text-sm font-semibold tabular-nums'>{item.count.toLocaleString()}</span>
                                <span className='text-xs text-muted-foreground w-8 text-right'>{pct}%</span>
                              </div>
                            </div>
                            <div className='h-1.5 w-full rounded-full bg-muted overflow-hidden'>
                              <div
                                className='h-full rounded-full bg-primary transition-all'
                                style={{ width: `${pct}%` }}
                              />
                            </div>
                          </div>
                        )
                      })}
                    {!loading && (stats?.by_payment_method ?? []).length === 0 && (
                      <p className='text-sm text-muted-foreground'>No orders yet.</p>
                    )}
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Financial status breakdown */}
            <Card className='lg:col-span-1'>
              <CardHeader>
                <CardTitle className='text-sm font-medium'>Financial Status</CardTitle>
                <CardDescription>Payment status breakdown</CardDescription>
              </CardHeader>
              <CardContent>
                {loading ? (
                  <div className='space-y-3'>
                    {Array.from({ length: 4 }).map((_, i) => (
                      <div key={i} className='flex items-center justify-between'>
                        <Skeleton className='h-4 w-24' />
                        <Skeleton className='h-4 w-10' />
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className='space-y-3'>
                    {(stats?.by_financial_status ?? []).map((item) => {
                      const pct = Math.round((item.count / totalFinancial) * 100)
                      return (
                        <div key={item.financial_status}>
                          <div className='flex items-center justify-between mb-1'>
                            <span className='text-sm capitalize'>{item.financial_status.replace('_', ' ')}</span>
                            <div className='flex items-center gap-2'>
                              <span className='text-sm font-semibold tabular-nums'>{item.count.toLocaleString()}</span>
                              <Badge
                                variant='outline'
                                className={`text-xs shrink-0 ${financialColors[item.financial_status] ?? ''}`}
                              >
                                {pct}%
                              </Badge>
                            </div>
                          </div>
                          <div className='h-1.5 w-full rounded-full bg-muted overflow-hidden'>
                            <div
                              className='h-full rounded-full bg-primary/70 transition-all'
                              style={{ width: `${pct}%` }}
                            />
                          </div>
                        </div>
                      )
                    })}
                    {!loading && (stats?.by_financial_status ?? []).length === 0 && (
                      <p className='text-sm text-muted-foreground'>No orders yet.</p>
                    )}
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Quick actions */}
            <Card className='lg:col-span-1'>
              <CardHeader>
                <CardTitle className='text-sm font-medium'>Quick Actions</CardTitle>
                <CardDescription>Common tasks</CardDescription>
              </CardHeader>
              <CardContent>
                <div className='flex flex-col gap-2'>
                  <Link href='/client'>
                    <Button variant='outline' className='w-full justify-start gap-3 h-9'>
                      <UserPlus className='h-4 w-4 shrink-0' />
                      <span className='text-sm'>Add New Client</span>
                    </Button>
                  </Link>
                  <Link href='/orders'>
                    <Button variant='outline' className='w-full justify-start gap-3 h-9'>
                      <PlusCircle className='h-4 w-4 shrink-0' />
                      <span className='text-sm'>Manage Orders</span>
                    </Button>
                  </Link>
                  <Link href='/inventory'>
                    <Button variant='outline' className='w-full justify-start gap-3 h-9'>
                      <Package className='h-4 w-4 shrink-0' />
                      <span className='text-sm'>View Inventory</span>
                    </Button>
                  </Link>
                  <Button
                    variant='outline'
                    className='w-full justify-start gap-3 h-9'
                    onClick={() => { window.location.href = '/api/orders/export' }}
                  >
                    <FileDown className='h-4 w-4 shrink-0' />
                    <span className='text-sm'>Export Orders</span>
                  </Button>
                  <Separator className='my-1' />
                  <Link href='/admin/email-settings'>
                    <Button variant='ghost' className='w-full justify-start gap-3 h-9 text-muted-foreground'>
                      <ArrowRight className='h-4 w-4 shrink-0' />
                      <span className='text-sm'>Email Settings</span>
                    </Button>
                  </Link>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </Main>
    </>
  )
}

import { ShoppingCart, DollarSign, Package, Clock } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { ConfigDrawer } from '@/components/config-drawer'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { usePortalDashboard } from '@/hooks/usePortal'

export function PortalDashboard() {
  const { data, loading } = usePortalDashboard()

  return (
    <>
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ConfigDrawer />
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
                          <Badge variant='outline' className='text-xs capitalize'>{order.fulfillment_status}</Badge>
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

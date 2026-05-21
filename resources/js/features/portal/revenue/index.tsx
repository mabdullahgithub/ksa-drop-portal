import { DollarSign, TrendingUp, Percent, Calendar } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ConfigDrawer } from '@/components/config-drawer'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { usePortalRevenue } from '@/hooks/usePortal'
import { useState } from 'react'

export function PortalRevenue() {
  const [period, setPeriod] = useState('6months')
  const { data, loading, refresh } = usePortalRevenue(period)

  const handlePeriodChange = (value: string) => {
    setPeriod(value)
    refresh(value)
  }

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
        <div className='mb-4 flex items-center justify-between'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>Revenue</h1>
            <p className='text-muted-foreground'>Track your earnings and commission</p>
          </div>
          <Select value={period} onValueChange={handlePeriodChange}>
            <SelectTrigger className='w-[140px]'>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value='3months'>3 Months</SelectItem>
              <SelectItem value='6months'>6 Months</SelectItem>
              <SelectItem value='12months'>12 Months</SelectItem>
            </SelectContent>
          </Select>
        </div>

        {loading ? (
          <div className='grid gap-4 md:grid-cols-2 lg:grid-cols-4'>
            {Array.from({ length: 4 }).map((_, i) => (
              <Card key={i}>
                <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                  <div className='h-4 w-24 animate-pulse rounded bg-muted' />
                </CardHeader>
                <CardContent>
                  <div className='h-7 w-20 animate-pulse rounded bg-muted' />
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
                  <CardTitle className='text-sm font-medium'>Total Revenue</CardTitle>
                  <DollarSign className='h-4 w-4 text-muted-foreground' />
                </CardHeader>
                <CardContent>
                  <div className='text-2xl font-bold'>SAR {(data?.total_revenue ?? 0).toLocaleString()}</div>
                  <p className='text-xs text-muted-foreground'>All time</p>
                </CardContent>
              </Card>
              <Card>
                <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                  <CardTitle className='text-sm font-medium'>This Month</CardTitle>
                  <TrendingUp className='h-4 w-4 text-muted-foreground' />
                </CardHeader>
                <CardContent>
                  <div className='text-2xl font-bold'>SAR {(data?.this_month_revenue ?? 0).toLocaleString()}</div>
                  <p className='text-xs text-muted-foreground'>Current month earnings</p>
                </CardContent>
              </Card>
              {data?.commission_rate && (
                <Card>
                  <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                    <CardTitle className='text-sm font-medium'>Commission Rate</CardTitle>
                    <Percent className='h-4 w-4 text-muted-foreground' />
                  </CardHeader>
                  <CardContent>
                    <div className='text-2xl font-bold'>{data.commission_rate}%</div>
                    <p className='text-xs text-muted-foreground'>Per sale</p>
                  </CardContent>
                </Card>
              )}
              {data?.total_commission !== null && (
                <Card>
                  <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                    <CardTitle className='text-sm font-medium'>Total Commission</CardTitle>
                    <DollarSign className='h-4 w-4 text-muted-foreground' />
                  </CardHeader>
                  <CardContent>
                    <div className='text-2xl font-bold'>SAR {(data?.total_commission ?? 0).toLocaleString()}</div>
                    <p className='text-xs text-muted-foreground'>Earned from sales</p>
                  </CardContent>
                </Card>
              )}
            </div>

            {/* Monthly Breakdown */}
            {data?.monthly_breakdown && data.monthly_breakdown.length > 0 && (
              <Card>
                <CardHeader>
                  <CardTitle className='text-sm font-medium flex items-center gap-2'>
                    <Calendar className='h-4 w-4' />
                    Monthly Breakdown
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className='space-y-3'>
                    {data.monthly_breakdown.map((item: any) => (
                      <div key={item.month} className='flex items-center justify-between border-b pb-2 last:border-0'>
                        <span className='text-sm font-medium'>{item.month}</span>
                        <div className='flex items-center gap-4'>
                          <span className='text-xs text-muted-foreground'>{item.orders_count} orders</span>
                          <span className='text-sm font-bold'>SAR {parseFloat(item.revenue).toLocaleString()}</span>
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

import { DollarSign, TrendingUp, Percent, ShoppingCart } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { usePortalRevenue } from '@/hooks/usePortal'
import { useState } from 'react'
import {
  AreaChart,
  Area,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts'

function formatMonth(ym: string) {
  const [year, month] = ym.split('-')
  return new Date(Number(year), Number(month) - 1).toLocaleString('default', { month: 'short', year: '2-digit' })
}

function formatSAR(value: number) {
  if (value >= 1000) return `SAR ${(value / 1000).toFixed(1)}k`
  return `SAR ${value.toLocaleString()}`
}

function RevenueTooltip({ active, payload, label }: any) {
  if (!active || !payload?.length) return null
  return (
    <div className='rounded-lg border bg-background p-3 shadow-sm text-sm'>
      <p className='font-medium mb-1'>{label}</p>
      {payload.map((entry: any) => (
        <p key={entry.dataKey} style={{ color: entry.color }}>
          {entry.name}: {entry.dataKey === 'revenue' ? `SAR ${Number(entry.value).toLocaleString()}` : entry.value}
        </p>
      ))}
    </div>
  )
}

export function PortalRevenue() {
  const [period, setPeriod] = useState('6months')
  const { data, loading, refresh } = usePortalRevenue(period)

  const handlePeriodChange = (value: string) => {
    setPeriod(value)
    refresh(value)
  }

  const chartData = (data?.monthly_breakdown ?? []).map((item: any) => ({
    month: formatMonth(item.month),
    revenue: parseFloat(item.revenue),
    orders: Number(item.orders_count),
  }))

  return (
    <>
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main>
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
          <div className='space-y-6'>
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
            <div className='grid gap-4 md:grid-cols-2'>
              {[0, 1].map((i) => (
                <Card key={i}>
                  <CardHeader>
                    <div className='h-4 w-32 animate-pulse rounded bg-muted' />
                  </CardHeader>
                  <CardContent>
                    <div className='h-[260px] animate-pulse rounded bg-muted' />
                  </CardContent>
                </Card>
              ))}
            </div>
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
              {data?.total_commission != null && (
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

            {/* Charts */}
            {chartData.length > 0 && (
              <div className='grid gap-4 md:grid-cols-2'>
                {/* Revenue Area Chart */}
                <Card>
                  <CardHeader>
                    <CardTitle className='text-base'>Monthly Revenue</CardTitle>
                    <CardDescription>Revenue trend over the selected period</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <ResponsiveContainer width='100%' height={260}>
                      <AreaChart data={chartData} margin={{ top: 4, right: 8, left: 0, bottom: 0 }}>
                        <defs>
                          <linearGradient id='revenueGradient' x1='0' y1='0' x2='0' y2='1'>
                            <stop offset='5%' stopColor='hsl(var(--primary))' stopOpacity={0.3} />
                            <stop offset='95%' stopColor='hsl(var(--primary))' stopOpacity={0} />
                          </linearGradient>
                        </defs>
                        <CartesianGrid strokeDasharray='3 3' stroke='hsl(var(--border))' vertical={false} />
                        <XAxis
                          dataKey='month'
                          tick={{ fontSize: 12, fill: 'hsl(var(--muted-foreground))' }}
                          axisLine={false}
                          tickLine={false}
                        />
                        <YAxis
                          tickFormatter={formatSAR}
                          tick={{ fontSize: 11, fill: 'hsl(var(--muted-foreground))' }}
                          axisLine={false}
                          tickLine={false}
                          width={72}
                        />
                        <Tooltip content={<RevenueTooltip />} />
                        <Area
                          type='monotone'
                          dataKey='revenue'
                          name='Revenue'
                          stroke='hsl(var(--primary))'
                          strokeWidth={2}
                          fill='url(#revenueGradient)'
                          dot={{ r: 3, fill: 'hsl(var(--primary))' }}
                          activeDot={{ r: 5 }}
                        />
                      </AreaChart>
                    </ResponsiveContainer>
                  </CardContent>
                </Card>

                {/* Orders Bar Chart */}
                <Card>
                  <CardHeader>
                    <CardTitle className='text-base'>Monthly Orders</CardTitle>
                    <CardDescription>Number of orders per month</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <ResponsiveContainer width='100%' height={260}>
                      <BarChart data={chartData} margin={{ top: 4, right: 8, left: 0, bottom: 0 }} barCategoryGap='30%'>
                        <CartesianGrid strokeDasharray='3 3' stroke='hsl(var(--border))' vertical={false} />
                        <XAxis
                          dataKey='month'
                          tick={{ fontSize: 12, fill: 'hsl(var(--muted-foreground))' }}
                          axisLine={false}
                          tickLine={false}
                        />
                        <YAxis
                          allowDecimals={false}
                          tick={{ fontSize: 11, fill: 'hsl(var(--muted-foreground))' }}
                          axisLine={false}
                          tickLine={false}
                          width={40}
                        />
                        <Tooltip content={<RevenueTooltip />} />
                        <Bar
                          dataKey='orders'
                          name='Orders'
                          fill='hsl(var(--primary))'
                          radius={[4, 4, 0, 0]}
                          opacity={0.85}
                        />
                      </BarChart>
                    </ResponsiveContainer>
                  </CardContent>
                </Card>
              </div>
            )}

            {/* Monthly breakdown table */}
            {chartData.length > 0 && (
              <Card>
                <CardHeader>
                  <CardTitle className='text-base flex items-center gap-2'>
                    <ShoppingCart className='h-4 w-4' />
                    Monthly Breakdown
                  </CardTitle>
                </CardHeader>
                <CardContent className='p-0'>
                  <table className='w-full text-sm'>
                    <thead>
                      <tr className='border-b'>
                        <th className='text-left px-6 py-3 font-medium text-muted-foreground'>Month</th>
                        <th className='text-right px-6 py-3 font-medium text-muted-foreground'>Orders</th>
                        <th className='text-right px-6 py-3 font-medium text-muted-foreground'>Revenue</th>
                      </tr>
                    </thead>
                    <tbody>
                      {(data?.monthly_breakdown ?? []).map((item: any, idx: number) => (
                        <tr key={item.month} className={idx % 2 === 0 ? 'bg-muted/30' : ''}>
                          <td className='px-6 py-3 font-medium'>{formatMonth(item.month)}</td>
                          <td className='px-6 py-3 text-right text-muted-foreground'>{item.orders_count}</td>
                          <td className='px-6 py-3 text-right font-semibold'>SAR {parseFloat(item.revenue).toLocaleString()}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </CardContent>
              </Card>
            )}
          </div>
        )}
      </Main>
    </>
  )
}

import { TrendingUp, ShoppingBag, Wallet, AlertCircle } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { usePortalFinance } from '@/hooks/usePortal'
import { FinancePaymentsTable } from './components/finance-payments-table'
import { FinanceMonthlyBreakdown } from './components/finance-monthly-breakdown'
import { FinanceGuideBook } from './components/finance-guide-book'

export function PortalFinance() {
  const { data, loading, filters, updateFilters, updatePage } = usePortalFinance()

  const handlePeriodChange = (value: string) => {
    updateFilters({ period: value, page: 1 })
  }

  const summary = data?.summary
  const balanceOwed = summary?.balance_owed ?? 0

  return (
    <>
      <Header>
        <Search className='me-auto' />
        <ThemeSwitch />
        <NotificationsDropdown />
        <ProfileDropdown />
      </Header>

      <Main>
        <div className='mb-4 flex items-center justify-between gap-3 flex-wrap'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>Finance</h1>
            <p className='text-muted-foreground'>Your earnings, profit and payment history</p>
          </div>
          <div className='flex items-center gap-2'>
            {data && (
              <FinanceGuideBook
                clientType={data.client_type ?? []}
                charges={data.charges ?? {}}
              />
            )}
            <Select value={filters.period || '6months'} onValueChange={handlePeriodChange}>
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
        </div>

        {loading && !data ? (
          <div className='space-y-6'>
            <div className='grid gap-4 md:grid-cols-2 lg:grid-cols-4'>
              {Array.from({ length: 4 }).map((_, i) => (
                <Card key={i}>
                  <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                    <div className='h-4 w-24 animate-pulse rounded bg-muted' />
                  </CardHeader>
                  <CardContent>
                    <div className='h-7 w-20 animate-pulse rounded bg-muted' />
                    <div className='mt-1 h-3 w-28 animate-pulse rounded bg-muted' />
                  </CardContent>
                </Card>
              ))}
            </div>
            <Card>
              <CardContent className='pt-6'>
                <div className='h-40 animate-pulse rounded bg-muted' />
              </CardContent>
            </Card>
          </div>
        ) : (
          <div className='space-y-6'>
            {/* Summary Cards */}
            <div className='grid gap-4 md:grid-cols-2 lg:grid-cols-4'>
              <Card>
                <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                  <CardTitle className='text-sm font-medium'>Total Sold</CardTitle>
                  <ShoppingBag className='h-4 w-4 text-muted-foreground' />
                </CardHeader>
                <CardContent>
                  <div className='text-2xl font-bold'>
                    SAR {(summary?.total_sold_value ?? 0).toLocaleString()}
                  </div>
                  <p className='text-xs text-muted-foreground'>
                    {summary?.total_sold_count ?? 0} orders
                  </p>
                </CardContent>
              </Card>

              <Card>
                <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                  <CardTitle className='text-sm font-medium'>Your Profit</CardTitle>
                  <TrendingUp className='h-4 w-4 text-muted-foreground' />
                </CardHeader>
                <CardContent>
                  <div className={`text-2xl font-bold ${(summary?.total_profit ?? 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                    SAR {(summary?.total_profit ?? 0).toLocaleString()}
                  </div>
                  <p className='text-xs text-muted-foreground'>After charges &amp; costs</p>
                </CardContent>
              </Card>

              <Card>
                <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                  <CardTitle className='text-sm font-medium'>Total Received</CardTitle>
                  <Wallet className='h-4 w-4 text-muted-foreground' />
                </CardHeader>
                <CardContent>
                  <div className='text-2xl font-bold text-blue-600 dark:text-blue-400'>
                    SAR {(summary?.total_received ?? 0).toLocaleString()}
                  </div>
                  <p className='text-xs text-muted-foreground'>Transferred to you</p>
                </CardContent>
              </Card>

              <Card>
                <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                  <CardTitle className='text-sm font-medium'>Balance Owed</CardTitle>
                  <AlertCircle className='h-4 w-4 text-muted-foreground' />
                </CardHeader>
                <CardContent>
                  <div className={`text-2xl font-bold ${balanceOwed > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-green-600 dark:text-green-400'}`}>
                    SAR {balanceOwed.toLocaleString()}
                  </div>
                  <p className='text-xs text-muted-foreground'>
                    {balanceOwed > 0 ? 'Pending transfer' : 'All settled'}
                  </p>
                </CardContent>
              </Card>
            </div>

            {/* Monthly Breakdown */}
            <FinanceMonthlyBreakdown data={data?.monthly_breakdown} />

            {/* Payments */}
            <Card>
              <CardHeader>
                <CardTitle className='text-sm font-medium'>Payments</CardTitle>
              </CardHeader>
              <CardContent>
                <FinancePaymentsTable
                  payments={data?.payments}
                  loading={loading}
                  onPageChange={updatePage}
                />
              </CardContent>
            </Card>
          </div>
        )}
      </Main>
    </>
  )
}

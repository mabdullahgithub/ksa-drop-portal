import { useState } from 'react'
import { DollarSign, TrendingUp, TrendingDown, AlertCircle, CreditCard, Receipt } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Input } from '@/components/ui/input'
import { Header } from '@/components/layout/header'
import { NotificationsDropdown } from '@/components/layout/notifications-dropdown'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { usePortalFinance } from '@/hooks/usePortal'
import { FinanceTransactionsTable } from './components/finance-transactions-table'
import { FinanceMonthlyBreakdown } from './components/finance-monthly-breakdown'

export function PortalFinance() {
  const { data, loading, filters, updateFilters } = usePortalFinance({ financial_status: '' })
  const [searchValue, setSearchValue] = useState('')

  const handlePeriodChange = (value: string) => {
    updateFilters({ period: value, page: 1 })
  }

  const handleStatusFilter = (value: string) => {
    updateFilters({ financial_status: value === 'all' ? '' : value, page: 1 })
  }

  const handleSearch = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      updateFilters({ search: searchValue, page: 1 })
    }
  }

  const handlePageChange = (page: number) => {
    updateFilters({ page })
  }

  const statusColor = (status: string) => {
    switch (status) {
      case 'paid': return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
      case 'pending': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
      case 'refunded': return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
      case 'partially_refunded': return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'
      default: return ''
    }
  }

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
            <h1 className='text-2xl font-bold tracking-tight'>Finance</h1>
            <p className='text-muted-foreground'>Financial overview, transactions and payment tracking</p>
          </div>
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
            <div className='grid gap-4 md:grid-cols-2 lg:grid-cols-5'>
              <Card>
                <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                  <CardTitle className='text-sm font-medium'>Net Revenue</CardTitle>
                  <TrendingUp className='h-4 w-4 text-muted-foreground' />
                </CardHeader>
                <CardContent>
                  <div className='text-2xl font-bold text-green-600 dark:text-green-400'>
                    SAR {(data?.summary?.net_revenue ?? 0).toLocaleString()}
                  </div>
                  <p className='text-xs text-muted-foreground'>Paid minus refunds</p>
                </CardContent>
              </Card>
              <Card>
                <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                  <CardTitle className='text-sm font-medium'>Total Paid</CardTitle>
                  <DollarSign className='h-4 w-4 text-muted-foreground' />
                </CardHeader>
                <CardContent>
                  <div className='text-2xl font-bold'>
                    SAR {(data?.summary?.total_paid ?? 0).toLocaleString()}
                  </div>
                  <p className='text-xs text-muted-foreground'>Collected payments</p>
                </CardContent>
              </Card>
              <Card>
                <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                  <CardTitle className='text-sm font-medium'>Pending</CardTitle>
                  <AlertCircle className='h-4 w-4 text-muted-foreground' />
                </CardHeader>
                <CardContent>
                  <div className='text-2xl font-bold text-yellow-600 dark:text-yellow-400'>
                    SAR {(data?.summary?.total_pending ?? 0).toLocaleString()}
                  </div>
                  <p className='text-xs text-muted-foreground'>Awaiting payment</p>
                </CardContent>
              </Card>
              <Card>
                <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                  <CardTitle className='text-sm font-medium'>Refunded</CardTitle>
                  <TrendingDown className='h-4 w-4 text-muted-foreground' />
                </CardHeader>
                <CardContent>
                  <div className='text-2xl font-bold text-red-600 dark:text-red-400'>
                    SAR {(data?.summary?.total_refunded ?? 0).toLocaleString()}
                  </div>
                  <p className='text-xs text-muted-foreground'>Total refunded</p>
                </CardContent>
              </Card>
              <Card>
                <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-2'>
                  <CardTitle className='text-sm font-medium'>Outstanding</CardTitle>
                  <CreditCard className='h-4 w-4 text-muted-foreground' />
                </CardHeader>
                <CardContent>
                  <div className='text-2xl font-bold text-orange-600 dark:text-orange-400'>
                    SAR {(data?.summary?.total_outstanding ?? 0).toLocaleString()}
                  </div>
                  <p className='text-xs text-muted-foreground'>Balance due</p>
                </CardContent>
              </Card>
            </div>

            {/* Status Breakdown */}
            {data?.status_breakdown && data.status_breakdown.length > 0 && (
              <Card>
                <CardHeader>
                  <CardTitle className='text-sm font-medium flex items-center gap-2'>
                    <Receipt className='h-4 w-4' />
                    Payment Status Breakdown
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className='grid gap-3 sm:grid-cols-2 lg:grid-cols-4'>
                    {data.status_breakdown.map((item: any) => (
                      <div key={item.financial_status} className='flex items-center justify-between rounded-lg border p-3'>
                        <div>
                          <Badge className={`text-xs ${statusColor(item.financial_status)}`} variant='outline'>
                            {item.financial_status.replace('_', ' ')}
                          </Badge>
                          <p className='mt-1 text-xs text-muted-foreground'>{item.count} orders</p>
                        </div>
                        <span className='text-sm font-bold'>SAR {parseFloat(item.total_amount).toLocaleString()}</span>
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            )}

            {/* Monthly Breakdown */}
            <FinanceMonthlyBreakdown data={data?.monthly_breakdown} />

            {/* Transactions */}
            <Card>
              <CardHeader>
                <div className='flex items-center justify-between'>
                  <CardTitle className='text-sm font-medium'>Transactions</CardTitle>
                  <div className='flex items-center gap-2'>
                    <Input
                      placeholder='Search orders...'
                      className='h-8 w-[180px]'
                      value={searchValue}
                      onChange={(e) => setSearchValue(e.target.value)}
                      onKeyDown={handleSearch}
                    />
                    <Select value={filters.financial_status || 'all'} onValueChange={handleStatusFilter}>
                      <SelectTrigger className='h-8 w-[150px]'>
                        <SelectValue placeholder='All statuses' />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value='all'>All Statuses</SelectItem>
                        <SelectItem value='paid'>Paid</SelectItem>
                        <SelectItem value='pending'>Pending</SelectItem>
                        <SelectItem value='refunded'>Refunded</SelectItem>
                        <SelectItem value='partially_refunded'>Partially Refunded</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <FinanceTransactionsTable
                  transactions={data?.transactions}
                  loading={loading}
                  onPageChange={handlePageChange}
                />
              </CardContent>
            </Card>
          </div>
        )}
      </Main>
    </>
  )
}

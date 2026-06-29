import { useOrderStatistics } from '@/hooks/useOrders'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { DollarSign, ShoppingCart, TrendingUp, Package } from 'lucide-react'
import { OrdersStatsSkeleton } from './orders-skeleton'

export function OrdersStats() {
  const { statistics, loading } = useOrderStatistics()

  if (loading) {
    return <OrdersStatsSkeleton />
  }

  if (!statistics) return null

  const fulfillmentCounts = (statistics.by_fulfillment_status ?? []).reduce(
    (acc, item) => {
      acc[item.fulfillment_status] = item.count
      return acc
    },
    {} as Record<string, number>
  )

  const financialCounts = (statistics.by_financial_status ?? []).reduce(
    (acc, item) => {
      acc[item.financial_status] = item.count
      return acc
    },
    {} as Record<string, number>
  )

  return (
    <div className='grid gap-3 md:grid-cols-2 lg:grid-cols-4'>
      <Card className='border-muted/50'>
        <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-0 pt-0 gap-0'>
          <CardTitle className='text-xs font-medium text-muted-foreground'>Total Orders</CardTitle>
          <ShoppingCart className='h-5 w-5 text-muted-foreground' />
        </CardHeader>
        <CardContent className='pb-0 -mt-3'>
          <div className='text-xl font-bold'>{statistics.total_orders}</div>
          <p className='text-[11px] text-muted-foreground mt-0'>
            {fulfillmentCounts.pending || 0} pending, {fulfillmentCounts.unfulfilled || 0} unfulfilled
          </p>
        </CardContent>
      </Card>

      <Card className='border-muted/50'>
        <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-0 pt-0 gap-0'>
          <CardTitle className='text-xs font-medium text-muted-foreground'>Total Revenue</CardTitle>
          <DollarSign className='h-5 w-5 text-muted-foreground' />
        </CardHeader>
        <CardContent className='pb-0 -mt-3'>
          <div className='text-xl font-bold'>
            SAR {statistics.total_revenue.toLocaleString('en-US', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
            })}
          </div>
          <p className='text-[11px] text-muted-foreground mt-0'>
            {financialCounts.paid || 0} paid orders
          </p>
        </CardContent>
      </Card>

      <Card className='border-muted/50'>
        <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-0 pt-0 gap-0'>
          <CardTitle className='text-xs font-medium text-muted-foreground'>Average Order</CardTitle>
          <TrendingUp className='h-5 w-5 text-muted-foreground' />
        </CardHeader>
        <CardContent className='pb-0 -mt-3'>
          <div className='text-xl font-bold'>
            SAR {statistics.average_order_value.toLocaleString('en-US', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
            })}
          </div>
          <p className='text-[11px] text-muted-foreground mt-0'>
            Per order value
          </p>
        </CardContent>
      </Card>

      <Card className='border-muted/50'>
        <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-0 pt-0 gap-0'>
          <CardTitle className='text-xs font-medium text-muted-foreground'>Fulfilled</CardTitle>
          <Package className='h-5 w-5 text-muted-foreground' />
        </CardHeader>
        <CardContent className='pb-0 -mt-3'>
          <div className='text-xl font-bold'>
            {fulfillmentCounts.fulfilled || 0}
          </div>
          <p className='text-[11px] text-muted-foreground mt-0'>
            {fulfillmentCounts.cancelled || 0} cancelled
          </p>
        </CardContent>
      </Card>
    </div>
  )
}

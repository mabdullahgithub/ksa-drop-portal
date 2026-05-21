import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Calendar } from 'lucide-react'

interface MonthlyItem {
  month: string
  paid_amount: number
  paid_count: number
  pending_amount: number
  pending_count: number
  refunded_amount: number
  refunded_count: number
  total_orders: number
}

interface Props {
  data: MonthlyItem[] | undefined
}

export function FinanceMonthlyBreakdown({ data }: Props) {
  if (!data || data.length === 0) return null

  return (
    <Card>
      <CardHeader>
        <CardTitle className='text-sm font-medium flex items-center gap-2'>
          <Calendar className='h-4 w-4' />
          Monthly Financial Breakdown
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className='overflow-x-auto'>
          <table className='w-full text-sm'>
            <thead>
              <tr className='border-b text-left'>
                <th className='pb-2 pr-4 font-medium text-muted-foreground'>Month</th>
                <th className='pb-2 pr-4 font-medium text-muted-foreground'>Orders</th>
                <th className='pb-2 pr-4 font-medium text-green-600 dark:text-green-400'>Paid</th>
                <th className='pb-2 pr-4 font-medium text-yellow-600 dark:text-yellow-400'>Pending</th>
                <th className='pb-2 font-medium text-red-600 dark:text-red-400'>Refunded</th>
              </tr>
            </thead>
            <tbody>
              {data.map((item) => (
                <tr key={item.month} className='border-b last:border-0'>
                  <td className='py-2.5 pr-4 font-medium'>{item.month}</td>
                  <td className='py-2.5 pr-4 text-muted-foreground'>{item.total_orders}</td>
                  <td className='py-2.5 pr-4'>
                    <div className='text-green-600 dark:text-green-400 font-medium'>
                      SAR {item.paid_amount.toLocaleString()}
                    </div>
                    <div className='text-xs text-muted-foreground'>{item.paid_count} orders</div>
                  </td>
                  <td className='py-2.5 pr-4'>
                    <div className='text-yellow-600 dark:text-yellow-400 font-medium'>
                      SAR {item.pending_amount.toLocaleString()}
                    </div>
                    <div className='text-xs text-muted-foreground'>{item.pending_count} orders</div>
                  </td>
                  <td className='py-2.5'>
                    {item.refunded_amount > 0 ? (
                      <>
                        <div className='text-red-600 dark:text-red-400 font-medium'>
                          SAR {item.refunded_amount.toLocaleString()}
                        </div>
                        <div className='text-xs text-muted-foreground'>{item.refunded_count} orders</div>
                      </>
                    ) : (
                      <span className='text-muted-foreground'>—</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </CardContent>
    </Card>
  )
}

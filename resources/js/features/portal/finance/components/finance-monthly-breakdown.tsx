import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Calendar } from 'lucide-react'

interface MonthlyItem {
  month: string
  orders_count: number
  sold_value: number
  profit: number
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
          Monthly Breakdown
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className='overflow-x-auto'>
          <table className='w-full text-sm'>
            <thead>
              <tr className='border-b text-left'>
                <th className='pb-2 pr-4 font-medium text-muted-foreground'>Month</th>
                <th className='pb-2 pr-4 font-medium text-muted-foreground'>Orders</th>
                <th className='pb-2 pr-4 font-medium text-muted-foreground'>Total Sold</th>
                <th className='pb-2 font-medium text-green-600 dark:text-green-400'>Your Profit</th>
              </tr>
            </thead>
            <tbody>
              {data.map((item) => (
                <tr key={item.month} className='border-b last:border-0'>
                  <td className='py-2.5 pr-4 font-medium'>{item.month}</td>
                  <td className='py-2.5 pr-4 text-muted-foreground'>{item.orders_count}</td>
                  <td className='py-2.5 pr-4'>SAR {item.sold_value.toLocaleString()}</td>
                  <td className='py-2.5'>
                    <span className={item.profit >= 0 ? 'text-green-600 dark:text-green-400 font-medium' : 'text-red-600 dark:text-red-400 font-medium'}>
                      SAR {item.profit.toLocaleString()}
                    </span>
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

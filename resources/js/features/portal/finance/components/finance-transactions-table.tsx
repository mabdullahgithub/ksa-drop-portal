import { Badge } from '@/components/ui/badge'
import { Pagination } from '@/components/data-table'
import { EmptyState } from '@/components/empty-state'

interface Transaction {
  id: number
  order_number: string
  customer_name: string
  total: string
  subtotal: string
  shipping_cost: string
  taxes: string
  discount_amount: string
  refunded_amount: string
  outstanding_balance: string
  financial_status: string
  payment_method: string | null
  payment_reference: string | null
  currency: string
  paid_at: string | null
  created_at: string
}

interface PaginatedTransactions {
  data: Transaction[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

interface Props {
  transactions: PaginatedTransactions | null
  loading: boolean
  onPageChange: (page: number) => void
  onPageSizeChange: (pageSize: number) => void
}

const statusVariant = (status: string) => {
  switch (status) {
    case 'paid': return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
    case 'pending': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
    case 'refunded': return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
    case 'partially_refunded': return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'
    default: return ''
  }
}

export function FinanceTransactionsTable({ transactions, loading, onPageChange, onPageSizeChange }: Props) {
  if (!transactions || transactions.data.length === 0) {
    return (
      <EmptyState bot='hexagon' title='No transactions found for the selected period.' />
    )
  }

  return (
    <div className='space-y-4'>
      <div className='overflow-x-auto'>
        <table className='w-full text-sm'>
          <thead>
            <tr className='border-b text-left'>
              <th className='pb-2 pr-4 font-medium text-muted-foreground'>Order</th>
              <th className='pb-2 pr-4 font-medium text-muted-foreground'>Customer</th>
              <th className='pb-2 pr-4 font-medium text-muted-foreground'>Total</th>
              <th className='pb-2 pr-4 font-medium text-muted-foreground'>Status</th>
              <th className='pb-2 pr-4 font-medium text-muted-foreground'>Payment</th>
              <th className='pb-2 pr-4 font-medium text-muted-foreground'>Refunded</th>
              <th className='pb-2 font-medium text-muted-foreground'>Date</th>
            </tr>
          </thead>
          <tbody>
            {transactions.data.map((tx) => (
              <tr key={tx.id} className='border-b last:border-0'>
                <td className='py-2.5 pr-4 font-mono text-xs'>{tx.order_number}</td>
                <td className='py-2.5 pr-4'>{tx.customer_name}</td>
                <td className='py-2.5 pr-4 font-medium'>
                  {tx.currency} {parseFloat(tx.total).toLocaleString()}
                </td>
                <td className='py-2.5 pr-4'>
                  <Badge className={`text-xs ${statusVariant(tx.financial_status)}`} variant='outline'>
                    {tx.financial_status.replace('_', ' ')}
                  </Badge>
                </td>
                <td className='py-2.5 pr-4 text-xs text-muted-foreground'>
                  {tx.payment_method || '—'}
                </td>
                <td className='py-2.5 pr-4'>
                  {parseFloat(tx.refunded_amount) > 0 ? (
                    <span className='text-red-600 dark:text-red-400'>
                      -{tx.currency} {parseFloat(tx.refunded_amount).toLocaleString()}
                    </span>
                  ) : (
                    <span className='text-muted-foreground'>—</span>
                  )}
                </td>
                <td className='py-2.5 text-xs text-muted-foreground'>
                  {tx.paid_at
                    ? new Date(tx.paid_at).toLocaleDateString()
                    : new Date(tx.created_at).toLocaleDateString()}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <Pagination
        meta={transactions}
        onPageChange={onPageChange}
        onPageSizeChange={onPageSizeChange}
        className='pt-2'
      />
    </div>
  )
}

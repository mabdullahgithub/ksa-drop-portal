import { useEffect, useState } from 'react'
import { Link } from '@inertiajs/react'
import { Badge } from '@/components/ui/badge'
import type { Order } from '@/types/order'

const financialColorMap: Record<string, string> = {
  paid: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
  pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
  refunded: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
  partially_refunded: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
}

export function RecentOrders() {
  const [orders, setOrders] = useState<Order[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetch('/api/orders?per_page=10&sort_by=created_at&sort_order=desc')
      .then((r) => r.json())
      .then((data) => setOrders(data?.data ?? []))
      .catch(() => setOrders([]))
      .finally(() => setLoading(false))
  }, [])

  if (loading) {
    return (
      <div className='space-y-3'>
        {Array.from({ length: 8 }).map((_, i) => (
          <div key={i} className='flex items-center gap-2'>
            <div className='h-4 w-16 animate-pulse rounded bg-muted' />
            <div className='h-4 flex-1 animate-pulse rounded bg-muted' />
            <div className='h-5 w-20 animate-pulse rounded bg-muted' />
            <div className='h-5 w-14 animate-pulse rounded bg-muted' />
            <div className='h-4 w-16 animate-pulse rounded bg-muted' />
          </div>
        ))}
      </div>
    )
  }

  if (!orders.length) {
    return <p className='text-sm text-muted-foreground'>No orders yet.</p>
  }

  return (
    <div className='space-y-2'>
      {orders.map((order) => (
        <div key={order.id} className='flex items-center gap-2 text-sm'>
          <Link href='/orders' className='shrink-0 font-mono font-medium hover:underline'>
            {order.order_number}
          </Link>
          <span className='min-w-0 flex-1 truncate text-muted-foreground'>
            {order.customer_name ?? '—'}
          </span>
          <Badge
            variant='outline'
            className={`shrink-0 text-xs ${financialColorMap[order.financial_status] ?? financialColorMap['pending']}`}
          >
            {order.financial_status.charAt(0).toUpperCase() + order.financial_status.slice(1)}
          </Badge>
          <span className='shrink-0 font-medium tabular-nums'>{order.formatted_total}</span>
        </div>
      ))}
    </div>
  )
}

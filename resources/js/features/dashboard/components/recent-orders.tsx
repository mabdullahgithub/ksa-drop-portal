import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'

const orders = [
  {
    id: '#ORD-001',
    customer: 'Olivia Martin',
    email: 'olivia.martin@email.com',
    avatar: '/avatars/01.png',
    amount: '$1,999.00',
    status: 'completed',
  },
  {
    id: '#ORD-002',
    customer: 'Jackson Lee',
    email: 'jackson.lee@email.com',
    avatar: '/avatars/02.png',
    amount: '$39.00',
    status: 'pending',
  },
  {
    id: '#ORD-003',
    customer: 'Isabella Nguyen',
    email: 'isabella.nguyen@email.com',
    avatar: '/avatars/03.png',
    amount: '$299.00',
    status: 'completed',
  },
  {
    id: '#ORD-004',
    customer: 'William Kim',
    email: 'will@email.com',
    avatar: '/avatars/04.png',
    amount: '$99.00',
    status: 'processing',
  },
  {
    id: '#ORD-005',
    customer: 'Sofia Davis',
    email: 'sofia.davis@email.com',
    avatar: '/avatars/05.png',
    amount: '$39.00',
    status: 'completed',
  },
]

const statusColorMap: Record<string, string> = {
  completed: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
  pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
  processing: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
}

export function RecentOrders() {
  return (
    <div className='space-y-8'>
      {orders.map((order) => (
        <div key={order.id} className='flex items-center gap-4'>
          <Avatar className='h-9 w-9'>
            <AvatarImage src={order.avatar} alt={order.customer} />
            <AvatarFallback>
              {order.customer
                .split(' ')
                .map((n) => n[0])
                .join('')}
            </AvatarFallback>
          </Avatar>
          <div className='flex flex-1 flex-wrap items-center justify-between gap-2'>
            <div className='space-y-1'>
              <p className='text-sm leading-none font-medium'>{order.customer}</p>
              <p className='text-sm text-muted-foreground'>{order.id}</p>
            </div>
            <div className='flex items-center gap-3'>
              <Badge variant='secondary' className={statusColorMap[order.status] || ''}>
                {order.status.charAt(0).toUpperCase() + order.status.slice(1)}
              </Badge>
              <div className='font-medium'>{order.amount}</div>
            </div>
          </div>
        </div>
      ))}
    </div>
  )
}

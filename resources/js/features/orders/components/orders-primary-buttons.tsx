import { Download, RefreshCw } from 'lucide-react'
import { type Table } from '@tanstack/react-table'
import { Button } from '@/components/ui/button'
import { Can } from '@/components/can'
import { useOrderMutations } from '@/hooks/useOrders'
import { type Order, type OrderFilters } from '@/types/order'
import { useOrdersContext } from './orders-provider'
import { useState } from 'react'

type OrdersPrimaryButtonsProps = {
  filters: OrderFilters
  table?: Table<Order> | null
}

export function OrdersPrimaryButtons({ filters, table }: OrdersPrimaryButtonsProps) {
  const { exportOrders } = useOrderMutations()
  const { refresh } = useOrdersContext()
  const [isRefreshing, setIsRefreshing] = useState(false)

  const handleRefresh = () => {
    setIsRefreshing(true)
    refresh().finally(() => setIsRefreshing(false))
  }

  const handleExport = () => {
    const selectedIds = table
      ?.getFilteredSelectedRowModel()
      .rows.map((row) => row.original.id)

    if (selectedIds && selectedIds.length > 0) {
      exportOrders({ order_ids: selectedIds })
    } else {
      exportOrders(filters)
    }
  }

  return (
    <div className='flex items-center gap-2'>
      <Button
        variant='outline'
        size='sm'
        onClick={handleRefresh}
        disabled={isRefreshing}
      >
        <RefreshCw className={`mr-2 h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
        Refresh
      </Button>
      <Can permission='view orders'>
        <Button variant='outline' size='sm' onClick={handleExport}>
          <Download className='mr-2 h-4 w-4' />
          Export CSV
        </Button>
      </Can>
    </div>
  )
}

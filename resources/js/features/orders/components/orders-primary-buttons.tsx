import { Download, RefreshCw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Can } from '@/components/can'
import { useOrderMutations } from '@/hooks/useOrders'
import { useState } from 'react'

export function OrdersPrimaryButtons() {
  const { exportOrders } = useOrderMutations()
  const [isRefreshing, setIsRefreshing] = useState(false)

  const handleRefresh = () => {
    setIsRefreshing(true)
    window.location.reload()
  }

  const handleExport = () => {
    exportOrders()
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

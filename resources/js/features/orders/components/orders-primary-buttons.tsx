import { Download, RefreshCw, Upload } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Can } from '@/components/can'
import { useOrderMutations } from '@/hooks/useOrders'
import { useState } from 'react'
import { OrdersImportDialog } from './orders-import-dialog'

interface OrdersPrimaryButtonsProps {
  onImportSuccess?: () => void
}

export function OrdersPrimaryButtons({ onImportSuccess }: OrdersPrimaryButtonsProps) {
  const { exportOrders } = useOrderMutations()
  const [isRefreshing, setIsRefreshing] = useState(false)
  const [importDialogOpen, setImportDialogOpen] = useState(false)

  const handleRefresh = () => {
    setIsRefreshing(true)
    window.location.reload()
  }

  const handleExport = () => {
    exportOrders()
  }

  return (
    <>
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
        <Can permission='edit orders'>
          <Button
            variant='outline'
            size='sm'
            onClick={() => setImportDialogOpen(true)}
          >
            <Upload className='mr-2 h-4 w-4' />
            Import CSV
          </Button>
        </Can>
        <Can permission='view orders'>
          <Button variant='outline' size='sm' onClick={handleExport}>
            <Download className='mr-2 h-4 w-4' />
            Export CSV
          </Button>
        </Can>
      </div>

      <OrdersImportDialog
        open={importDialogOpen}
        onOpenChange={setImportDialogOpen}
        onSuccess={onImportSuccess}
      />
    </>
  )
}

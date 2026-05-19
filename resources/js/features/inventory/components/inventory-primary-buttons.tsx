import { Download, RefreshCw, Upload } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Can } from '@/components/can'
import { useProductMutations } from '@/hooks/useProducts'
import { useState } from 'react'
import { InventoryImportDialog } from './inventory-import-dialog'

interface InventoryPrimaryButtonsProps {
  onImportSuccess?: () => void
}

export function InventoryPrimaryButtons({ onImportSuccess }: InventoryPrimaryButtonsProps) {
  const { exportProducts } = useProductMutations()
  const [isRefreshing, setIsRefreshing] = useState(false)
  const [importOpen, setImportOpen] = useState(false)

  const handleRefresh = () => {
    setIsRefreshing(true)
    window.location.reload()
  }

  return (
    <>
      <div className='flex items-center gap-2'>
        <Button variant='outline' size='sm' onClick={handleRefresh} disabled={isRefreshing}>
          <RefreshCw className={`mr-2 h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
          Refresh
        </Button>
        <Can permission='edit inventory'>
          <Button variant='outline' size='sm' onClick={() => setImportOpen(true)}>
            <Upload className='mr-2 h-4 w-4' />
            Import CSV
          </Button>
        </Can>
        <Can permission='view inventory'>
          <Button variant='outline' size='sm' onClick={() => exportProducts()}>
            <Download className='mr-2 h-4 w-4' />
            Export CSV
          </Button>
        </Can>
      </div>

      <InventoryImportDialog open={importOpen} onOpenChange={setImportOpen} onSuccess={onImportSuccess} />
    </>
  )
}

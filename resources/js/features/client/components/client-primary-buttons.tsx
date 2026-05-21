import { useState } from 'react'
import { Download, Plus, RefreshCw } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { usePermissions } from '@/hooks/use-permissions'
import { useClientMutations } from '@/hooks/useClients'
import { useClientContext } from './client-provider'
import type { ClientFilters } from '@/types/client'

interface ClientPrimaryButtonsProps {
  filters: ClientFilters
  onRefresh: () => void
}

export function ClientPrimaryButtons({ filters, onRefresh }: ClientPrimaryButtonsProps) {
  const { can } = usePermissions()
  const { setOpen } = useClientContext()
  const { exportClients } = useClientMutations()
  const [isRefreshing, setIsRefreshing] = useState(false)

  const handleRefresh = async () => {
    setIsRefreshing(true)
    onRefresh()
    setTimeout(() => setIsRefreshing(false), 500)
  }

  return (
    <div className='flex items-center gap-2'>
      <Button variant='outline' size='sm' onClick={handleRefresh}>
        <RefreshCw className={`mr-2 h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
        Refresh
      </Button>
      {can('view client') && (
        <Button variant='outline' size='sm' onClick={() => exportClients(filters)}>
          <Download className='mr-2 h-4 w-4' />
          Export CSV
        </Button>
      )}
      {can('create client') && (
        <Button size='sm' onClick={() => setOpen('create')}>
          <Plus className='mr-2 h-4 w-4' />
          Create Client
        </Button>
      )}
    </div>
  )
}

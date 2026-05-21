import { UserCheck, UserX, Ban } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { useClientMutations } from '@/hooks/useClients'

interface ClientBulkActionsProps {
  selectedIds: number[]
  onSuccess: () => void
}

export function ClientBulkActions({ selectedIds, onSuccess }: ClientBulkActionsProps) {
  const { bulkUpdate, loading } = useClientMutations()

  if (selectedIds.length === 0) return null

  const handleBulkAction = async (action: string) => {
    const success = await bulkUpdate(selectedIds, action)
    if (success) {
      toast.success(`${selectedIds.length} clients updated`)
      onSuccess()
    } else {
      toast.error('Failed to update clients')
    }
  }

  return (
    <div className='flex items-center gap-2 rounded-md border bg-muted/50 px-4 py-2'>
      <span className='text-sm font-medium'>
        {selectedIds.length} selected
      </span>
      <Button variant='outline' size='sm' onClick={() => handleBulkAction('activate')} disabled={loading}>
        <UserCheck className='mr-1 h-3 w-3' />
        Activate
      </Button>
      <Button variant='outline' size='sm' onClick={() => handleBulkAction('deactivate')} disabled={loading}>
        <UserX className='mr-1 h-3 w-3' />
        Deactivate
      </Button>
      <Button variant='outline' size='sm' onClick={() => handleBulkAction('suspend')} disabled={loading}>
        <Ban className='mr-1 h-3 w-3' />
        Suspend
      </Button>
    </div>
  )
}

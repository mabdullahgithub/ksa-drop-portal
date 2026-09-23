import { useState } from 'react'
import { type Table } from '@tanstack/react-table'
import { UserCheck, UserX, Ban, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { DataTableBulkActions as BulkActionsToolbar } from '@/components/data-table'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { useClientMutations } from '@/hooks/useClients'
import type { Client } from '@/types/client'
import { usePermissions } from '@/hooks/use-permissions'

interface ClientBulkActionsProps<TData> {
  table: Table<TData>
  onSuccess: () => void
}

/**
 * Takes the table instance directly, like the orders and inventory toolbars.
 * It previously read ids from ClientContext, which nothing ever populated, so
 * the toolbar could never appear.
 */
export function ClientBulkActions<TData>({ table, onSuccess }: ClientBulkActionsProps<TData>) {
  const { bulkUpdate, bulkDelete, loading } = useClientMutations()
  const { can } = usePermissions()
  const [showDeleteDialog, setShowDeleteDialog] = useState(false)
  const [deleting, setDeleting] = useState(false)

  const selectedRows = table.getFilteredSelectedRowModel().rows
  const selectedIds = selectedRows.map((row) => (row.original as Client).id)

  const handleBulkAction = async (action: string) => {
    const success = await bulkUpdate(selectedIds, action)
    if (success) {
      toast.success(`${selectedIds.length} client${selectedIds.length > 1 ? 's' : ''} updated`)
      table.resetRowSelection()
      onSuccess()
    } else {
      toast.error('Failed to update clients')
    }
  }

  const handleBulkDelete = async () => {
    setDeleting(true)
    try {
      const success = await bulkDelete(selectedIds)
      if (success) {
        toast.success(
          `Moved ${selectedIds.length} client${selectedIds.length > 1 ? 's' : ''} to the recycle bin`
        )
        table.resetRowSelection()
        setShowDeleteDialog(false)
        onSuccess()
      } else {
        toast.error('Failed to delete clients')
      }
    } finally {
      setDeleting(false)
    }
  }

  return (
    <>
      <BulkActionsToolbar table={table} entityName='client'>
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant='outline'
              size='icon'
              className='size-8'
              onClick={() => handleBulkAction('activate')}
              disabled={loading}
              aria-label='Activate clients'
            >
              <UserCheck className='h-4 w-4' />
            </Button>
          </TooltipTrigger>
          <TooltipContent>Activate</TooltipContent>
        </Tooltip>

        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant='outline'
              size='icon'
              className='size-8'
              onClick={() => handleBulkAction('deactivate')}
              disabled={loading}
              aria-label='Deactivate clients'
            >
              <UserX className='h-4 w-4' />
            </Button>
          </TooltipTrigger>
          <TooltipContent>Deactivate</TooltipContent>
        </Tooltip>

        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant='outline'
              size='icon'
              className='size-8'
              onClick={() => handleBulkAction('suspend')}
              disabled={loading}
              aria-label='Suspend clients'
            >
              <Ban className='h-4 w-4' />
            </Button>
          </TooltipTrigger>
          <TooltipContent>Suspend</TooltipContent>
        </Tooltip>

        {can('delete client') && (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant='destructive'
                size='icon'
                className='size-8'
                onClick={() => setShowDeleteDialog(true)}
                disabled={loading}
                aria-label='Delete clients'
              >
                <Trash2 className='h-4 w-4' />
              </Button>
            </TooltipTrigger>
            <TooltipContent>Delete (moves to recycle bin)</TooltipContent>
          </Tooltip>
        )}
      </BulkActionsToolbar>

      <ConfirmDialog
        open={showDeleteDialog}
        onOpenChange={setShowDeleteDialog}
        title='Delete clients'
        desc={
          <div className='space-y-2'>
            <p>
              Move {selectedIds.length} selected client{selectedIds.length > 1 ? 's' : ''} to the
              recycle bin? You can restore {selectedIds.length > 1 ? 'them' : 'it'} from there.
            </p>
            <p className='text-muted-foreground text-sm'>
              Their orders and products are not deleted and stay visible.
            </p>
          </div>
        }
        confirmText='Delete'
        destructive
        isLoading={deleting}
        handleConfirm={handleBulkDelete}
      />
    </>
  )
}

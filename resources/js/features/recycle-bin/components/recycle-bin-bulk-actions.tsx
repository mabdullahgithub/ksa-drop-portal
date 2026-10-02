import { useState } from 'react'
import { type Table } from '@tanstack/react-table'
import { RotateCcw, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { DataTableBulkActions } from '@/components/data-table'
import { ConfirmDialog } from '@/components/confirm-dialog'

type RecycleBinBulkActionsProps<T> = {
  table: Table<T>
  entityName: string
  onRestore?: (ids: (number | string)[]) => Promise<void>
  onPurge?: (ids: (number | string)[]) => Promise<void>
}

export function RecycleBinBulkActions<T>({
  table,
  entityName,
  onRestore,
  onPurge,
}: RecycleBinBulkActionsProps<T>) {
  const [showPurgeDialog, setShowPurgeDialog] = useState(false)
  const [working, setWorking] = useState(false)

  const selectedRows = table.getFilteredSelectedRowModel().rows
  const count = selectedRows.length
  // The table's row id is the wire id: a plain numeric id for orders and
  // clients, the composite "product:7" form for inventory.
  const selectedIds = selectedRows.map((row) => row.id)

  const run = async (action: () => Promise<void>) => {
    setWorking(true)
    try {
      await action()
      table.resetRowSelection()
      setShowPurgeDialog(false)
    } finally {
      setWorking(false)
    }
  }

  const handleRestore = () =>
    run(async () => {
      try {
        await onRestore?.(selectedIds)
      } catch (error) {
        toast.error(error instanceof Error ? error.message : 'Failed to restore')
      }
    })

  const handlePurge = () =>
    run(async () => {
      try {
        await onPurge?.(selectedIds)
      } catch (error) {
        toast.error(error instanceof Error ? error.message : 'Failed to delete permanently')
      }
    })

  return (
    <>
      <DataTableBulkActions table={table} entityName={entityName}>
        {onRestore && (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant='outline'
                size='sm'
                onClick={handleRestore}
                disabled={working}
                className='h-8 gap-1.5'
                aria-label={`Restore selected ${entityName}s`}
              >
                <RotateCcw className='h-4 w-4' />
                Restore
              </Button>
            </TooltipTrigger>
            <TooltipContent>Put these back where they were</TooltipContent>
          </Tooltip>
        )}

        {onPurge && (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant='destructive'
                size='sm'
                onClick={() => setShowPurgeDialog(true)}
                disabled={working}
                className='h-8 gap-1.5'
                aria-label={`Permanently delete selected ${entityName}s`}
              >
                <Trash2 className='h-4 w-4' />
                Delete forever
              </Button>
            </TooltipTrigger>
            <TooltipContent>Remove permanently — this cannot be undone</TooltipContent>
          </Tooltip>
        )}
      </DataTableBulkActions>

      <ConfirmDialog
        open={showPurgeDialog}
        onOpenChange={setShowPurgeDialog}
        title={`Permanently delete ${count} ${entityName}${count === 1 ? '' : 's'}?`}
        desc={
          <div className='space-y-2'>
            <p>
              This removes {count === 1 ? 'it' : 'them'} from the database for good. There is no
              way to get {count === 1 ? 'it' : 'them'} back.
            </p>
            <p className='text-muted-foreground text-sm'>
              Anything attached goes too — an order takes its items, shipments and invoices; a
              client takes its products, payments and store connection.
            </p>
          </div>
        }
        confirmText='Delete forever'
        destructive
        isLoading={working}
        handleConfirm={handlePurge}
      />
    </>
  )
}

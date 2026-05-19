import { useState } from 'react'
import { type Table } from '@tanstack/react-table'
import { Package, DollarSign, Tag, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { DataTableBulkActions as BulkActionsToolbar } from '@/components/data-table'
import { type Order } from '@/types/order'
import { useOrderMutations } from '@/hooks/useOrders'

type DataTableBulkActionsProps<TData> = {
  table: Table<TData>
}

export function DataTableBulkActions<TData>({
  table,
}: DataTableBulkActionsProps<TData>) {
  const selectedRows = table.getFilteredSelectedRowModel().rows
  const { bulkUpdate } = useOrderMutations()
  const [showTagDialog, setShowTagDialog] = useState(false)
  const [showDeleteDialog, setShowDeleteDialog] = useState(false)
  const [tagInput, setTagInput] = useState('')

  const handleBulkFulfillmentChange = async (status: string) => {
    const selectedOrders = selectedRows.map((row) => (row.original as Order).id)

    toast.promise(
      bulkUpdate({
        order_ids: selectedOrders,
        action: 'update_fulfillment',
        fulfillment_status: status,
      }),
      {
        loading: 'Updating fulfillment status...',
        success: () => {
          table.resetRowSelection()
          window.location.reload()
          return `Updated ${selectedOrders.length} order${selectedOrders.length > 1 ? 's' : ''}`
        },
        error: 'Failed to update orders',
      }
    )
  }

  const handleBulkFinancialChange = async (status: string) => {
    const selectedOrders = selectedRows.map((row) => (row.original as Order).id)

    toast.promise(
      bulkUpdate({
        order_ids: selectedOrders,
        action: 'update_financial',
        financial_status: status,
      }),
      {
        loading: 'Updating payment status...',
        success: () => {
          table.resetRowSelection()
          window.location.reload()
          return `Updated ${selectedOrders.length} order${selectedOrders.length > 1 ? 's' : ''}`
        },
        error: 'Failed to update orders',
      }
    )
  }

  const handleAddTags = async () => {
    const selectedOrders = selectedRows.map((row) => (row.original as Order).id)
    const tags = tagInput.split(',').map(tag => tag.trim()).filter(tag => tag)

    if (tags.length === 0) {
      toast.error('Please enter at least one tag')
      return
    }

    toast.promise(
      bulkUpdate({
        order_ids: selectedOrders,
        action: 'add_tags',
        tags,
      }),
      {
        loading: 'Adding tags...',
        success: () => {
          table.resetRowSelection()
          setShowTagDialog(false)
          setTagInput('')
          window.location.reload()
          return `Added tags to ${selectedOrders.length} order${selectedOrders.length > 1 ? 's' : ''}`
        },
        error: 'Failed to add tags',
      }
    )
  }

  const handleBulkDelete = async () => {
    const selectedOrders = selectedRows.map((row) => (row.original as Order).id)

    toast.promise(
      bulkUpdate({
        order_ids: selectedOrders,
        action: 'cancel',
      }),
      {
        loading: 'Cancelling orders...',
        success: () => {
          table.resetRowSelection()
          setShowDeleteDialog(false)
          window.location.reload()
          return `Cancelled ${selectedOrders.length} order${selectedOrders.length > 1 ? 's' : ''}`
        },
        error: 'Failed to cancel orders',
      }
    )
  }

  return (
    <>
      <BulkActionsToolbar table={table} entityName='order'>
        <DropdownMenu>
          <Tooltip>
            <TooltipTrigger asChild>
              <DropdownMenuTrigger asChild>
                <Button
                  variant='outline'
                  size='icon'
                  className='size-8'
                  aria-label='Update fulfillment'
                  title='Update fulfillment'
                >
                  <Package className='h-4 w-4' />
                  <span className='sr-only'>Update fulfillment</span>
                </Button>
              </DropdownMenuTrigger>
            </TooltipTrigger>
            <TooltipContent>
              <p>Update fulfillment</p>
            </TooltipContent>
          </Tooltip>
          <DropdownMenuContent sideOffset={14}>
            <DropdownMenuItem onClick={() => handleBulkFulfillmentChange('pending')}>
              Pending
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => handleBulkFulfillmentChange('unfulfilled')}>
              Unfulfilled
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => handleBulkFulfillmentChange('fulfilled')}>
              Fulfilled
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <DropdownMenu>
          <Tooltip>
            <TooltipTrigger asChild>
              <DropdownMenuTrigger asChild>
                <Button
                  variant='outline'
                  size='icon'
                  className='size-8'
                  aria-label='Update payment'
                  title='Update payment'
                >
                  <DollarSign className='h-4 w-4' />
                  <span className='sr-only'>Update payment</span>
                </Button>
              </DropdownMenuTrigger>
            </TooltipTrigger>
            <TooltipContent>
              <p>Update payment</p>
            </TooltipContent>
          </Tooltip>
          <DropdownMenuContent sideOffset={14}>
            <DropdownMenuItem onClick={() => handleBulkFinancialChange('pending')}>
              Pending
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => handleBulkFinancialChange('paid')}>
              Paid
            </DropdownMenuItem>
            <DropdownMenuItem onClick={() => handleBulkFinancialChange('refunded')}>
              Refunded
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant='outline'
              size='icon'
              onClick={() => setShowTagDialog(true)}
              className='size-8'
              aria-label='Add tags'
              title='Add tags'
            >
              <Tag className='h-4 w-4' />
              <span className='sr-only'>Add tags</span>
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            <p>Add tags</p>
          </TooltipContent>
        </Tooltip>

        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant='destructive'
              size='icon'
              onClick={() => setShowDeleteDialog(true)}
              className='size-8'
              aria-label='Cancel orders'
              title='Cancel orders'
            >
              <Trash2 className='h-4 w-4' />
              <span className='sr-only'>Cancel orders</span>
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            <p>Cancel orders</p>
          </TooltipContent>
        </Tooltip>
      </BulkActionsToolbar>

      {/* Add Tags Dialog */}
      <Dialog open={showTagDialog} onOpenChange={setShowTagDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Add Tags</DialogTitle>
            <DialogDescription>
              Add tags to {selectedRows.length} selected order{selectedRows.length > 1 ? 's' : ''}. Separate multiple tags with commas.
            </DialogDescription>
          </DialogHeader>
          <div className='py-4'>
            <Label htmlFor='tags'>Tags</Label>
            <Input
              id='tags'
              placeholder='urgent, vip, priority'
              value={tagInput}
              onChange={(e) => setTagInput(e.target.value)}
              className='mt-2'
            />
          </div>
          <DialogFooter>
            <Button variant='outline' onClick={() => setShowTagDialog(false)}>
              Cancel
            </Button>
            <Button onClick={handleAddTags}>
              Add Tags
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Cancel Orders</DialogTitle>
            <DialogDescription>
              Are you sure you want to cancel {selectedRows.length} selected order{selectedRows.length > 1 ? 's' : ''}? This action cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant='outline' onClick={() => setShowDeleteDialog(false)}>
              Cancel
            </Button>
            <Button variant='destructive' onClick={handleBulkDelete}>
              Cancel Orders
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}

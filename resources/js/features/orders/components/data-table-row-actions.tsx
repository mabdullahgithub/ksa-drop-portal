import { DotsHorizontalIcon } from '@radix-ui/react-icons'
import { type Row } from '@tanstack/react-table'
import { Eye, Package, DollarSign, Tag } from 'lucide-react'
import { useState } from 'react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { type Order } from '@/types/order'
import { useOrdersContext } from './orders-provider'
import { usePermissions } from '@/hooks/use-permissions'
import { useOrderMutations } from '@/hooks/useOrders'
import { OrderTagsDialog } from './order-tags-dialog'
import { toast } from 'sonner'

type DataTableRowActionsProps<TData> = {
  row: Row<TData>
}

export function DataTableRowActions<TData>({ row }: DataTableRowActionsProps<TData>) {
  const order = row.original as Order
  const { setOpen, setCurrentRow } = useOrdersContext()
  const { can } = usePermissions()
  const { updateFulfillmentStatus, updateFinancialStatus, updateOrder } = useOrderMutations()
  const [showTagDialog, setShowTagDialog] = useState(false)
  const [isSavingTags, setIsSavingTags] = useState(false)

  const canView = can('view orders')
  const canEdit = can('edit orders')

  const handleStatusUpdate = async (status: string) => {
    const success = await updateFulfillmentStatus(order.id, status)
    if (success) {
      toast.success('Order status updated')
      window.location.reload()
    } else {
      toast.error('Failed to update status')
    }
  }

  const handleFinancialUpdate = async (status: string) => {
    const success = await updateFinancialStatus(order.id, status)
    if (success) {
      toast.success('Payment status updated')
      window.location.reload()
    } else {
      toast.error('Failed to update status')
    }
  }

  const handleSaveTags = async (tags: string[]) => {
    setIsSavingTags(true)
    const success = await updateOrder(order.id, { tags })
    setIsSavingTags(false)
    if (success) {
      toast.success('Tags updated')
      setShowTagDialog(false)
      window.location.reload()
    } else {
      toast.error('Failed to update tags')
    }
  }

  if (!canView) return null

  return (
    <>
      <DropdownMenu modal={false}>
        <DropdownMenuTrigger asChild>
          <Button variant='ghost' className='flex h-8 w-8 p-0 data-[state=open]:bg-muted'>
            <DotsHorizontalIcon className='h-4 w-4' />
            <span className='sr-only'>Open menu</span>
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align='end' className='w-48'>
          <DropdownMenuItem
            onClick={() => {
              setCurrentRow(order)
              setOpen('view')
            }}
          >
            <Eye className='mr-2 h-4 w-4' />
            View Details
          </DropdownMenuItem>

          {canEdit && (
            <>
              <DropdownMenuSeparator />

              <DropdownMenuItem onClick={() => setShowTagDialog(true)}>
                <Tag className='mr-2 h-4 w-4' />
                Manage Tags
              </DropdownMenuItem>

              <DropdownMenuSeparator />

              <DropdownMenuSub>
                <DropdownMenuSubTrigger>
                  <Package className='mr-2 h-4 w-4' />
                  Fulfillment Status
                </DropdownMenuSubTrigger>
                <DropdownMenuSubContent>
                  <DropdownMenuItem onClick={() => handleStatusUpdate('pending')}>Pending</DropdownMenuItem>
                  <DropdownMenuItem onClick={() => handleStatusUpdate('unfulfilled')}>Unfulfilled</DropdownMenuItem>
                  <DropdownMenuItem onClick={() => handleStatusUpdate('fulfilled')}>Fulfilled</DropdownMenuItem>
                  <DropdownMenuItem onClick={() => handleStatusUpdate('cancelled')}>Cancelled</DropdownMenuItem>
                </DropdownMenuSubContent>
              </DropdownMenuSub>

              <DropdownMenuSub>
                <DropdownMenuSubTrigger>
                  <DollarSign className='mr-2 h-4 w-4' />
                  Payment Status
                </DropdownMenuSubTrigger>
                <DropdownMenuSubContent>
                  <DropdownMenuItem onClick={() => handleFinancialUpdate('pending')}>Pending</DropdownMenuItem>
                  <DropdownMenuItem onClick={() => handleFinancialUpdate('paid')}>Paid</DropdownMenuItem>
                  <DropdownMenuItem onClick={() => handleFinancialUpdate('partially_refunded')}>Partially Refunded</DropdownMenuItem>
                  <DropdownMenuItem onClick={() => handleFinancialUpdate('refunded')}>Refunded</DropdownMenuItem>
                </DropdownMenuSubContent>
              </DropdownMenuSub>
            </>
          )}
        </DropdownMenuContent>
      </DropdownMenu>

      <OrderTagsDialog
        open={showTagDialog}
        onOpenChange={setShowTagDialog}
        orderNumber={order.order_number}
        currentTags={order.tags ?? []}
        onSave={handleSaveTags}
        isSaving={isSavingTags}
      />
    </>
  )
}

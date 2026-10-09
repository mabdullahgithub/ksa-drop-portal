import { DotsHorizontalIcon } from '@radix-ui/react-icons'
import { type Row } from '@tanstack/react-table'
import { Eye, Package, DollarSign, Tag, Truck, Pencil, MapPin, Trash2 } from 'lucide-react'
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
import { ConfirmDialog } from '@/components/confirm-dialog'
import { toast } from 'sonner'

type DataTableRowActionsProps<TData> = {
  row: Row<TData>
}

export function DataTableRowActions<TData>({ row }: DataTableRowActionsProps<TData>) {
  const order = row.original as Order
  const { setOpen, setCurrentRow, refresh } = useOrdersContext()
  const { can } = usePermissions()
  const { updateFulfillmentStatus, updateFinancialStatus, updateOrder, deleteOrder } = useOrderMutations()
  const [showTagDialog, setShowTagDialog] = useState(false)
  const [isSavingTags, setIsSavingTags] = useState(false)
  const [showDeleteDialog, setShowDeleteDialog] = useState(false)
  const [deleting, setDeleting] = useState(false)

  // Every item in this menu is a permission of its own.
  const canView = can('view order details')
  const canEdit = canView && can('edit orders')
  const canTag = can('tag orders')
  const canShipment = order.latest_shipment
    ? canView && can('view shipments')
    : can('create shipments')
  const canTrack = !!order.latest_shipment && can('view shipments')
  const canFulfillment = can('update order fulfillment status')
  const canPayment = can('update order payment status')
  const canDelete = can('delete orders')

  const hasEditGroup = canEdit || canTag || canShipment || canTrack
  const hasStatusGroup = canFulfillment || canPayment

  const handleStatusUpdate = async (status: string) => {
    const success = await updateFulfillmentStatus(order.id, status)
    if (success) {
      toast.success('Order status updated')
      refresh()
    } else {
      toast.error('Failed to update status')
    }
  }

  const handleFinancialUpdate = async (status: string) => {
    const success = await updateFinancialStatus(order.id, status)
    if (success) {
      toast.success('Payment status updated')
      refresh()
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
      refresh()
    } else {
      toast.error('Failed to update tags')
    }
  }

  const handleDelete = async () => {
    setDeleting(true)
    try {
      await deleteOrder(order.id)
      toast.success(`${order.order_number} moved to the recycle bin`)
      setShowDeleteDialog(false)
      refresh()
    } catch (error) {
      // The server refuses orders with an active shipment, and says why.
      toast.error(error instanceof Error ? error.message : 'Failed to delete order')
    } finally {
      setDeleting(false)
    }
  }

  // Nothing to offer: no menu at all.
  if (!canView && !hasEditGroup && !hasStatusGroup && !canDelete) return null

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
          {canView && (
            <DropdownMenuItem
              onClick={() => {
                setCurrentRow(order)
                setOpen('view')
              }}
            >
              <Eye className='mr-2 h-4 w-4' />
              View Details
            </DropdownMenuItem>
          )}

          {hasEditGroup && (
            <>
              {canView && <DropdownMenuSeparator />}

              {canEdit && (
                <DropdownMenuItem
                  onClick={() => {
                    setCurrentRow(order)
                    setOpen('edit')
                  }}
                >
                  <Pencil className='mr-2 h-4 w-4' />
                  Edit Order
                </DropdownMenuItem>
              )}

              {canTag && (
                <DropdownMenuItem onClick={() => setShowTagDialog(true)}>
                  <Tag className='mr-2 h-4 w-4' />
                  Manage Tag
                </DropdownMenuItem>
              )}

              {canShipment && (
                <DropdownMenuItem
                  onClick={() => {
                    setCurrentRow(order)
                    setOpen(order.latest_shipment ? 'view' : 'shipment')
                  }}
                >
                  <Truck className='mr-2 h-4 w-4' />
                  {order.latest_shipment ? 'View Shipment' : 'Create Shipment'}
                </DropdownMenuItem>
              )}

              {canTrack && (
                <DropdownMenuItem
                  onClick={() => window.open('/track', '_blank')}
                >
                  <MapPin className='mr-2 h-4 w-4' />
                  Track Shipment
                </DropdownMenuItem>
              )}
            </>
          )}

          {hasStatusGroup && (
            <>
              {(canView || hasEditGroup) && <DropdownMenuSeparator />}

              {canFulfillment && (
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
              )}

              {canPayment && (
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
              )}
            </>
          )}

          {canDelete && (
            <>
              {(canView || hasEditGroup || hasStatusGroup) && <DropdownMenuSeparator />}
              <DropdownMenuItem
                onClick={() => setShowDeleteDialog(true)}
                className='text-destructive focus:text-destructive'
              >
                <Trash2 className='mr-2 h-4 w-4' />
                Delete Order
              </DropdownMenuItem>
            </>
          )}
        </DropdownMenuContent>
      </DropdownMenu>

      <ConfirmDialog
        open={showDeleteDialog}
        onOpenChange={setShowDeleteDialog}
        title='Delete order'
        desc={
          <span>
            Move <strong>{order.order_number}</strong> to the recycle bin? You can restore it from
            there. An order with an active shipment cannot be deleted.
          </span>
        }
        confirmText='Delete'
        destructive
        isLoading={deleting}
        handleConfirm={handleDelete}
      />

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

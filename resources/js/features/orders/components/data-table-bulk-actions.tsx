import { useState, useEffect } from 'react'
import { type Table } from '@tanstack/react-table'
import { Package, DollarSign, Tag, Trash2, Truck, CheckCircle2, XCircle } from 'lucide-react'
import { toast } from 'sonner'
import axios from 'axios'
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
import { usePermissions } from '@/hooks/use-permissions'

interface Warehouse {
  id: number
  name: string
  city: string
  is_default: boolean
}

interface BulkShipmentResult {
  created: { id: number; tracking_number: string | null }[]
  failed: { order_id: number; error: string }[]
}

type DataTableBulkActionsProps<TData> = {
  table: Table<TData>
}

export function DataTableBulkActions<TData>({
  table,
}: DataTableBulkActionsProps<TData>) {
  const selectedRows = table.getFilteredSelectedRowModel().rows
  const { bulkUpdate } = useOrderMutations()
  const { can } = usePermissions()
  const [showTagDialog, setShowTagDialog] = useState(false)
  const [showDeleteDialog, setShowDeleteDialog] = useState(false)
  const [tagInput, setTagInput] = useState('')

  // Bulk shipment state
  const [showShipmentDialog, setShowShipmentDialog] = useState(false)
  const [warehouses, setWarehouses] = useState<Warehouse[]>([])
  const [selectedWarehouse, setSelectedWarehouse] = useState<number | ''>('')
  const [shipmentServiceType, setShipmentServiceType] = useState('02')
  const [shipmentWeight, setShipmentWeight] = useState('0.5')
  const [creatingShipments, setCreatingShipments] = useState(false)
  const [shipmentResult, setShipmentResult] = useState<BulkShipmentResult | null>(null)

  useEffect(() => {
    if (showShipmentDialog && warehouses.length === 0) {
      axios
        .get('/api/warehouses')
        .then((res) => {
          const wh: Warehouse[] = res.data.warehouses || []
          setWarehouses(wh)
          const def = wh.find((w) => w.is_default) || wh[0]
          if (def) setSelectedWarehouse(def.id)
        })
        .catch(() => toast.error('Failed to load warehouses'))
    }
  }, [showShipmentDialog, warehouses.length])

  const handleCreateShipments = async () => {
    if (!selectedWarehouse) {
      toast.error('Please select a warehouse')
      return
    }
    const orderIds = selectedRows.map((row) => (row.original as Order).id)
    setCreatingShipments(true)
    setShipmentResult(null)
    try {
      const res = await axios.post('/api/shipments/bulk', {
        order_ids: orderIds,
        warehouse_id: selectedWarehouse,
        weight: parseFloat(shipmentWeight) || 0.5,
        service_type: shipmentServiceType,
      })
      setShipmentResult({ created: res.data.created || [], failed: res.data.failed || [] })
      toast.success(res.data.message)
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Failed to create shipments')
    } finally {
      setCreatingShipments(false)
    }
  }

  const closeShipmentDialog = () => {
    setShowShipmentDialog(false)
    if (shipmentResult && shipmentResult.created.length > 0) {
      table.resetRowSelection()
      window.location.reload()
    }
    setShipmentResult(null)
  }

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

        {can('edit orders') && (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant='outline'
                size='icon'
                onClick={() => setShowShipmentDialog(true)}
                className='size-8'
                aria-label='Create shipments'
                title='Create shipments'
              >
                <Truck className='h-4 w-4' />
                <span className='sr-only'>Create shipments</span>
              </Button>
            </TooltipTrigger>
            <TooltipContent>
              <p>Create shipments</p>
            </TooltipContent>
          </Tooltip>
        )}

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

      {/* Create Shipments Dialog */}
      <Dialog
        open={showShipmentDialog}
        onOpenChange={(open) => (open ? setShowShipmentDialog(true) : closeShipmentDialog())}
      >
        <DialogContent className='max-w-lg'>
          <DialogHeader>
            <DialogTitle>Create Shipments</DialogTitle>
            <DialogDescription>
              Create J&amp;T Express shipments for {selectedRows.length} selected order
              {selectedRows.length > 1 ? 's' : ''}. Orders that already have an active shipment will be skipped.
            </DialogDescription>
          </DialogHeader>

          {!shipmentResult ? (
            <div className='space-y-4 py-2'>
              <div className='space-y-2'>
                <Label>Sender Warehouse</Label>
                <select
                  className='flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm'
                  value={selectedWarehouse}
                  onChange={(e) => setSelectedWarehouse(Number(e.target.value))}
                >
                  <option value=''>Select a warehouse...</option>
                  {warehouses.map((w) => (
                    <option key={w.id} value={w.id}>
                      {w.name} {w.is_default ? '(Default)' : ''} — {w.city}
                    </option>
                  ))}
                </select>
                {warehouses.length === 0 && (
                  <p className='text-xs text-muted-foreground'>
                    No warehouses configured. Add one in J&amp;T Settings first.
                  </p>
                )}
              </div>
              <div className='grid grid-cols-2 gap-3'>
                <div className='space-y-2'>
                  <Label>Default Weight (kg)</Label>
                  <Input
                    type='number'
                    step='0.1'
                    value={shipmentWeight}
                    onChange={(e) => setShipmentWeight(e.target.value)}
                  />
                </div>
                <div className='space-y-2'>
                  <Label>Service Type</Label>
                  <select
                    className='flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm'
                    value={shipmentServiceType}
                    onChange={(e) => setShipmentServiceType(e.target.value)}
                  >
                    <option value='01'>Express</option>
                    <option value='02'>Standard</option>
                  </select>
                </div>
              </div>
            </div>
          ) : (
            <div className='space-y-3 py-2 max-h-72 overflow-y-auto'>
              {shipmentResult.created.length > 0 && (
                <div className='space-y-2'>
                  <div className='flex items-center gap-2 text-sm font-medium text-green-600'>
                    <CheckCircle2 className='h-4 w-4' />
                    {shipmentResult.created.length} shipment(s) created
                  </div>
                  {shipmentResult.created.map((s) => (
                    <div key={s.id} className='text-xs text-muted-foreground pl-6 font-mono'>
                      {s.tracking_number || '—'}
                    </div>
                  ))}
                </div>
              )}
              {shipmentResult.failed.length > 0 && (
                <div className='space-y-2'>
                  <div className='flex items-center gap-2 text-sm font-medium text-red-600'>
                    <XCircle className='h-4 w-4' />
                    {shipmentResult.failed.length} failed
                  </div>
                  {shipmentResult.failed.map((f, i) => (
                    <div key={i} className='text-xs text-muted-foreground pl-6'>
                      Order #{f.order_id}: {f.error}
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          <DialogFooter>
            {!shipmentResult ? (
              <>
                <Button variant='outline' onClick={closeShipmentDialog}>
                  Cancel
                </Button>
                <Button
                  onClick={handleCreateShipments}
                  disabled={creatingShipments || !selectedWarehouse}
                >
                  {creatingShipments ? 'Creating...' : 'Create Shipments'}
                </Button>
              </>
            ) : (
              <Button onClick={closeShipmentDialog}>Done</Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}

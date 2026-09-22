import { useState, useEffect } from 'react'
import { type Table } from '@tanstack/react-table'
import { Tag, Trash2, Truck, FileText, Loader2, CheckCircle2, XCircle, AlertTriangle, Ban } from 'lucide-react'
import { toast } from 'sonner'
import axios from 'axios'
import { Button } from '@/components/ui/button'
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
import { useOrdersContext } from './orders-provider'
import { OrderTagsDialog } from './order-tags-dialog'

type Courier = 'jnt_express' | 'imile' | 'logestechs' | 'ksadrop_express'

const COURIER_LABELS: Record<Courier, string> = {
  jnt_express: 'J&T Express',
  imile: 'iMile',
  logestechs: 'LogesTechs',
  ksadrop_express: 'KSA Express',
}

interface Warehouse {
  id: number
  name: string
  city: string
  is_default: boolean
}

interface WaybillError {
  order_id: number
  order_number: string | null
  error: string
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
  const { bulkUpdate, bulkDelete } = useOrderMutations()
  const { can } = usePermissions()
  const { refresh } = useOrdersContext()
  const [showTagDialog, setShowTagDialog] = useState(false)
  const [isSavingTags, setIsSavingTags] = useState(false)
  const [showCancelDialog, setShowCancelDialog] = useState(false)
  const [showDeleteDialog, setShowDeleteDialog] = useState(false)
  const [deleting, setDeleting] = useState(false)

  // Bulk shipment state
  const [showShipmentDialog, setShowShipmentDialog] = useState(false)
  const [warehouses, setWarehouses] = useState<Warehouse[]>([])
  const [selectedWarehouse, setSelectedWarehouse] = useState<number | ''>('')
  const [shipmentCourier, setShipmentCourier] = useState<Courier>('jnt_express')
  const [shipmentServiceType, setShipmentServiceType] = useState('02')
  const [shipmentGoodsType, setShipmentGoodsType] = useState('ITN1')
  const [logesTechsServiceType, setLogesTechsServiceType] = useState('STANDARD')
  const [shipmentWeight, setShipmentWeight] = useState('0.5')
  const [shipmentRemark, setShipmentRemark] = useState('')
  const [creatingShipments, setCreatingShipments] = useState(false)
  const [shipmentResult, setShipmentResult] = useState<BulkShipmentResult | null>(null)

  // Bulk waybill state
  const [generatingWaybills, setGeneratingWaybills] = useState(false)
  const [waybillErrors, setWaybillErrors] = useState<WaybillError[] | null>(null)

  const isLogesTechs = shipmentCourier === 'logestechs'

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
        courier: shipmentCourier,
        weight: parseFloat(shipmentWeight) || 0.5,
        ...(shipmentCourier === 'jnt_express'
          ? { service_type: shipmentServiceType, goods_type: shipmentGoodsType }
          : {}),
        // LogesTechs uses plain-string service types instead of J&T's '01'/'02'.
        // Its district and National Address are assigned server-side per order
        // (see ShipmentController::bulkStore) — there is no per-order field for
        // them in a bulk run.
        ...(isLogesTechs ? { service_type: logesTechsServiceType } : {}),
        ...(shipmentRemark.trim() ? { remark: shipmentRemark.trim() } : {}),
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
      refresh()
    }
    setShipmentResult(null)
    setShipmentRemark('')
  }

  const handleGenerateWaybills = async () => {
    const orderIds = selectedRows.map((row) => (row.original as Order).id)
    setGeneratingWaybills(true)
    try {
      const res = await axios.post(
        '/api/shipments/waybills/bulk',
        { order_ids: orderIds },
        { responseType: 'blob' }
      )
      const url = URL.createObjectURL(res.data)
      const link = document.createElement('a')
      link.href = url
      link.download =
        res.headers['content-disposition']?.match(/filename="?([^"]+)"?/)?.[1] ?? 'ksa-express-waybills.pdf'
      document.body.appendChild(link)
      link.click()
      link.remove()
      URL.revokeObjectURL(url)
      toast.success(`Generated ${orderIds.length} waybill${orderIds.length > 1 ? 's' : ''}`)
    } catch (err: any) {
      // With responseType 'blob' the JSON error body arrives as a Blob too.
      let body: { message?: string; errors?: WaybillError[] } = {}
      try {
        body = JSON.parse(await err.response?.data?.text())
      } catch {
        // not JSON — fall through to the generic message
      }
      if (body.errors?.length) {
        setWaybillErrors(body.errors)
      } else {
        toast.error(body.message || 'Failed to generate waybills')
      }
    } finally {
      setGeneratingWaybills(false)
    }
  }

  const handleAddTags = async (tags: string[]) => {
    const selectedOrders = selectedRows.map((row) => (row.original as Order).id)

    if (tags.length === 0) {
      toast.error('Please select a tag')
      return
    }

    setIsSavingTags(true)
    try {
      await bulkUpdate({
        order_ids: selectedOrders,
        action: 'add_tags',
        tags,
      })
      table.resetRowSelection()
      setShowTagDialog(false)
      toast.success(`Tag assigned to ${selectedOrders.length} order${selectedOrders.length > 1 ? 's' : ''}`)
      refresh()
    } catch {
      toast.error('Failed to assign tag')
    } finally {
      setIsSavingTags(false)
    }
  }

  const handleBulkCancel = async () => {
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
          setShowCancelDialog(false)
          refresh()
          return `Cancelled ${selectedOrders.length} order${selectedOrders.length > 1 ? 's' : ''}`
        },
        error: 'Failed to cancel orders',
      }
    )
  }

  /**
   * A real delete: the orders move to the recycle bin. Distinct from Cancel
   * above, which only sets the fulfillment status.
   */
  const handleBulkDelete = async () => {
    const selectedOrders = selectedRows.map((row) => (row.original as Order).id)

    setDeleting(true)
    try {
      const result = await bulkDelete(selectedOrders)

      if (result.deleted_count > 0) {
        toast.success(
          `Moved ${result.deleted_count} order${result.deleted_count > 1 ? 's' : ''} to the recycle bin`
        )
      }

      // Orders with an active shipment are refused server-side; name them
      // rather than reporting a clean success for a partial batch.
      result.blocked?.forEach((item) => {
        toast.warning(`${item.order_number} was not deleted. ${item.reason}`)
      })

      if (result.deleted_count === 0 && !result.blocked?.length) {
        toast.error('No orders were deleted')
      }

      table.resetRowSelection()
      setShowDeleteDialog(false)
      refresh()
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'Failed to delete orders')
    } finally {
      setDeleting(false)
    }
  }

  return (
    <>
      <BulkActionsToolbar table={table} entityName='order'>
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant='outline'
              size='icon'
              onClick={() => setShowTagDialog(true)}
              className='size-8'
              aria-label='Assign tag'
              title='Assign tag'
            >
              <Tag className='h-4 w-4' />
              <span className='sr-only'>Assign tag</span>
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            <p>Assign tag</p>
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

        {can('edit orders') && (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant='outline'
                size='icon'
                onClick={handleGenerateWaybills}
                disabled={generatingWaybills}
                className='size-8'
                aria-label='Generate waybill'
                title='Generate waybill'
              >
                {generatingWaybills ? (
                  <Loader2 className='h-4 w-4 animate-spin' />
                ) : (
                  <FileText className='h-4 w-4' />
                )}
                <span className='sr-only'>Generate waybill</span>
              </Button>
            </TooltipTrigger>
            <TooltipContent>
              <p>Generate waybill (KSA Express)</p>
            </TooltipContent>
          </Tooltip>
        )}

        {/* Cancel only sets the order status -- deliberately not a trash icon,
            so it cannot be mistaken for the delete button beside it. */}
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant='outline'
              size='icon'
              onClick={() => setShowCancelDialog(true)}
              className='size-8'
              aria-label='Cancel orders'
              title='Cancel orders'
            >
              <Ban className='h-4 w-4' />
              <span className='sr-only'>Cancel orders</span>
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            <p>Cancel orders (keeps them in the list)</p>
          </TooltipContent>
        </Tooltip>

        {can('delete orders') && (
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant='destructive'
                size='icon'
                onClick={() => setShowDeleteDialog(true)}
                className='size-8'
                aria-label='Delete orders'
                title='Delete orders'
              >
                <Trash2 className='h-4 w-4' />
                <span className='sr-only'>Delete orders</span>
              </Button>
            </TooltipTrigger>
            <TooltipContent>
              <p>Delete orders (moves to recycle bin)</p>
            </TooltipContent>
          </Tooltip>
        )}
      </BulkActionsToolbar>

      <OrderTagsDialog
        open={showTagDialog}
        onOpenChange={setShowTagDialog}
        orderNumber={`${selectedRows.length} order${selectedRows.length !== 1 ? 's' : ''}`}
        currentTags={[]}
        onSave={handleAddTags}
        isSaving={isSavingTags}
      />

      {/* Waybill errors: orders that block the bulk waybill */}
      <Dialog open={waybillErrors !== null} onOpenChange={(open) => !open && setWaybillErrors(null)}>
        <DialogContent className='max-w-lg'>
          <DialogHeader>
            <DialogTitle>Cannot generate waybills</DialogTitle>
            <DialogDescription>
              Waybills are generated only when every selected order has a shipment created with KSA Express.
              Fix or deselect these orders and try again.
            </DialogDescription>
          </DialogHeader>
          <div className='max-h-72 space-y-2 overflow-y-auto'>
            {waybillErrors?.map((e) => (
              <div key={e.order_id} className='flex items-start gap-2 rounded-md border p-2 text-sm'>
                <XCircle className='mt-0.5 h-4 w-4 shrink-0 text-destructive' />
                <div>
                  <p className='font-medium'>Order {e.order_number ?? e.order_id}</p>
                  <p className='text-muted-foreground'>{e.error}</p>
                </div>
              </div>
            ))}
          </div>
          <DialogFooter>
            <Button variant='outline' onClick={() => setWaybillErrors(null)}>
              Close
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Cancel Confirmation Dialog */}
      <Dialog open={showCancelDialog} onOpenChange={setShowCancelDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Cancel Orders</DialogTitle>
            <DialogDescription>
              Mark {selectedRows.length} selected order{selectedRows.length > 1 ? 's' : ''} as
              cancelled. They stay in the orders list.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant='outline' onClick={() => setShowCancelDialog(false)}>
              Keep as is
            </Button>
            <Button variant='destructive' onClick={handleBulkCancel}>
              Cancel Orders
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Delete Orders</DialogTitle>
            <DialogDescription>
              Move {selectedRows.length} selected order{selectedRows.length > 1 ? 's' : ''} to the
              recycle bin. You can restore {selectedRows.length > 1 ? 'them' : 'it'} from there.
              Orders with an active shipment will be skipped.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant='outline' onClick={() => setShowDeleteDialog(false)} disabled={deleting}>
              Cancel
            </Button>
            <Button variant='destructive' onClick={handleBulkDelete} disabled={deleting}>
              {deleting ? 'Deleting…' : 'Delete Orders'}
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
              Create {COURIER_LABELS[shipmentCourier]} shipments for {selectedRows.length} selected order
              {selectedRows.length > 1 ? 's' : ''}. Orders that already have an active shipment will be skipped.
            </DialogDescription>
          </DialogHeader>

          {!shipmentResult ? (
            <div className='space-y-4 py-2'>
              <div className='space-y-2'>
                <Label>Courier</Label>
                <select
                  className='flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm'
                  value={shipmentCourier}
                  onChange={(e) => setShipmentCourier(e.target.value as Courier)}
                >
                  <option value='jnt_express'>J&amp;T Express</option>
                  <option value='imile'>iMile</option>
                  <option value='logestechs'>LogesTechs</option>
                  <option value='ksadrop_express'>KSA Express</option>
                </select>
              </div>

              {isLogesTechs && (
                <div className='flex gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400'>
                  <AlertTriangle className='mt-0.5 h-4 w-4 shrink-0' />
                  <p>
                    LogesTechs requires a destination district and a Saudi National Address, and
                    orders carry neither. In a bulk run both are assigned automatically — a random
                    district from LogesTechs&apos; own list and a generated National Address — so the
                    destination on these shipments will not match the customer&apos;s real address.
                    Use the single-order dialog when the real district matters.
                  </p>
                </div>
              )}
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
                {isLogesTechs && (
                  <div className='space-y-2'>
                    <Label>Service Type</Label>
                    <select
                      className='flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm'
                      value={logesTechsServiceType}
                      onChange={(e) => setLogesTechsServiceType(e.target.value)}
                    >
                      <option value='STANDARD'>Standard</option>
                      <option value='EXPRESS'>Express</option>
                    </select>
                  </div>
                )}
                {shipmentCourier === 'jnt_express' && (
                  <div className='space-y-2'>
                    <Label>Service Type</Label>
                    <select
                      className='flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm'
                      value={shipmentServiceType}
                      onChange={(e) => setShipmentServiceType(e.target.value)}
                    >
                      <option value='01'>Express (pickup at door)</option>
                      <option value='02'>Standard (drop at J&T store)</option>
                    </select>
                  </div>
                )}
                {shipmentCourier === 'jnt_express' && (
                  <div className='space-y-2 col-span-2'>
                    <Label>Goods Type <span className='text-xs text-muted-foreground font-normal'>(optional)</span></Label>
                    <select
                      className='flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm'
                      value={shipmentGoodsType}
                      onChange={(e) => setShipmentGoodsType(e.target.value)}
                    >
                      <option value='ITN1'>Clothes (ITN1)</option>
                      <option value='ITN2'>Document (ITN2)</option>
                      <option value='ITN3'>Food (ITN3)</option>
                      <option value='ITN4'>Others (ITN4)</option>
                      <option value='ITN5'>Digital Product (ITN5)</option>
                      <option value='ITN6'>Daily Necessities (ITN6)</option>
                      <option value='ITN7'>Fragile Items (ITN7)</option>
                    </select>
                  </div>
                )}
                <div className='space-y-2 col-span-2'>
                  <Label>Remark <span className='text-xs text-muted-foreground font-normal'>(optional, forwarded to the courier)</span></Label>
                  <Input
                    value={shipmentRemark}
                    onChange={(e) => setShipmentRemark(e.target.value)}
                    placeholder='Order notes or special instructions'
                    maxLength={200}
                  />
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

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import { type Order } from '@/types/order'
import { format } from 'date-fns'
import { Package, DollarSign, MapPin, Phone, Calendar, Tag, Pencil, Save, X } from 'lucide-react'
import { ShipmentPanel } from './shipment-panel'
import { CreateShipmentDialog } from './create-shipment-dialog'
import { InvoicePanel } from './invoice-panel'
import { useState, useEffect, useCallback } from 'react'
import axios from 'axios'
import { usePermissions } from '@/hooks/use-permissions'
import { useOrderMutations } from '@/hooks/useOrders'
import { toast } from 'sonner'

interface OrderDetailsDialogProps {
  order: Order | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved?: () => void
  startInEdit?: boolean
}

// Fields the admin can edit. Mirrors the validation in OrderController::update.
const EDITABLE_FIELDS = [
  'customer_name', 'customer_email', 'customer_phone',
  'shipping_name', 'shipping_phone', 'shipping_address1', 'shipping_address2',
  'shipping_city', 'shipping_province', 'shipping_zip', 'shipping_country',
  'fulfillment_status', 'financial_status', 'risk_level',
  'currency', 'subtotal', 'shipping_cost', 'taxes', 'discount_amount',
  'total', 'refunded_amount', 'outstanding_balance',
  'payment_method', 'payment_reference', 'shipping_method',
  'notes',
] as const

type FormState = Record<string, string> & { tags: string }

function buildForm(order: Order): FormState {
  const form: Record<string, string> = {}
  for (const key of EDITABLE_FIELDS) {
    const value = (order as unknown as Record<string, unknown>)[key]
    form[key] = value === null || value === undefined ? '' : String(value)
  }
  return { ...form, tags: (order.tags ?? []).join(', ') } as FormState
}

export function OrderDetailsDialog({ order, open, onOpenChange, onSaved, startInEdit = false }: OrderDetailsDialogProps) {
  const [createShipmentOpen, setCreateShipmentOpen] = useState(false)
  const [currentOrder, setCurrentOrder] = useState<Order | null>(order)
  const [editing, setEditing] = useState(false)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState<FormState | null>(order ? buildForm(order) : null)

  const { can } = usePermissions()
  const { updateOrder } = useOrderMutations()
  const canEdit = can('edit orders')

  useEffect(() => {
    setCurrentOrder(order)
    setEditing(false)
    if (order) setForm(buildForm(order))
  }, [order])

  const refetchOrder = useCallback(async () => {
    if (!order) return
    try {
      const res = await axios.get(`/api/orders/${order.id}`)
      setCurrentOrder(res.data)
      setForm(buildForm(res.data))
    } catch {
      // keep existing data on failure
    }
  }, [order])

  // The list row lacks invoices/full shipment data, so pull the complete order
  // whenever the dialog opens — otherwise a generated waybill looks ungenerated.
  useEffect(() => {
    if (open && order) {
      refetchOrder()
    }
  }, [open, order, refetchOrder])

  // When opened straight into edit mode (from the row actions menu).
  useEffect(() => {
    if (open && startInEdit && canEdit) {
      setEditing(true)
    }
  }, [open, startInEdit, canEdit])

  const setField = (key: string, value: string) =>
    setForm((prev) => (prev ? { ...prev, [key]: value } : prev))

  const startEditing = () => {
    if (currentOrder) setForm(buildForm(currentOrder))
    setEditing(true)
  }

  const cancelEditing = () => {
    if (currentOrder) setForm(buildForm(currentOrder))
    setEditing(false)
  }

  const handleSave = async () => {
    if (!currentOrder || !form) return
    setSaving(true)

    const payload: Record<string, unknown> = {}
    for (const key of EDITABLE_FIELDS) {
      payload[key] = form[key] === '' ? null : form[key]
    }
    payload.tags = form.tags
      .split(',')
      .map((t) => t.trim())
      .filter(Boolean)

    const success = await updateOrder(currentOrder.id, payload as Partial<Order>)
    setSaving(false)

    if (success) {
      toast.success('Order updated')
      setEditing(false)
      await refetchOrder()
      onSaved?.()
    } else {
      toast.error('Failed to update order')
    }
  }

  if (!order || !currentOrder) return null

  const statusColorMap: Record<string, string> = {
    success: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    warning: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    info: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    error: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    default: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
  }

  const renderField = (label: string, key: string, type: 'text' | 'number' | 'email' = 'text') => (
    <div className='space-y-1'>
      <Label className='text-xs text-muted-foreground'>{label}</Label>
      <Input
        type={type}
        value={form?.[key] ?? ''}
        onChange={(e) => setField(key, e.target.value)}
        step={type === 'number' ? '0.01' : undefined}
      />
    </div>
  )

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-w-3xl max-h-[90vh] overflow-y-auto'>
        <DialogHeader>
          <div className='flex items-center justify-between gap-4 pe-6'>
            <DialogTitle className='text-2xl'>Order {order.order_number}</DialogTitle>
            {canEdit && !editing && (
              <Button variant='outline' size='sm' onClick={startEditing}>
                <Pencil className='mr-2 h-4 w-4' />
                Edit
              </Button>
            )}
            {editing && (
              <div className='flex items-center gap-2'>
                <Button variant='ghost' size='sm' onClick={cancelEditing} disabled={saving}>
                  <X className='mr-2 h-4 w-4' />
                  Cancel
                </Button>
                <Button size='sm' onClick={handleSave} disabled={saving}>
                  <Save className='mr-2 h-4 w-4' />
                  {saving ? 'Saving…' : 'Save'}
                </Button>
              </div>
            )}
          </div>
        </DialogHeader>

        {editing && form ? (
          /* ---------- Edit form ---------- */
          <div className='space-y-6'>
            {/* Status */}
            <div>
              <h3 className='font-semibold mb-3'>Status</h3>
              <div className='grid grid-cols-2 gap-4 sm:grid-cols-3'>
                <div className='space-y-1'>
                  <Label className='text-xs text-muted-foreground'>Fulfillment</Label>
                  <Select value={form.fulfillment_status} onValueChange={(v) => setField('fulfillment_status', v)}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value='pending'>Pending</SelectItem>
                      <SelectItem value='unfulfilled'>Unfulfilled</SelectItem>
                      <SelectItem value='fulfilled'>Fulfilled</SelectItem>
                      <SelectItem value='cancelled'>Cancelled</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className='space-y-1'>
                  <Label className='text-xs text-muted-foreground'>Payment</Label>
                  <Select value={form.financial_status} onValueChange={(v) => setField('financial_status', v)}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value='pending'>Pending</SelectItem>
                      <SelectItem value='paid'>Paid</SelectItem>
                      <SelectItem value='partially_refunded'>Partially Refunded</SelectItem>
                      <SelectItem value='refunded'>Refunded</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                {renderField('Risk Level', 'risk_level')}
              </div>
            </div>

            <Separator />

            {/* Customer */}
            <div>
              <h3 className='font-semibold mb-3'>Customer Information</h3>
              <div className='grid grid-cols-2 gap-4'>
                {renderField('Name', 'customer_name')}
                {renderField('Phone', 'customer_phone')}
                {renderField('Email', 'customer_email', 'email')}
              </div>
            </div>

            <Separator />

            {/* Shipping address */}
            <div>
              <h3 className='font-semibold mb-3'>Shipping Address</h3>
              <div className='grid grid-cols-2 gap-4'>
                {renderField('Name', 'shipping_name')}
                {renderField('Phone', 'shipping_phone')}
                {renderField('Address 1', 'shipping_address1')}
                {renderField('Address 2', 'shipping_address2')}
                {renderField('City', 'shipping_city')}
                {renderField('Province', 'shipping_province')}
                {renderField('Zip', 'shipping_zip')}
                {renderField('Country', 'shipping_country')}
              </div>
            </div>

            <Separator />

            {/* Amounts */}
            <div>
              <h3 className='font-semibold mb-3'>Amounts</h3>
              <div className='grid grid-cols-2 gap-4 sm:grid-cols-3'>
                {renderField('Currency', 'currency')}
                {renderField('Subtotal', 'subtotal', 'number')}
                {renderField('Shipping', 'shipping_cost', 'number')}
                {renderField('Taxes', 'taxes', 'number')}
                {renderField('Discount', 'discount_amount', 'number')}
                {renderField('Total', 'total', 'number')}
                {renderField('Refunded', 'refunded_amount', 'number')}
                {renderField('Outstanding', 'outstanding_balance', 'number')}
              </div>
            </div>

            <Separator />

            {/* Payment & shipping method */}
            <div>
              <h3 className='font-semibold mb-3'>Payment</h3>
              <div className='grid grid-cols-2 gap-4'>
                {renderField('Payment Method', 'payment_method')}
                {renderField('Payment Reference', 'payment_reference')}
                {renderField('Shipping Method', 'shipping_method')}
              </div>
            </div>

            <Separator />

            {/* Tags & notes */}
            <div className='space-y-4'>
              <div className='space-y-1'>
                <Label className='text-xs text-muted-foreground'>Tags (comma separated)</Label>
                <Input value={form.tags} onChange={(e) => setField('tags', e.target.value)} />
              </div>
              <div className='space-y-1'>
                <Label className='text-xs text-muted-foreground'>Notes</Label>
                <Textarea
                  value={form.notes}
                  onChange={(e) => setField('notes', e.target.value)}
                  rows={4}
                />
              </div>
            </div>
          </div>
        ) : (
          /* ---------- Read-only view ---------- */
          <div className='space-y-6'>
          {/* Status Section */}
          <div className='flex flex-wrap gap-4'>
            <div>
              <div className='text-sm font-medium text-muted-foreground mb-1'>Fulfillment</div>
              <Badge variant='outline' className={`text-xs capitalize ${statusColorMap[order.status_color] || statusColorMap.default}`}>
                {order.fulfillment_status}
              </Badge>
            </div>
            <div>
              <div className='text-sm font-medium text-muted-foreground mb-1'>Payment</div>
              <Badge variant='outline' className={`capitalize ${statusColorMap[order.financial_status_color] || statusColorMap.default}`}>
                {order.financial_status}
              </Badge>
            </div>
            {order.risk_level && (
              <div>
                <div className='text-sm font-medium text-muted-foreground mb-1'>Risk Level</div>
                <Badge variant='outline'>{order.risk_level}</Badge>
              </div>
            )}
          </div>

          <Separator />

          {/* Customer Information */}
          <div>
            <h3 className='font-semibold mb-3 flex items-center gap-2'>
              <Phone className='h-4 w-4' />
              Customer Information
            </h3>
            <div className='grid grid-cols-2 gap-4 text-sm'>
              <div>
                <div className='text-muted-foreground'>Name</div>
                <div className='font-medium'>{order.customer_name || 'N/A'}</div>
              </div>
              <div>
                <div className='text-muted-foreground'>Phone</div>
                <div className='font-medium' dir='ltr'>{order.customer_phone || 'N/A'}</div>
              </div>
              {order.customer_email && (
                <div>
                  <div className='text-muted-foreground'>Email</div>
                  <div className='font-medium'>{order.customer_email}</div>
                </div>
              )}
            </div>
          </div>

          <Separator />

          {/* Shipping Address */}
          {order.shipping_name && (
            <>
              <div>
                <h3 className='font-semibold mb-3 flex items-center gap-2'>
                  <MapPin className='h-4 w-4' />
                  Shipping Address
                </h3>
                <div className='text-sm space-y-1'>
                  <div>{order.shipping_name}</div>
                  {order.shipping_address1 && <div>{order.shipping_address1}</div>}
                  {order.shipping_address2 && <div>{order.shipping_address2}</div>}
                  <div>
                    {order.shipping_city && `${order.shipping_city}, `}
                    {order.shipping_country}
                  </div>
                  {order.shipping_phone && <div dir='ltr'>{order.shipping_phone}</div>}
                </div>
              </div>
              <Separator />
            </>
          )}

          {/* Order Items */}
          {order.items && order.items.length > 0 && (
            <>
              <div>
                <h3 className='font-semibold mb-3 flex items-center gap-2'>
                  <Package className='h-4 w-4' />
                  Order Items
                </h3>
                <div className='space-y-3'>
                  {order.items.map((item) => (
                    <div key={item.id} className='flex justify-between items-start p-3 bg-muted/50 rounded-lg'>
                      <div className='flex-1'>
                        <div className='font-medium'>{item.lineitem_name}</div>
                        {item.variant_name && (
                          <div className='text-sm text-muted-foreground'>Variant: {item.variant_name}</div>
                        )}
                        <div className='text-sm text-muted-foreground mt-1'>
                          Qty: {item.lineitem_quantity} × {order.currency} {item.formatted_price}
                        </div>
                      </div>
                      <div className='text-right'>
                        <div className='font-semibold'>
                          {order.currency} {item.total_price.toFixed(2)}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
              <Separator />
            </>
          )}

          {/* Pricing Summary */}
          <div>
            <h3 className='font-semibold mb-3 flex items-center gap-2'>
              <DollarSign className='h-4 w-4' />
              Order Summary
            </h3>
            <div className='space-y-2 text-sm'>
              <div className='flex justify-between'>
                <span className='text-muted-foreground'>Subtotal</span>
                <span>{order.currency} {order.subtotal}</span>
              </div>
              {parseFloat(order.shipping_cost) > 0 && (
                <div className='flex justify-between'>
                  <span className='text-muted-foreground'>Shipping</span>
                  <span>{order.currency} {order.shipping_cost}</span>
                </div>
              )}
              {parseFloat(order.taxes) > 0 && (
                <div className='flex justify-between'>
                  <span className='text-muted-foreground'>Taxes</span>
                  <span>{order.currency} {order.taxes}</span>
                </div>
              )}
              {parseFloat(order.discount_amount) > 0 && (
                <div className='flex justify-between text-green-600'>
                  <span>Discount</span>
                  <span>-{order.currency} {order.discount_amount}</span>
                </div>
              )}
              <Separator />
              <div className='flex justify-between font-semibold text-base'>
                <span>Total</span>
                <span>{order.formatted_total}</span>
              </div>
              {order.payment_method && (
                <div className='flex justify-between text-muted-foreground'>
                  <span>Payment Method</span>
                  <span>{order.payment_method}</span>
                </div>
              )}
            </div>
          </div>

          {/* Timestamps */}
          <div>
            <h3 className='font-semibold mb-3 flex items-center gap-2'>
              <Calendar className='h-4 w-4' />
              Timeline
            </h3>
            <div className='space-y-2 text-sm'>
              <div className='flex justify-between'>
                <span className='text-muted-foreground'>Created</span>
                <span>{format(new Date(order.created_at), 'PPpp')}</span>
              </div>
              {order.paid_at && (
                <div className='flex justify-between'>
                  <span className='text-muted-foreground'>Paid</span>
                  <span>{format(new Date(order.paid_at), 'PPpp')}</span>
                </div>
              )}
              {order.fulfilled_at && (
                <div className='flex justify-between'>
                  <span className='text-muted-foreground'>Fulfilled</span>
                  <span>{format(new Date(order.fulfilled_at), 'PPpp')}</span>
                </div>
              )}
              {order.cancelled_at && (
                <div className='flex justify-between'>
                  <span className='text-muted-foreground'>Cancelled</span>
                  <span>{format(new Date(order.cancelled_at), 'PPpp')}</span>
                </div>
              )}
            </div>
          </div>

          {/* Marketing Info */}
          {(order.utm_source || order.utm_campaign) && (
            <>
              <Separator />
              <div>
                <h3 className='font-semibold mb-3 flex items-center gap-2'>
                  <Tag className='h-4 w-4' />
                  Marketing
                </h3>
                <div className='space-y-2 text-sm'>
                  {order.utm_source && (
                    <div className='flex justify-between'>
                      <span className='text-muted-foreground'>Source</span>
                      <span className='capitalize'>{order.utm_source}</span>
                    </div>
                  )}
                  {order.utm_medium && (
                    <div className='flex justify-between'>
                      <span className='text-muted-foreground'>Medium</span>
                      <span className='capitalize'>{order.utm_medium}</span>
                    </div>
                  )}
                  {order.utm_campaign && (
                    <div className='flex justify-between'>
                      <span className='text-muted-foreground'>Campaign</span>
                      <span className='text-xs'>{decodeURIComponent(order.utm_campaign)}</span>
                    </div>
                  )}
                </div>
              </div>
            </>
          )}

          {/* Tags */}
          {order.tags && order.tags.length > 0 && (
            <>
              <Separator />
              <div>
                <h3 className='font-semibold mb-3'>Tags</h3>
                <div className='flex flex-wrap gap-2'>
                  {order.tags.map((tag, index) => (
                    <Badge key={index} variant='outline'>
                      {tag}
                    </Badge>
                  ))}
                </div>
              </div>
            </>
          )}

          {/* Notes */}
          {order.notes && (
            <>
              <Separator />
              <div>
                <h3 className='font-semibold mb-3'>Notes</h3>
                <div className='text-sm whitespace-pre-wrap bg-muted/50 p-3 rounded-lg'>
                  {order.notes}
                </div>
              </div>
            </>
          )}

          {/* Shipment */}
          <Separator />
          <ShipmentPanel
            shipment={currentOrder.latest_shipment || null}
            orderId={currentOrder.id}
            onCreateShipment={() => setCreateShipmentOpen(true)}
            onShipmentUpdated={refetchOrder}
          />

          {/* Invoices */}
          <Separator />
          <InvoicePanel order={currentOrder} onInvoicesChanged={refetchOrder} />
          </div>
        )}
      </DialogContent>

      <CreateShipmentDialog
        order={currentOrder}
        open={createShipmentOpen}
        onOpenChange={setCreateShipmentOpen}
        onSuccess={refetchOrder}
      />
    </Dialog>
  )
}

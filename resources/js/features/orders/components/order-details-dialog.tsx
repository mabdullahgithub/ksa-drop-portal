import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { type Order } from '@/types/order'
import { format } from 'date-fns'
import { Package, DollarSign, MapPin, Phone, Mail, Calendar, Tag } from 'lucide-react'

interface OrderDetailsDialogProps {
  order: Order | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function OrderDetailsDialog({ order, open, onOpenChange }: OrderDetailsDialogProps) {
  if (!order) return null

  const statusColorMap: Record<string, string> = {
    success: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    warning: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    info: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    error: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    default: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-w-3xl max-h-[90vh] overflow-y-auto'>
        <DialogHeader>
          <DialogTitle className='text-2xl'>Order {order.order_number}</DialogTitle>
        </DialogHeader>

        <div className='space-y-6'>
          {/* Status Section */}
          <div className='flex flex-wrap gap-4'>
            <div>
              <div className='text-sm font-medium text-muted-foreground mb-1'>Fulfillment</div>
              <Badge variant='secondary' className={`capitalize ${statusColorMap[order.status_color] || statusColorMap.default}`}>
                {order.fulfillment_status}
              </Badge>
            </div>
            <div>
              <div className='text-sm font-medium text-muted-foreground mb-1'>Payment</div>
              <Badge variant='secondary' className={`capitalize ${statusColorMap[order.financial_status_color] || statusColorMap.default}`}>
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
        </div>
      </DialogContent>
    </Dialog>
  )
}

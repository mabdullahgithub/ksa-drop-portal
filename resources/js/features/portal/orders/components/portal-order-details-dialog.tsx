import { format } from 'date-fns'
import { Package, DollarSign, MapPin, Phone, Calendar, Tag, Truck, Copy, ExternalLink, FileText, Eye, Download, Receipt } from 'lucide-react'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Skeleton } from '@/components/ui/skeleton'
import { usePortalOrder } from '@/hooks/usePortal'
import { toast } from 'sonner'

const statusColorMap: Record<string, string> = {
  success: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
  warning: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
  info: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
  error: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
  default: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
}

const shipmentColorMap: Record<string, string> = {
  gray: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
  blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
  indigo: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400',
  orange: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
  red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
  green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
  yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
}

interface Props {
  order: any | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function PortalOrderDetailsDialog({ order, open, onOpenChange }: Props) {
  const { order: fullOrder, loading } = usePortalOrder(open && order ? order.id : null)
  const o = fullOrder || order
  if (!o) return null

  const copyTracking = (trackingNumber: string) => {
    navigator.clipboard.writeText(trackingNumber)
    toast.success('Tracking number copied!')
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-w-3xl max-h-[90vh] overflow-y-auto'>
        <DialogHeader>
          <DialogTitle className='text-2xl'>Order {o.order_number}</DialogTitle>
        </DialogHeader>

        {loading && !fullOrder ? (
          <div className='space-y-4'>
            {Array.from({ length: 6 }).map((_, i) => (
              <Skeleton key={i} className='h-8 w-full rounded' />
            ))}
          </div>
        ) : (
          <div className='space-y-6'>
            {/* Status */}
            <div className='flex flex-wrap gap-4'>
              <div>
                <div className='text-sm font-medium text-muted-foreground mb-1'>Fulfillment</div>
                <Badge variant='outline' className={`text-xs capitalize ${statusColorMap[o.status_color] || statusColorMap.default}`}>
                  {o.fulfillment_status}
                </Badge>
              </div>
              <div>
                <div className='text-sm font-medium text-muted-foreground mb-1'>Payment</div>
                <Badge variant='outline' className={`capitalize ${statusColorMap[o.financial_status_color] || statusColorMap.default}`}>
                  {o.financial_status}
                </Badge>
              </div>
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
                  <div className='font-medium'>{o.customer_name || 'N/A'}</div>
                </div>
                <div>
                  <div className='text-muted-foreground'>Phone</div>
                  <div className='font-medium' dir='ltr'>{o.customer_phone || 'N/A'}</div>
                </div>
                {o.customer_email && (
                  <div>
                    <div className='text-muted-foreground'>Email</div>
                    <div className='font-medium'>{o.customer_email}</div>
                  </div>
                )}
              </div>
            </div>

            {/* Shipping Address */}
            {o.shipping_name && (
              <>
                <Separator />
                <div>
                  <h3 className='font-semibold mb-3 flex items-center gap-2'>
                    <MapPin className='h-4 w-4' />
                    Shipping Address
                  </h3>
                  <div className='text-sm space-y-1'>
                    <div>{o.shipping_name}</div>
                    {o.shipping_address1 && <div>{o.shipping_address1}</div>}
                    {o.shipping_address2 && <div>{o.shipping_address2}</div>}
                    <div>
                      {o.shipping_city && `${o.shipping_city}, `}
                      {o.shipping_country}
                    </div>
                    {o.shipping_phone && <div dir='ltr'>{o.shipping_phone}</div>}
                  </div>
                </div>
              </>
            )}

            {/* Order Items */}
            {o.items && o.items.length > 0 && (
              <>
                <Separator />
                <div>
                  <h3 className='font-semibold mb-3 flex items-center gap-2'>
                    <Package className='h-4 w-4' />
                    Order Items
                  </h3>
                  <div className='space-y-3'>
                    {o.items.map((item: any) => (
                      <div key={item.id} className='p-3 bg-muted/50 rounded-lg'>
                        <div className='flex justify-between items-start gap-3'>
                          <div className='flex-1 min-w-0'>
                            <div className='font-medium'>{item.lineitem_name}</div>
                            {item.variant_name && (
                              <div className='text-xs text-muted-foreground'>Variant: {item.variant_name}</div>
                            )}
                            <div className='text-sm text-muted-foreground mt-0.5'>
                              {item.lineitem_quantity} × {o.currency} {item.formatted_price}
                            </div>
                            {item.lineitem_sku && (
                              <div className='text-xs text-muted-foreground font-mono mt-0.5'>SKU: {item.lineitem_sku}</div>
                            )}
                          </div>
                          <div className='text-right shrink-0'>
                            <div className='font-semibold'>{o.currency} {Number(item.total_price).toFixed(2)}</div>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </>
            )}

            {/* Pricing Summary */}
            <Separator />
            <div>
              <h3 className='font-semibold mb-3 flex items-center gap-2'>
                <DollarSign className='h-4 w-4' />
                Order Summary
              </h3>
              <div className='space-y-2 text-sm'>
                <div className='flex justify-between'>
                  <span className='text-muted-foreground'>Subtotal</span>
                  <span>{o.currency} {o.subtotal}</span>
                </div>
                {parseFloat(o.shipping_cost) > 0 && (
                  <div className='flex justify-between'>
                    <span className='text-muted-foreground'>Shipping</span>
                    <span>{o.currency} {o.shipping_cost}</span>
                  </div>
                )}
                {parseFloat(o.taxes) > 0 && (
                  <div className='flex justify-between'>
                    <span className='text-muted-foreground'>Taxes</span>
                    <span>{o.currency} {o.taxes}</span>
                  </div>
                )}
                {parseFloat(o.discount_amount) > 0 && (
                  <div className='flex justify-between text-green-600'>
                    <span>Discount</span>
                    <span>-{o.currency} {o.discount_amount}</span>
                  </div>
                )}
                <Separator />
                <div className='flex justify-between font-semibold text-base'>
                  <span>Total</span>
                  <span>{o.formatted_total || `${o.currency} ${o.total}`}</span>
                </div>
                {o.payment_method && (
                  <div className='flex justify-between text-muted-foreground'>
                    <span>Payment Method</span>
                    <span>{o.payment_method}</span>
                  </div>
                )}
              </div>
            </div>

            {/* Timestamps */}
            <Separator />
            <div>
              <h3 className='font-semibold mb-3 flex items-center gap-2'>
                <Calendar className='h-4 w-4' />
                Timeline
              </h3>
              <div className='space-y-2 text-sm'>
                <div className='flex justify-between'>
                  <span className='text-muted-foreground'>Created</span>
                  <span>{format(new Date(o.created_at), 'PPpp')}</span>
                </div>
                {o.paid_at && (
                  <div className='flex justify-between'>
                    <span className='text-muted-foreground'>Paid</span>
                    <span>{format(new Date(o.paid_at), 'PPpp')}</span>
                  </div>
                )}
                {o.fulfilled_at && (
                  <div className='flex justify-between'>
                    <span className='text-muted-foreground'>Fulfilled</span>
                    <span>{format(new Date(o.fulfilled_at), 'PPpp')}</span>
                  </div>
                )}
                {o.cancelled_at && (
                  <div className='flex justify-between'>
                    <span className='text-muted-foreground'>Cancelled</span>
                    <span>{format(new Date(o.cancelled_at), 'PPpp')}</span>
                  </div>
                )}
              </div>
            </div>

            {/* Tags */}
            {o.tags && o.tags.length > 0 && (
              <>
                <Separator />
                <div>
                  <h3 className='font-semibold mb-3 flex items-center gap-2'>
                    <Tag className='h-4 w-4' />
                    Tags
                  </h3>
                  <div className='flex flex-wrap gap-2'>
                    {o.tags.map((tag: string, index: number) => (
                      <Badge key={index} variant='outline'>{tag}</Badge>
                    ))}
                  </div>
                </div>
              </>
            )}

            {/* Notes */}
            {o.notes && (
              <>
                <Separator />
                <div>
                  <h3 className='font-semibold mb-3'>Notes</h3>
                  <div className='text-sm whitespace-pre-wrap bg-muted/50 p-3 rounded-lg'>{o.notes}</div>
                </div>
              </>
            )}

            {/* Shipment (read-only) */}
            <Separator />
            <div className='space-y-4'>
              <div className='flex items-center gap-2'>
                <Truck className='h-4 w-4' />
                <span className='font-semibold'>Shipment</span>
              </div>
              {o.latest_shipment ? (
                <>
                  <div className='flex items-center gap-2'>
                    <Badge variant='outline' className={shipmentColorMap[o.latest_shipment.status_color] || shipmentColorMap.gray}>
                      {o.latest_shipment.status_label}
                    </Badge>
                  </div>
                  <div className='grid grid-cols-2 gap-3 text-sm'>
                    {o.latest_shipment.tracking_number && (
                      <div>
                        <div className='text-muted-foreground'>Tracking Number</div>
                        <div className='font-medium flex items-center gap-1'>
                          <span className='font-mono'>{o.latest_shipment.tracking_number}</span>
                          <button
                            onClick={() => copyTracking(o.latest_shipment.tracking_number)}
                            className='text-muted-foreground hover:text-foreground'
                          >
                            <Copy className='h-3 w-3' />
                          </button>
                          <a
                            href={`/track?q=${encodeURIComponent(o.latest_shipment.tracking_number)}`}
                            target='_blank'
                            rel='noopener noreferrer'
                            className='text-muted-foreground hover:text-foreground'
                          >
                            <ExternalLink className='h-3 w-3' />
                          </a>
                        </div>
                      </div>
                    )}
                  </div>
                </>
              ) : (
                <p className='text-sm text-muted-foreground'>No shipment created yet</p>
              )}
            </div>

            {/* Documents */}
            {o.invoices && o.invoices.length > 0 && (
              <>
                <Separator />
                <div className='space-y-3'>
                  <div className='flex items-center gap-2'>
                    <Receipt className='h-4 w-4' />
                    <span className='font-semibold'>Documents</span>
                  </div>
                  {o.invoices.map((invoice: any) => (
                    <div key={invoice.id} className='flex items-center justify-between rounded-lg border p-3 text-sm'>
                      <div className='flex items-center gap-2'>
                        <FileText className='h-4 w-4 text-muted-foreground' />
                        <div>
                          <div className='font-medium capitalize'>{invoice.type} Waybill</div>
                          <div className='text-xs text-muted-foreground font-mono'>{invoice.invoice_number}</div>
                        </div>
                      </div>
                      <div className='flex gap-2'>
                        <Button
                          size='sm'
                          variant='outline'
                          onClick={() => window.open(`/portal/api/invoices/${invoice.id}/preview`, '_blank')}
                        >
                          <Eye className='h-3 w-3 mr-1' />
                          View
                        </Button>
                        <Button
                          size='sm'
                          variant='outline'
                          onClick={() => window.open(`/portal/api/invoices/${invoice.id}/download`, '_blank')}
                        >
                          <Download className='h-3 w-3 mr-1' />
                          PDF
                        </Button>
                      </div>
                    </div>
                  ))}
                </div>
              </>
            )}
          </div>
        )}
      </DialogContent>
    </Dialog>
  )
}

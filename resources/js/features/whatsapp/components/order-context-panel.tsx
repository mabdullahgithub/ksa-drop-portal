import { Link } from '@inertiajs/react'
import { format } from 'date-fns'
import { ExternalLink, MapPin, Package, Phone, Wallet, Truck } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Separator } from '@/components/ui/separator'
import { callStatusClass, callStatusLabel } from '@/features/orders/data/call-status'
import type { ConversationOrder } from '../types'

function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className='flex items-baseline justify-between gap-3 text-sm'>
      <span className='shrink-0 text-muted-foreground'>{label}</span>
      <span className='text-end font-medium'>{value || '—'}</span>
    </div>
  )
}

/**
 * Everything an agent needs to judge a reply without leaving the inbox, plus a
 * deep link into the order itself for anything they need to change — the inbox
 * is deliberately read-only about order data, so there is exactly one place
 * orders get edited.
 */
export function OrderContextPanel({ order }: { order: ConversationOrder }) {
  const address = [
    order.shipping_address1,
    order.shipping_address2,
    order.shipping_city,
    order.shipping_province,
    order.shipping_country,
  ]
    .filter(Boolean)
    .join(', ')

  return (
    <ScrollArea className='h-full'>
      <div className='space-y-5 p-4'>
        <div>
          <div className='flex items-center justify-between gap-2'>
            <h3 className='font-semibold'>Order {order.order_number}</h3>
          </div>
          <p className='mt-0.5 text-xs text-muted-foreground'>
            Placed {format(new Date(order.created_at), 'd MMM yyyy, HH:mm')}
          </p>
        </div>

        <Button asChild size='sm' className='w-full'>
          {/* Deep link — the Orders page reads ?order= and opens this order's
              details dialog straight away. */}
          <Link href={`/orders?order=${encodeURIComponent(order.order_number)}`}>
            <ExternalLink className='me-2 h-3.5 w-3.5' />
            View full order details
          </Link>
        </Button>

        <div className='flex flex-wrap gap-1.5'>
          <Badge variant='outline' className={`text-xs ${callStatusClass(order.call_status)}`}>
            Call: {callStatusLabel(order.call_status)}
          </Badge>
          <Badge variant='outline' className='text-xs capitalize'>
            {order.fulfillment_status}
          </Badge>
          <Badge variant='outline' className='text-xs capitalize'>
            {order.financial_status}
          </Badge>
          {order.is_cod && (
            <Badge variant='outline' className='text-xs text-amber-700 dark:text-amber-400'>
              COD
            </Badge>
          )}
        </div>

        <Separator />

        <div className='space-y-2'>
          <h4 className='flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground'>
            <Phone className='h-3.5 w-3.5' /> Customer
          </h4>
          <Row label='Name' value={order.customer_name} />
          <Row label='Phone' value={<span dir='ltr'>{order.customer_phone}</span>} />
          {order.customer_email && <Row label='Email' value={order.customer_email} />}
          {order.client && <Row label='Store' value={order.client.company_name} />}
        </div>

        <Separator />

        <div className='space-y-2'>
          <h4 className='flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground'>
            <MapPin className='h-3.5 w-3.5' /> Delivery address
          </h4>
          <p className='text-sm leading-relaxed'>{address || '—'}</p>
        </div>

        <Separator />

        <div className='space-y-2'>
          <h4 className='flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground'>
            <Package className='h-3.5 w-3.5' /> Items
          </h4>
          {order.items.length === 0 ? (
            <p className='text-sm text-muted-foreground'>—</p>
          ) : (
            <div className='space-y-1.5'>
              {order.items.map((item) => (
                <div key={item.id} className='flex items-baseline justify-between gap-3 text-sm'>
                  <span className='min-w-0 flex-1 truncate' title={item.name}>
                    {item.name}
                  </span>
                  <span className='shrink-0 text-muted-foreground'>×{item.quantity}</span>
                </div>
              ))}
            </div>
          )}
        </div>

        <Separator />

        <div className='space-y-2'>
          <h4 className='flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground'>
            <Wallet className='h-3.5 w-3.5' /> Payment
          </h4>
          <Row label='Method' value={order.payment_method} />
          <Row
            label='Total'
            value={`${order.currency} ${Number(order.total).toFixed(2)}`}
          />
        </div>

        {order.tracking_number && (
          <>
            <Separator />
            <div className='space-y-2'>
              <h4 className='flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground'>
                <Truck className='h-3.5 w-3.5' /> Shipment
              </h4>
              <Row label='Tracking' value={<span className='font-mono text-xs'>{order.tracking_number}</span>} />
            </div>
          </>
        )}

        {(order.call_notes || order.call_attempts > 0) && (
          <>
            <Separator />
            <div className='space-y-2'>
              <h4 className='text-xs font-semibold uppercase tracking-wide text-muted-foreground'>
                Call log
              </h4>
              <Row label='Attempts' value={order.call_attempts} />
              {order.last_called_at && (
                <Row
                  label='Last called'
                  value={format(new Date(order.last_called_at), 'd MMM, HH:mm')}
                />
              )}
              {order.call_notes && (
                <p className='rounded-md bg-muted p-2 text-xs leading-relaxed'>{order.call_notes}</p>
              )}
            </div>
          </>
        )}
      </div>
    </ScrollArea>
  )
}

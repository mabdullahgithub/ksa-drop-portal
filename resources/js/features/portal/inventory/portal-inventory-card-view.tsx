import { CheckCircle, Clock, Hourglass, Lock, Package, PackageX } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { cn } from '@/lib/utils'
import type { PortalInventoryProduct } from '@/types/client'

type Props = {
  products: PortalInventoryProduct[]
  loading: boolean
  /** Shown when there are no products. */
  empty: React.ReactNode
  disabled?: boolean
  onView: (product: PortalInventoryProduct) => void
  onEdit: (product: PortalInventoryProduct) => void
}

const badgeBase = 'text-[9px] py-0 h-4 px-1.5 shadow-sm'

function StatusBadge({ status }: { status: PortalInventoryProduct['verification_status'] }) {
  if (status === 'verified') {
    return (
      <Badge variant='outline' className={cn(badgeBase, 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400')}>
        <CheckCircle className='mr-1 h-2.5 w-2.5' />
        Verified
      </Badge>
    )
  }
  if (status === 'rejected') {
    return (
      <Badge variant='outline' className={cn(badgeBase, 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400')}>
        <Lock className='mr-1 h-2.5 w-2.5' />
        Rejected
      </Badge>
    )
  }
  return (
    <Badge variant='outline' className={cn(badgeBase, 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400')}>
      <Clock className='mr-1 h-2.5 w-2.5' />
      Pending
    </Badge>
  )
}

/** The client's inventory as a grid of cards, matching the admin card view. */
export function PortalInventoryCardView({ products, loading, empty, disabled, onView, onEdit }: Props) {
  if (loading) {
    return (
      <div className='grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5'>
        {Array.from({ length: 10 }).map((_, i) => (
          <Card key={i} className='overflow-hidden border-muted/50 py-0'>
            <div className='aspect-square animate-pulse bg-muted' />
            <CardContent className='space-y-2 p-3'>
              <div className='h-3 w-3/4 animate-pulse rounded bg-muted' />
              <div className='h-3 w-1/2 animate-pulse rounded bg-muted' />
            </CardContent>
          </Card>
        ))}
      </div>
    )
  }

  if (products.length === 0) {
    return <div className='rounded-md border py-10'>{empty}</div>
  }

  return (
    <div className='grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5'>
      {products.map((product) => {
        const stockRequest = product.pending_requests?.find((request) => request.type === 'stock')
        const stockChange = stockRequest
          ? (stockRequest.requested_quantity ?? 0) - (stockRequest.stock_at_request ?? 0)
          : 0

        return (
          <Card
            key={product.id}
            className='group cursor-pointer gap-0 overflow-hidden border-muted/50 py-0 transition-all duration-200 hover:border-muted hover:shadow-sm'
            onClick={() => onView(product)}
          >
            <div className='relative aspect-square overflow-hidden bg-muted/20'>
              {product.images.length > 0 ? (
                <img
                  src={product.images[0].url}
                  alt={product.name}
                  className='h-full w-full object-cover transition-transform duration-300 group-hover:scale-105'
                />
              ) : (
                <div className='flex h-full items-center justify-center'>
                  <Package className='h-10 w-10 text-muted-foreground/20' />
                </div>
              )}
              <div className='absolute top-2 left-2 flex flex-wrap gap-1'>
                <StatusBadge status={product.verification_status} />
                {product.is_out_of_stock && (
                  <Badge variant='outline' className={cn(badgeBase, 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400')}>
                    <PackageX className='mr-1 h-2.5 w-2.5' />
                    Out of Stock
                  </Badge>
                )}
              </div>
            </div>

            <CardContent className='space-y-2 p-3'>
              <div className='min-w-0'>
                <p className='line-clamp-2 text-xs leading-tight font-semibold'>{product.name}</p>
                <p className='mt-0.5 truncate font-mono text-[10px] text-muted-foreground'>
                  {product.product_code}
                  {product.sku ? ` · ${product.sku}` : ''}
                </p>
              </div>

              <div className='flex items-center justify-between gap-1'>
                <span className='text-xs font-bold'>
                  {product.unit_price ? `SAR ${parseFloat(product.unit_price).toFixed(2)}` : '—'}
                </span>
                <span className='text-[10px] text-muted-foreground tabular-nums'>
                  {product.quantity} in stock
                </span>
              </div>

              {stockRequest && (
                <p className='flex items-center text-[10px] text-blue-700 dark:text-blue-400'>
                  <Hourglass className='mr-1 h-2.5 w-2.5' />
                  Stock {stockChange > 0 ? '+' : '−'}{Math.abs(stockChange)} pending
                </p>
              )}

              <div className='flex gap-2 pt-1' onClick={(e) => e.stopPropagation()}>
                <Button
                  variant='outline'
                  size='sm'
                  className='h-7 flex-1 px-2.5 text-xs font-normal'
                  onClick={() => onView(product)}
                >
                  View
                </Button>
                <Button
                  variant='outline'
                  size='sm'
                  className='h-7 flex-1 px-2.5 text-xs font-normal'
                  onClick={() => onEdit(product)}
                  disabled={disabled}
                >
                  Edit
                </Button>
              </div>
            </CardContent>
          </Card>
        )
      })}
    </div>
  )
}

import { useEffect, useState } from 'react'
import { CheckCircle, Clock, Hourglass, ImageOff, Lock, PackageX, Pencil, Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { cn } from '@/lib/utils'
import type { PortalInventoryProduct } from '@/types/client'

type Props = {
  product: PortalInventoryProduct | null
  onOpenChange: (open: boolean) => void
  onEdit: (product: PortalInventoryProduct) => void
  /** Only a product not yet verified is the client's to remove. */
  onDelete: (product: PortalInventoryProduct) => void
}

/** Read-only look at one of the client's own inventory products. */
export function PortalProductViewDialog({ product, onOpenChange, onEdit, onDelete }: Props) {
  const [activeImage, setActiveImage] = useState(0)

  useEffect(() => setActiveImage(0), [product?.id])

  if (!product) return null

  const stockRequest = product.pending_requests?.find((request) => request.type === 'stock')
  const stockChange = stockRequest
    ? (stockRequest.requested_quantity ?? 0) - (stockRequest.stock_at_request ?? 0)
    : 0
  const hasDetailsRequest = product.pending_requests?.some((request) => request.type !== 'stock')

  return (
    <Dialog open={!!product} onOpenChange={onOpenChange}>
      <DialogContent className='max-h-[90vh] overflow-y-auto sm:max-w-2xl'>
        <DialogHeader>
          <DialogTitle className='pr-6'>{product.name}</DialogTitle>
          <DialogDescription className='flex flex-wrap items-center gap-2'>
            <code className='rounded bg-muted px-1.5 py-0.5 text-xs font-semibold text-foreground'>
              {product.product_code}
            </code>
            {product.sku && <span>SKU: {product.sku}</span>}
          </DialogDescription>
        </DialogHeader>

        <div className='grid gap-6 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]'>
          <div className='space-y-2'>
            <div className='flex aspect-square items-center justify-center overflow-hidden rounded-lg border bg-muted/30'>
              {product.images.length > 0 ? (
                <img
                  src={product.images[activeImage]?.url}
                  alt={product.images[activeImage]?.alt_text ?? product.name}
                  className='h-full w-full object-contain'
                />
              ) : (
                <div className='flex flex-col items-center gap-2 text-sm text-muted-foreground'>
                  <ImageOff className='h-8 w-8 opacity-50' />
                  No images uploaded
                </div>
              )}
            </div>
            {product.images.length > 1 && (
              <div className='flex flex-wrap gap-2'>
                {product.images.map((image, index) => (
                  <button
                    key={image.id}
                    type='button'
                    onClick={() => setActiveImage(index)}
                    className={cn(
                      'h-14 w-14 overflow-hidden rounded-md border',
                      index === activeImage ? 'ring-2 ring-primary' : 'opacity-70 hover:opacity-100'
                    )}
                  >
                    <img src={image.url} alt='' className='h-full w-full object-cover' />
                  </button>
                ))}
              </div>
            )}
          </div>

          <div className='space-y-4 text-sm'>
            <div className='flex flex-wrap gap-1'>
              {product.verification_status === 'verified' ? (
                <Badge variant='outline' className='bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'>
                  <CheckCircle className='mr-1 h-3 w-3' />
                  Verified
                </Badge>
              ) : product.verification_status === 'rejected' ? (
                <Badge variant='outline' className='bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'>
                  <Lock className='mr-1 h-3 w-3' />
                  Rejected
                </Badge>
              ) : (
                <Badge variant='outline' className='bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'>
                  <Clock className='mr-1 h-3 w-3' />
                  Pending verification
                </Badge>
              )}
              {product.is_out_of_stock && (
                <Badge variant='outline' className='bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'>
                  <PackageX className='mr-1 h-3 w-3' />
                  Out of Stock
                </Badge>
              )}
            </div>

            {product.verification_status === 'rejected' && product.rejection_reason && (
              <div className='rounded-md border border-red-200 bg-red-50 p-3 text-red-800 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300'>
                <p className='font-medium'>Reason for rejection</p>
                <p className='mt-1'>{product.rejection_reason}</p>
              </div>
            )}

            <dl className='grid grid-cols-2 gap-3'>
              <div>
                <dt className='text-muted-foreground'>Quantity</dt>
                <dd className='text-base font-semibold'>{product.quantity}</dd>
              </div>
              <div>
                <dt className='text-muted-foreground'>Unit Price</dt>
                <dd className='text-base font-semibold'>
                  {product.unit_price ? `SAR ${parseFloat(product.unit_price).toFixed(2)}` : '—'}
                </dd>
              </div>
              <div className='col-span-2'>
                <dt className='text-muted-foreground'>Added</dt>
                <dd>{new Date(product.created_at).toLocaleDateString()}</dd>
              </div>
            </dl>

            {(stockRequest || hasDetailsRequest) && (
              <div className='space-y-1 rounded-md border border-blue-200 bg-blue-50 p-3 text-blue-800 dark:border-blue-900/50 dark:bg-blue-900/20 dark:text-blue-300'>
                <p className='flex items-center font-medium'>
                  <Hourglass className='mr-1.5 h-3.5 w-3.5' />
                  Waiting for our team to approve
                </p>
                {stockRequest && (
                  <p>
                    Stock {stockChange > 0 ? '+' : '−'}{Math.abs(stockChange)} (to {stockRequest.requested_quantity})
                  </p>
                )}
                {hasDetailsRequest && <p>Changes to the product details</p>}
              </div>
            )}

            {product.description && (
              <div>
                <p className='text-muted-foreground'>Description</p>
                <p className='mt-1 whitespace-pre-line'>{product.description}</p>
              </div>
            )}

            {product.notes && (
              <div>
                <p className='text-muted-foreground'>Notes</p>
                <p className='mt-1 whitespace-pre-line'>{product.notes}</p>
              </div>
            )}
          </div>
        </div>

        <DialogFooter className='gap-2 sm:gap-2'>
          {product.verification_status !== 'verified' && (
            <Button
              variant='ghost'
              className='text-destructive hover:bg-destructive/10 hover:text-destructive sm:mr-auto'
              onClick={() => {
                onOpenChange(false)
                onDelete(product)
              }}
            >
              <Trash2 className='mr-2 h-4 w-4' />
              Delete
            </Button>
          )}
          <Button variant='outline' onClick={() => onOpenChange(false)}>
            Close
          </Button>
          <Button
            onClick={() => {
              onOpenChange(false)
              onEdit(product)
            }}
          >
            <Pencil className='mr-2 h-4 w-4' />
            Edit
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

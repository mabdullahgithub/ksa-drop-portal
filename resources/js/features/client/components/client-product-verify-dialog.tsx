import { useState, useRef, useEffect } from 'react'
import { ImagePlus, X, ShieldCheck, XCircle, Clock, ChevronLeft, ChevronRight, PackageX } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import type { ClientProduct } from '@/types/client'

interface ClientProductReviewDialogProps {
  product: ClientProduct | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onConfirm: (action: 'verify' | 'reject' | 'pending', images: File[], rejectionReason: string, isOutOfStock: boolean) => Promise<void>
  loading: boolean
}

export function ClientProductVerifyDialog({
  product,
  open,
  onOpenChange,
  onConfirm,
  loading,
}: ClientProductReviewDialogProps) {
  const [images, setImages] = useState<File[]>([])
  const [previews, setPreviews] = useState<string[]>([])
  const [rejectionReason, setRejectionReason] = useState('')
  const [isOutOfStock, setIsOutOfStock] = useState(false)
  const [lightboxIndex, setLightboxIndex] = useState<number | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (open && product) {
      setIsOutOfStock(product.is_out_of_stock ?? false)
    }
  }, [open, product])

  const handleOpenChange = (o: boolean) => {
    if (!o) {
      previews.forEach((p) => URL.revokeObjectURL(p))
      setImages([])
      setPreviews([])
      setRejectionReason('')
      setIsOutOfStock(false)
      setLightboxIndex(null)
    }
    onOpenChange(o)
  }

  const handleFiles = (files: FileList | null) => {
    if (!files) return
    const valid = Array.from(files).filter((f) => f.type.startsWith('image/'))
    const combined = [...images, ...valid].slice(0, 10)
    setImages(combined)
    setPreviews(combined.map((f) => URL.createObjectURL(f)))
  }

  const removeImage = (index: number) => {
    URL.revokeObjectURL(previews[index])
    setImages((prev) => prev.filter((_, i) => i !== index))
    setPreviews((prev) => prev.filter((_, i) => i !== index))
  }

  const allImages = [
    ...(product?.images ?? []).map((img) => ({ src: img.url, type: 'existing' as const, id: img.id })),
    ...previews.map((src, i) => ({ src, type: 'new' as const, index: i })),
  ]

  return (
    <>
      <Dialog open={open} onOpenChange={handleOpenChange}>
        <DialogContent className='max-w-2xl p-0 gap-0 flex flex-col max-h-[min(90vh,44rem)]'>
          <DialogHeader className='px-6 pt-5 pb-4 border-b shrink-0'>
            <DialogTitle>Review &amp; Verify Product</DialogTitle>
          </DialogHeader>

          <div className='flex-1 min-h-0 overflow-y-auto'>
            <div className='px-6 py-4 space-y-5'>

              {/* Product info */}
              <div className='rounded-lg border bg-muted/30 p-4 space-y-3'>
                <div className='flex items-start justify-between gap-3'>
                  <div>
                    <p className='font-semibold text-base leading-tight'>{product?.name}</p>
                    <div className='flex items-center gap-2 mt-1'>
                      <code className='rounded bg-muted px-1.5 py-0.5 text-xs font-semibold'>
                        {product?.product_code}
                      </code>
                      {product?.sku && (
                        <span className='text-xs text-muted-foreground'>SKU: {product.sku}</span>
                      )}
                    </div>
                  </div>
                  <div className='flex flex-col items-end gap-1'>
                    {product?.verification_status === 'verified' ? (
                      <Badge variant='outline' className='bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 text-xs shrink-0'>
                        <ShieldCheck className='mr-1 h-3 w-3' />
                        Verified
                      </Badge>
                    ) : product?.verification_status === 'rejected' ? (
                      <Badge variant='outline' className='bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 text-xs shrink-0'>
                        <XCircle className='mr-1 h-3 w-3' />
                        Rejected
                      </Badge>
                    ) : (
                      <Badge variant='outline' className='bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 text-xs shrink-0'>
                        <Clock className='mr-1 h-3 w-3' />
                        Pending
                      </Badge>
                    )}
                    {product?.is_out_of_stock && (
                      <Badge variant='outline' className='bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400 text-xs shrink-0'>
                        <PackageX className='mr-1 h-3 w-3' />
                        Out of Stock
                      </Badge>
                    )}
                  </div>
                </div>

                <div className='grid grid-cols-2 gap-x-6 gap-y-2 text-sm'>
                  <div>
                    <span className='text-muted-foreground'>Quantity</span>
                    <p className='font-medium'>{product?.quantity ?? '—'}</p>
                  </div>
                  <div>
                    <span className='text-muted-foreground'>Unit Price</span>
                    <p className='font-medium'>
                      {product?.unit_price ? `SAR ${parseFloat(product.unit_price).toFixed(2)}` : '—'}
                    </p>
                  </div>
                </div>

                {product?.description && (
                  <div className='text-sm'>
                    <span className='text-muted-foreground'>Description</span>
                    <p className='mt-0.5'>{product.description}</p>
                  </div>
                )}

                {product?.notes && (
                  <div className='text-sm'>
                    <span className='text-muted-foreground'>Notes</span>
                    <p className='mt-0.5'>{product.notes}</p>
                  </div>
                )}

                {product?.rejection_reason && product.verification_status === 'rejected' && (
                  <div className='text-sm rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-3'>
                    <span className='text-red-700 dark:text-red-400 font-medium text-xs uppercase tracking-wide'>Previous rejection reason</span>
                    <p className='mt-0.5 text-red-800 dark:text-red-300'>{product.rejection_reason}</p>
                  </div>
                )}
              </div>

              {/* Existing product images from client */}
              {(product?.images?.length ?? 0) > 0 && (
                <div className='space-y-2'>
                  <Label className='text-sm'>Client-Submitted Images</Label>
                  <div className='flex flex-wrap gap-2'>
                    {product!.images.map((img, i) => (
                      <button
                        key={img.id}
                        type='button'
                        onClick={() => setLightboxIndex(i)}
                        className='h-20 w-20 overflow-hidden rounded-md border hover:opacity-80 transition-opacity focus:outline-none focus:ring-2 focus:ring-ring'
                      >
                        <img src={img.url} alt='' className='h-full w-full object-cover' />
                      </button>
                    ))}
                  </div>
                </div>
              )}

              {/* Admin stock images upload */}
              <div className='space-y-2'>
                <Label>
                  Stock Images
                  <span className='ml-1 text-xs font-normal text-muted-foreground'>(optional — attach on verify, up to 10)</span>
                </Label>

                {previews.length > 0 && (
                  <div className='flex flex-wrap gap-2'>
                    {previews.map((src, i) => (
                      <div key={i} className='relative h-20 w-20 overflow-hidden rounded-md border'>
                        <button
                          type='button'
                          onClick={() => setLightboxIndex((product?.images?.length ?? 0) + i)}
                          className='h-full w-full'
                        >
                          <img src={src} alt='' className='h-full w-full object-cover' />
                        </button>
                        <button
                          type='button'
                          onClick={() => removeImage(i)}
                          className='absolute right-0.5 top-0.5 rounded-full bg-destructive p-0.5 text-destructive-foreground hover:bg-destructive/80'
                        >
                          <X className='h-3 w-3' />
                        </button>
                      </div>
                    ))}
                  </div>
                )}

                {images.length < 10 && (
                  <>
                    <input
                      ref={fileInputRef}
                      type='file'
                      accept='image/*'
                      multiple
                      className='hidden'
                      onChange={(e) => handleFiles(e.target.files)}
                    />
                    <Button
                      type='button'
                      variant='outline'
                      size='sm' className='h-7 px-2.5 text-xs'
                      onClick={() => fileInputRef.current?.click()}
                    >
                      <ImagePlus className='mr-2 h-4 w-4' />
                      {images.length === 0 ? 'Attach Stock Images' : 'Add More'}
                    </Button>
                  </>
                )}
              </div>

              {/* Out of stock */}
              <div className='flex items-center gap-2'>
                <Checkbox
                  id='out-of-stock'
                  checked={isOutOfStock}
                  onCheckedChange={(checked) => setIsOutOfStock(!!checked)}
                  disabled={loading}
                />
                <Label htmlFor='out-of-stock' className='cursor-pointer'>
                  Mark as out of stock
                  <span className='ml-1 text-xs font-normal text-muted-foreground'>(can be combined with any status)</span>
                </Label>
              </div>

              {/* Rejection reason */}
              <div className='space-y-2'>
                <Label htmlFor='rejection-reason'>
                  Rejection Reason
                  <span className='ml-1 text-xs font-normal text-muted-foreground'>(required when rejecting, optional otherwise)</span>
                </Label>
                <Textarea
                  id='rejection-reason'
                  placeholder='Explain why the product is being rejected…'
                  value={rejectionReason}
                  onChange={(e) => setRejectionReason(e.target.value)}
                  rows={3}
                  disabled={loading}
                />
              </div>

            </div>
          </div>

          {/* Action footer */}
          <div className='flex items-center justify-between gap-2 px-6 py-4 border-t bg-background shrink-0'>
            <Button
              variant='outline'
              size='sm'
              className='h-7 px-2.5 text-xs'
              onClick={() => handleOpenChange(false)}
              disabled={loading}
            >
              Cancel
            </Button>
            <div className='flex items-center gap-2'>
              <Button
                variant='outline'
                size='sm'
                className='h-7 px-2.5 text-xs border-yellow-400 text-yellow-700 hover:bg-yellow-50 dark:text-yellow-400 dark:hover:bg-yellow-900/20'
                disabled={loading}
                onClick={() => onConfirm('pending', images, rejectionReason, isOutOfStock)}
              >
                <Clock className='mr-1.5 h-3 w-3' />
                {loading ? 'Saving…' : 'Keep Pending'}
              </Button>
              <Button
                variant='outline'
                size='sm'
                className='h-7 px-2.5 text-xs border-red-400 text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20'
                disabled={loading}
                onClick={() => onConfirm('reject', images, rejectionReason, isOutOfStock)}
              >
                <XCircle className='mr-1.5 h-3 w-3' />
                {loading ? 'Saving…' : 'Reject'}
              </Button>
              <Button
                size='sm'
                className='h-7 px-2.5 text-xs bg-green-600 hover:bg-green-700 text-white'
                disabled={loading}
                onClick={() => onConfirm('verify', images, rejectionReason, isOutOfStock)}
              >
                <ShieldCheck className='mr-1.5 h-3 w-3' />
                {loading ? 'Verifying…' : 'Verify'}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      {/* Lightbox */}
      {lightboxIndex !== null && allImages.length > 0 && (
        <div
          className='fixed inset-0 z-50 flex items-center justify-center bg-black/80'
          onClick={() => setLightboxIndex(null)}
        >
          <button
            className='absolute top-4 right-4 text-white hover:text-white/70 p-2'
            onClick={() => setLightboxIndex(null)}
          >
            <X className='h-6 w-6' />
          </button>
          {allImages.length > 1 && (
            <>
              <button
                className='absolute left-4 text-white hover:text-white/70 p-2'
                onClick={(e) => { e.stopPropagation(); setLightboxIndex((lightboxIndex - 1 + allImages.length) % allImages.length) }}
              >
                <ChevronLeft className='h-8 w-8' />
              </button>
              <button
                className='absolute right-4 text-white hover:text-white/70 p-2'
                onClick={(e) => { e.stopPropagation(); setLightboxIndex((lightboxIndex + 1) % allImages.length) }}
              >
                <ChevronRight className='h-8 w-8' />
              </button>
            </>
          )}
          <img
            src={allImages[lightboxIndex].src}
            alt=''
            className='max-h-[85vh] max-w-[85vw] rounded-lg object-contain'
            onClick={(e) => e.stopPropagation()}
          />
          <p className='absolute bottom-4 text-white/70 text-sm'>
            {lightboxIndex + 1} / {allImages.length}
          </p>
        </div>
      )}
    </>
  )
}

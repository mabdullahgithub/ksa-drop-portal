import { useState, useRef, useEffect } from 'react'
import { ImagePlus, X, ShieldCheck, XCircle, Clock, ChevronLeft, ChevronRight, PackageX } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
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
  /** `receivedQuantity` is the count the warehouse received; null leaves the client's number. */
  onConfirm: (
    action: 'verify' | 'reject' | 'pending',
    images: File[],
    rejectionReason: string,
    isOutOfStock: boolean,
    receivedQuantity: number | null
  ) => Promise<void>
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
  const [receivedQuantity, setReceivedQuantity] = useState('')
  const [lightboxIndex, setLightboxIndex] = useState<number | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (open && product) {
      setIsOutOfStock(product.is_out_of_stock ?? false)
      setReceivedQuantity(String(product.quantity ?? ''))
    }
  }, [open, product])

  // Only a product not yet verified takes a received count: once verified,
  // its stock moves on scans and approved requests, and a number typed here
  // could overwrite them.
  const takesReceivedQuantity = product?.verification_status !== 'verified'
  const received =
    takesReceivedQuantity && receivedQuantity.trim() !== '' && Number.isInteger(Number(receivedQuantity)) && Number(receivedQuantity) >= 0
      ? Number(receivedQuantity)
      : null

  const handleOpenChange = (o: boolean) => {
    if (!o) {
      previews.forEach((p) => URL.revokeObjectURL(p))
      setImages([])
      setPreviews([])
      setRejectionReason('')
      setIsOutOfStock(false)
      setReceivedQuantity('')
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

              {/* Count the warehouse received */}
              {takesReceivedQuantity && (
                <div className='space-y-2'>
                  <Label htmlFor='received-quantity'>
                    Received Quantity
                    <span className='ml-1 text-xs font-normal text-muted-foreground'>
                      (saved on verify — the client stated {product?.quantity ?? 0})
                    </span>
                  </Label>
                  <Input
                    id='received-quantity'
                    type='number'
                    min={0}
                    step={1}
                    className='w-40'
                    value={receivedQuantity}
                    onChange={(e) => setReceivedQuantity(e.target.value)}
                    disabled={loading}
                  />
                </div>
              )}

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
                onClick={() => onConfirm('pending', images, rejectionReason, isOutOfStock, received)}
              >
                <Clock className='mr-1.5 h-3 w-3' />
                {loading ? 'Saving…' : 'Keep Pending'}
              </Button>
              <Button
                variant='outline'
                size='sm'
                className='h-7 px-2.5 text-xs border-red-400 text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20'
                disabled={loading}
                onClick={() => onConfirm('reject', images, rejectionReason, isOutOfStock, received)}
              >
                <XCircle className='mr-1.5 h-3 w-3' />
                {loading ? 'Saving…' : 'Reject'}
              </Button>
              <Button
                size='sm'
                className='h-7 px-2.5 text-xs bg-green-600 hover:bg-green-700 text-white'
                disabled={loading}
                onClick={() => onConfirm('verify', images, rejectionReason, isOutOfStock, received)}
              >
                <ShieldCheck className='mr-1.5 h-3 w-3' />
                {loading ? 'Verifying…' : 'Verify'}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      {/* Lightbox. A dialog of its own, so it opens above the review dialog
          rather than behind it, and Escape or a click outside closes only
          the image. */}
      <Dialog
        open={lightboxIndex !== null && allImages.length > 0}
        onOpenChange={(o) => { if (!o) setLightboxIndex(null) }}
      >
        <DialogContent
          showCloseButton={false}
          overlayClassName='bg-black/80'
          aria-describedby={undefined}
          className='flex w-auto max-w-[96vw] sm:max-w-[96vw] max-h-none items-center gap-2 space-y-0 overflow-visible border-0 bg-transparent p-0 shadow-none'
        >
          <DialogTitle className='sr-only'>Product image</DialogTitle>
          {lightboxIndex !== null && allImages[lightboxIndex] && (
            <>
              {allImages.length > 1 && (
                <button
                  type='button'
                  className='shrink-0 p-2 text-white hover:text-white/70'
                  aria-label='Previous image'
                  onClick={() => setLightboxIndex((lightboxIndex - 1 + allImages.length) % allImages.length)}
                >
                  <ChevronLeft className='h-8 w-8' />
                </button>
              )}
              <div className='relative'>
                <button
                  type='button'
                  className='absolute bottom-full right-0 p-2 text-white hover:text-white/70'
                  aria-label='Close image'
                  onClick={() => setLightboxIndex(null)}
                >
                  <X className='h-6 w-6' />
                </button>
                <img
                  src={allImages[lightboxIndex].src}
                  alt=''
                  className='max-h-[80vh] max-w-[78vw] rounded-lg object-contain'
                />
                <p className='absolute left-1/2 top-full mt-2 -translate-x-1/2 whitespace-nowrap text-sm text-white/70'>
                  {lightboxIndex + 1} / {allImages.length}
                </p>
              </div>
              {allImages.length > 1 && (
                <button
                  type='button'
                  className='shrink-0 p-2 text-white hover:text-white/70'
                  aria-label='Next image'
                  onClick={() => setLightboxIndex((lightboxIndex + 1) % allImages.length)}
                >
                  <ChevronRight className='h-8 w-8' />
                </button>
              )}
            </>
          )}
        </DialogContent>
      </Dialog>
    </>
  )
}

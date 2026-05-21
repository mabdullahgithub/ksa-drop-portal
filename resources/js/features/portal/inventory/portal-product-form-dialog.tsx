import { useState, useEffect, useRef } from 'react'
import { toast } from 'sonner'
import { ImagePlus, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { usePortalInventoryMutations } from '@/hooks/usePortal'
import type { ClientProductImage } from '@/types/client'

interface PortalProduct {
  id: number
  name: string
  sku: string | null
  description: string | null
  quantity: number
  unit_price: string | null
  notes: string | null
  verification_status: 'pending' | 'verified'
  images: ClientProductImage[]
}

interface PortalProductFormDialogProps {
  product: PortalProduct | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onSuccess: () => void
}

const emptyForm = {
  name: '',
  sku: '',
  description: '',
  quantity: '',
  unit_price: '',
  notes: '',
}

export function PortalProductFormDialog({
  product,
  open,
  onOpenChange,
  onSuccess,
}: PortalProductFormDialogProps) {
  const isEdit = product !== null
  const { loading, addProduct, updateProduct } = usePortalInventoryMutations()
  const [form, setForm] = useState(emptyForm)
  const [newImages, setNewImages] = useState<File[]>([])
  const [newPreviews, setNewPreviews] = useState<string[]>([])
  const [removeImageIds, setRemoveImageIds] = useState<number[]>([])
  const fileInputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (open) {
      if (product) {
        setForm({
          name: product.name,
          sku: product.sku ?? '',
          description: product.description ?? '',
          quantity: String(product.quantity),
          unit_price: product.unit_price ?? '',
          notes: product.notes ?? '',
        })
      } else {
        setForm(emptyForm)
      }
      setNewImages([])
      setNewPreviews([])
      setRemoveImageIds([])
    }
  }, [open, product])

  const handleChange = (field: string, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }))
  }

  const handleFiles = (files: FileList | null) => {
    if (!files) return
    const valid = Array.from(files).filter((f) => f.type.startsWith('image/'))
    const combined = [...newImages, ...valid].slice(0, 10)
    setNewImages(combined)
    setNewPreviews(combined.map((f) => URL.createObjectURL(f)))
  }

  const removeNewImage = (index: number) => {
    URL.revokeObjectURL(newPreviews[index])
    setNewImages((prev) => prev.filter((_, i) => i !== index))
    setNewPreviews((prev) => prev.filter((_, i) => i !== index))
  }

  const toggleRemoveExisting = (id: number) => {
    setRemoveImageIds((prev) =>
      prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]
    )
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    const payload: Record<string, unknown> = {
      name: form.name.trim(),
      sku: form.sku.trim() || null,
      description: form.description.trim() || null,
      quantity: parseInt(form.quantity, 10),
      unit_price: form.unit_price !== '' ? parseFloat(form.unit_price) : null,
      notes: form.notes.trim() || null,
    }

    if (isEdit) {
      const result = await updateProduct(product!.id, payload, newImages, removeImageIds)
      if (result === true) {
        toast.success('Product updated successfully')
        onSuccess()
        onOpenChange(false)
      } else if (result && typeof result === 'object' && 'message' in result) {
        toast.error((result as { message: string }).message)
      } else if (result && typeof result === 'object' && 'errors' in result) {
        const firstError = Object.values((result as { errors: Record<string, string[]> }).errors).flat()[0]
        toast.error(firstError || 'Validation failed')
      } else {
        toast.error('Failed to update product')
      }
    } else {
      const result = await addProduct(payload, newImages)
      if (result === true) {
        toast.success('Product added. It will appear as pending until verified by our team.')
        onSuccess()
        onOpenChange(false)
      } else if (result && typeof result === 'object' && 'errors' in result) {
        const firstError = Object.values((result as { errors: Record<string, string[]> }).errors).flat()[0]
        toast.error(firstError || 'Validation failed')
      } else {
        toast.error('Failed to add product')
      }
    }
  }

  const existingImages = (product?.images ?? []).filter((img) => !removeImageIds.includes(img.id))
  const totalImages = existingImages.length + newImages.length

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-w-md max-h-[90vh] overflow-y-auto'>
        <DialogHeader>
          <DialogTitle>{isEdit ? 'Edit Product' : 'Add Product to Inventory'}</DialogTitle>
        </DialogHeader>

        {!isEdit && (
          <p className='text-sm text-muted-foreground -mt-2'>
            Your product will be reviewed and verified by our team before being marked as active.
          </p>
        )}

        <form onSubmit={handleSubmit} className='space-y-4'>
          <div className='space-y-2'>
            <Label htmlFor='portal-product-name'>
              Name <span className='text-destructive'>*</span>
            </Label>
            <Input
              id='portal-product-name'
              value={form.name}
              onChange={(e) => handleChange('name', e.target.value)}
              placeholder='Product name'
              required
            />
          </div>

          <div className='grid grid-cols-2 gap-4'>
            <div className='space-y-2'>
              <Label htmlFor='portal-product-sku'>SKU</Label>
              <Input
                id='portal-product-sku'
                value={form.sku}
                onChange={(e) => handleChange('sku', e.target.value)}
                placeholder='e.g. ABC-001'
              />
            </div>
            <div className='space-y-2'>
              <Label htmlFor='portal-product-quantity'>
                Quantity <span className='text-destructive'>*</span>
              </Label>
              <Input
                id='portal-product-quantity'
                type='number'
                min={0}
                value={form.quantity}
                onChange={(e) => handleChange('quantity', e.target.value)}
                placeholder='0'
                required
              />
            </div>
          </div>

          <div className='space-y-2'>
            <Label htmlFor='portal-product-price'>Unit Price (SAR)</Label>
            <Input
              id='portal-product-price'
              type='number'
              min={0}
              step='0.01'
              value={form.unit_price}
              onChange={(e) => handleChange('unit_price', e.target.value)}
              placeholder='0.00'
            />
          </div>

          <div className='space-y-2'>
            <Label htmlFor='portal-product-description'>Description</Label>
            <Textarea
              id='portal-product-description'
              value={form.description}
              onChange={(e) => handleChange('description', e.target.value)}
              placeholder='Optional product description'
              rows={2}
            />
          </div>

          <div className='space-y-2'>
            <Label htmlFor='portal-product-notes'>Notes</Label>
            <Textarea
              id='portal-product-notes'
              value={form.notes}
              onChange={(e) => handleChange('notes', e.target.value)}
              placeholder='Any additional notes for the warehouse team'
              rows={2}
            />
          </div>

          {/* Images */}
          <div className='space-y-2'>
            <Label>
              Product Images
              <span className='ml-1 text-xs font-normal text-muted-foreground'>(up to 10)</span>
            </Label>

            {(existingImages.length > 0 || newImages.length > 0) && (
              <div className='flex flex-wrap gap-2'>
                {existingImages.map((img) => (
                  <div key={img.id} className='relative h-20 w-20 overflow-hidden rounded-md border'>
                    <img src={img.url} alt={img.alt_text ?? ''} className='h-full w-full object-cover' />
                    <button
                      type='button'
                      onClick={() => toggleRemoveExisting(img.id)}
                      className='absolute right-0.5 top-0.5 rounded-full bg-destructive p-0.5 text-destructive-foreground hover:bg-destructive/80'
                    >
                      <X className='h-3 w-3' />
                    </button>
                  </div>
                ))}
                {newPreviews.map((src, i) => (
                  <div key={i} className='relative h-20 w-20 overflow-hidden rounded-md border'>
                    <img src={src} alt='' className='h-full w-full object-cover' />
                    <button
                      type='button'
                      onClick={() => removeNewImage(i)}
                      className='absolute right-0.5 top-0.5 rounded-full bg-destructive p-0.5 text-destructive-foreground hover:bg-destructive/80'
                    >
                      <X className='h-3 w-3' />
                    </button>
                  </div>
                ))}
              </div>
            )}

            {totalImages < 10 && (
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
                  size='sm'
                  onClick={() => fileInputRef.current?.click()}
                >
                  <ImagePlus className='mr-2 h-4 w-4' />
                  {totalImages === 0 ? 'Add Images' : 'Add More'}
                </Button>
              </>
            )}
          </div>

          <DialogFooter>
            <Button type='button' variant='outline' onClick={() => onOpenChange(false)} disabled={loading}>
              Cancel
            </Button>
            <Button type='submit' disabled={loading}>
              {loading
                ? isEdit ? 'Saving...' : 'Submitting...'
                : isEdit ? 'Save Changes' : 'Submit for Verification'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

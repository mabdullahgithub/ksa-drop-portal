import { useState, useEffect } from 'react'
import { toast } from 'sonner'
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

interface PortalProduct {
  id: number
  name: string
  sku: string | null
  description: string | null
  quantity: number
  unit_price: string | null
  notes: string | null
  verification_status: 'pending' | 'verified'
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
    }
  }, [open, product])

  const handleChange = (field: string, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }))
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
      const result = await updateProduct(product!.id, payload)
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
      const result = await addProduct(payload)
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

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-w-md'>
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

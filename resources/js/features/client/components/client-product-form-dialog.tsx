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
import { useClientProductMutations } from '@/hooks/useClientProducts'
import type { ClientProduct } from '@/types/client'

interface ClientProductFormDialogProps {
  clientId: number
  product: ClientProduct | null
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

export function ClientProductFormDialog({
  clientId,
  product,
  open,
  onOpenChange,
  onSuccess,
}: ClientProductFormDialogProps) {
  const isEdit = product !== null
  const { loading, addProduct, updateProduct } = useClientProductMutations()
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

    const result = isEdit
      ? await updateProduct(clientId, product!.id, payload)
      : await addProduct(clientId, payload)

    if (result === true) {
      toast.success(isEdit ? 'Product updated successfully' : 'Product added successfully')
      onSuccess()
      onOpenChange(false)
    } else if (result && typeof result === 'object' && 'errors' in result) {
      const firstError = Object.values(result.errors).flat()[0]
      toast.error(firstError || 'Validation failed')
    } else {
      toast.error(isEdit ? 'Failed to update product' : 'Failed to add product')
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-w-md'>
        <DialogHeader>
          <DialogTitle>{isEdit ? 'Edit Product' : 'Add Product'}</DialogTitle>
        </DialogHeader>

        <form onSubmit={handleSubmit} className='space-y-4'>
          <div className='space-y-2'>
            <Label htmlFor='product-name'>
              Name <span className='text-destructive'>*</span>
            </Label>
            <Input
              id='product-name'
              value={form.name}
              onChange={(e) => handleChange('name', e.target.value)}
              placeholder='Product name'
              required
            />
          </div>

          <div className='grid grid-cols-2 gap-4'>
            <div className='space-y-2'>
              <Label htmlFor='product-sku'>SKU</Label>
              <Input
                id='product-sku'
                value={form.sku}
                onChange={(e) => handleChange('sku', e.target.value)}
                placeholder='e.g. ABC-001'
              />
            </div>
            <div className='space-y-2'>
              <Label htmlFor='product-quantity'>
                Quantity <span className='text-destructive'>*</span>
              </Label>
              <Input
                id='product-quantity'
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
            <Label htmlFor='product-price'>Unit Price (SAR)</Label>
            <Input
              id='product-price'
              type='number'
              min={0}
              step='0.01'
              value={form.unit_price}
              onChange={(e) => handleChange('unit_price', e.target.value)}
              placeholder='0.00'
            />
          </div>

          <div className='space-y-2'>
            <Label htmlFor='product-description'>Description</Label>
            <Textarea
              id='product-description'
              value={form.description}
              onChange={(e) => handleChange('description', e.target.value)}
              placeholder='Optional product description'
              rows={2}
            />
          </div>

          <div className='space-y-2'>
            <Label htmlFor='product-notes'>Notes</Label>
            <Textarea
              id='product-notes'
              value={form.notes}
              onChange={(e) => handleChange('notes', e.target.value)}
              placeholder='Internal notes'
              rows={2}
            />
          </div>

          <DialogFooter>
            <Button type='button' variant='outline' onClick={() => onOpenChange(false)} disabled={loading}>
              Cancel
            </Button>
            <Button type='submit' disabled={loading}>
              {loading ? (isEdit ? 'Saving...' : 'Adding...') : (isEdit ? 'Save Changes' : 'Add Product')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

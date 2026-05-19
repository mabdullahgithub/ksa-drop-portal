import { useState } from 'react'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { type Product } from '@/types/product'
import { useProductMutations } from '@/hooks/useProducts'
import { toast } from 'sonner'

interface EditProductDialogProps {
  product: Product
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function EditProductDialog({ product, open, onOpenChange }: EditProductDialogProps) {
  const { updateProduct, loading } = useProductMutations()

  const [title, setTitle] = useState(product.title)
  const [status, setStatus] = useState<'active' | 'draft' | 'archived'>(product.status)
  const [price, setPrice] = useState(product.variant_price || '')
  const [comparePrice, setComparePrice] = useState(product.variant_compare_at_price || '')
  const [qty, setQty] = useState(String(product.variant_inventory_qty))
  const [tags, setTags] = useState(product.tags?.join(', ') || '')
  const [published, setPublished] = useState(product.published)

  const handleSave = async () => {
    const success = await updateProduct(product.id, {
      title: title.trim() || product.title,
      status,
      variant_price: price || undefined,
      variant_compare_at_price: comparePrice || undefined,
      variant_inventory_qty: parseInt(qty) || 0,
      tags: tags ? tags.split(',').map((t) => t.trim()).filter(Boolean) : [],
      published,
    })

    if (success) {
      toast.success('Product updated')
      onOpenChange(false)
      window.location.reload()
    } else {
      toast.error('Failed to update product')
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='sm:max-w-[480px]'>
        <DialogHeader>
          <DialogTitle>Edit Product</DialogTitle>
          <DialogDescription className='truncate text-xs'>{product.title}</DialogDescription>
        </DialogHeader>

        <div className='grid gap-4 py-2'>
          <div className='grid gap-1.5'>
            <Label htmlFor='ep-title'>Title</Label>
            <Input
              id='ep-title'
              value={title}
              onChange={(e) => setTitle(e.target.value)}
            />
          </div>

          <div className='grid grid-cols-2 gap-3'>
            <div className='grid gap-1.5'>
              <Label htmlFor='ep-status'>Status</Label>
              <Select value={status} onValueChange={(v) => setStatus(v as typeof status)}>
                <SelectTrigger id='ep-status'>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value='active'>Active</SelectItem>
                  <SelectItem value='draft'>Draft</SelectItem>
                  <SelectItem value='archived'>Archived</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className='grid gap-1.5'>
              <Label htmlFor='ep-qty'>Stock Qty</Label>
              <Input
                id='ep-qty'
                type='number'
                min='0'
                value={qty}
                onChange={(e) => setQty(e.target.value)}
              />
            </div>
          </div>

          <div className='grid grid-cols-2 gap-3'>
            <div className='grid gap-1.5'>
              <Label htmlFor='ep-price'>Price (SAR)</Label>
              <Input
                id='ep-price'
                type='number'
                min='0'
                step='0.01'
                placeholder='0.00'
                value={price}
                onChange={(e) => setPrice(e.target.value)}
              />
            </div>

            <div className='grid gap-1.5'>
              <Label htmlFor='ep-compare'>Compare At (SAR)</Label>
              <Input
                id='ep-compare'
                type='number'
                min='0'
                step='0.01'
                placeholder='0.00'
                value={comparePrice}
                onChange={(e) => setComparePrice(e.target.value)}
              />
            </div>
          </div>

          <div className='grid gap-1.5'>
            <Label htmlFor='ep-tags'>Tags (comma-separated)</Label>
            <Input
              id='ep-tags'
              placeholder='sale, featured, new'
              value={tags}
              onChange={(e) => setTags(e.target.value)}
            />
          </div>

          <div className='flex items-center gap-2'>
            <Switch id='ep-published' checked={published} onCheckedChange={setPublished} />
            <Label htmlFor='ep-published' className='cursor-pointer'>Published</Label>
          </div>
        </div>

        <DialogFooter>
          <Button variant='outline' onClick={() => onOpenChange(false)} disabled={loading}>
            Cancel
          </Button>
          <Button onClick={handleSave} disabled={loading}>
            {loading ? 'Saving…' : 'Save Changes'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

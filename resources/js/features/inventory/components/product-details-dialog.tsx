import { useState } from 'react'
import { format } from 'date-fns'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Button } from '@/components/ui/button'
import { ChevronLeft, ChevronRight, Package, Tag, Globe, BarChart3, Info } from 'lucide-react'
import { type Product } from '@/types/product'
import { useProduct } from '@/hooks/useProducts'

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  active: 'default',
  draft: 'secondary',
  archived: 'outline',
}

const stockBadge = (qty: number) => {
  if (qty === 0) return 'destructive'
  if (qty <= 5) return 'secondary'
  return 'outline'
}

interface ProductDetailsDialogProps {
  product: Product
  open: boolean
  onOpenChange: (open: boolean) => void
}

function ImageGallery({ product }: { product: Product }) {
  const { product: fullProduct } = useProduct(product.id)
  const images = fullProduct?.images || (product.images ?? [])
  const [activeIndex, setActiveIndex] = useState(0)

  const mainSrc = images[activeIndex]?.src || product.primary_image

  return (
    <div className='space-y-2'>
      {/* Main image */}
      <div className='relative aspect-square w-full bg-muted/20 rounded-lg overflow-hidden border border-muted/50'>
        {mainSrc ? (
          <img src={mainSrc} alt={product.title} className='h-full w-full object-contain' />
        ) : (
          <div className='flex h-full items-center justify-center'>
            <Package className='h-16 w-16 text-muted-foreground/20' />
          </div>
        )}
        {images.length > 1 && (
          <>
            <Button
              variant='secondary'
              size='icon'
              className='absolute left-2 top-1/2 -translate-y-1/2 h-7 w-7 shadow'
              onClick={() => setActiveIndex((i) => Math.max(0, i - 1))}
              disabled={activeIndex === 0}
            >
              <ChevronLeft className='h-4 w-4' />
            </Button>
            <Button
              variant='secondary'
              size='icon'
              className='absolute right-2 top-1/2 -translate-y-1/2 h-7 w-7 shadow'
              onClick={() => setActiveIndex((i) => Math.min(images.length - 1, i + 1))}
              disabled={activeIndex === images.length - 1}
            >
              <ChevronRight className='h-4 w-4' />
            </Button>
            <div className='absolute bottom-2 right-2 bg-black/50 text-white text-[10px] rounded px-1.5 py-0.5'>
              {activeIndex + 1} / {images.length}
            </div>
          </>
        )}
      </div>

      {/* Thumbnails */}
      {images.length > 1 && (
        <div className='flex gap-1.5 overflow-x-auto pb-1'>
          {images.map((img, i) => (
            <button
              key={img.id}
              onClick={() => setActiveIndex(i)}
              className={`h-12 w-12 shrink-0 rounded overflow-hidden border-2 transition-colors ${
                i === activeIndex ? 'border-primary' : 'border-muted/50 hover:border-muted'
              }`}
            >
              <img src={img.src} alt={img.alt_text || ''} className='h-full w-full object-cover' />
            </button>
          ))}
        </div>
      )}
    </div>
  )
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
  if (!value && value !== 0) return null
  return (
    <div className='flex items-start justify-between gap-3 py-1.5'>
      <span className='text-xs text-muted-foreground shrink-0 w-32'>{label}</span>
      <span className='text-xs font-medium text-right'>{value}</span>
    </div>
  )
}

type Tab = 'overview' | 'pricing' | 'details'

export function ProductDetailsDialog({ product, open, onOpenChange }: ProductDetailsDialogProps) {
  const [tab, setTab] = useState<Tab>('overview')

  const tabs: { key: Tab; label: string; icon: React.ReactNode }[] = [
    { key: 'overview', label: 'Overview', icon: <Info className='h-3.5 w-3.5' /> },
    { key: 'pricing', label: 'Pricing', icon: <BarChart3 className='h-3.5 w-3.5' /> },
    { key: 'details', label: 'Details', icon: <Tag className='h-3.5 w-3.5' /> },
  ]

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='sm:max-w-[680px] max-h-[90vh] overflow-hidden flex flex-col'>
        <DialogHeader className='pb-0'>
          <div className='flex items-start justify-between gap-3 pr-6'>
            <div className='min-w-0'>
              <DialogTitle className='text-base font-semibold leading-tight line-clamp-2'>{product.title}</DialogTitle>
              <div className='flex items-center gap-2 mt-1.5 flex-wrap'>
                <Badge variant={statusVariant[product.status]} className='capitalize text-[10px] h-4 px-1.5'>
                  {product.status}
                </Badge>
                {product.published ? (
                  <Badge variant='outline' className='text-[10px] h-4 px-1.5 text-green-600 border-green-200 dark:border-green-800'>
                    <Globe className='h-2.5 w-2.5 mr-1' />Published
                  </Badge>
                ) : (
                  <Badge variant='outline' className='text-[10px] h-4 px-1.5'>Draft</Badge>
                )}
                <Badge variant={stockBadge(product.variant_inventory_qty)} className='text-[10px] h-4 px-1.5 font-mono'>
                  {product.variant_inventory_qty} in stock
                </Badge>
              </div>
            </div>
          </div>
        </DialogHeader>

        {/* Tab nav */}
        <div className='flex border-b border-muted/50 gap-0 -mb-px mt-2'>
          {tabs.map((t) => (
            <button
              key={t.key}
              onClick={() => setTab(t.key)}
              className={`flex items-center gap-1.5 px-4 py-2 text-xs font-medium border-b-2 transition-colors ${
                tab === t.key
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground'
              }`}
            >
              {t.icon}{t.label}
            </button>
          ))}
        </div>

        {/* Content */}
        <div className='flex-1 overflow-y-auto pt-3'>
          {tab === 'overview' && (
            <div className='grid grid-cols-1 sm:grid-cols-2 gap-4'>
              {/* Left: image */}
              <ImageGallery product={product} />

              {/* Right: key info */}
              <div className='space-y-3'>
                <div className='rounded-lg border border-muted/50 p-3 space-y-0 divide-y divide-muted/30'>
                  <InfoRow label='Handle' value={<span className='font-mono text-[10px]'>{product.handle}</span>} />
                  <InfoRow label='Vendor' value={product.vendor} />
                  <InfoRow label='Type' value={product.type} />
                  <InfoRow label='SKU' value={product.variant_sku ? <span className='font-mono text-[10px]'>{product.variant_sku}</span> : null} />
                  <InfoRow label='Barcode' value={product.variant_barcode ? <span className='font-mono text-[10px]'>{product.variant_barcode}</span> : null} />
                  <InfoRow label='Added' value={format(new Date(product.created_at), 'MMM dd, yyyy')} />
                </div>

                {product.tags && product.tags.length > 0 && (
                  <div>
                    <p className='text-[10px] text-muted-foreground mb-1.5 font-medium uppercase tracking-wide'>Tags</p>
                    <div className='flex flex-wrap gap-1'>
                      {product.tags.map((tag) => (
                        <Badge key={tag} variant='outline' className='text-[10px] h-4 px-1.5'>{tag}</Badge>
                      ))}
                    </div>
                  </div>
                )}

                {product.body_html && (
                  <div>
                    <p className='text-[10px] text-muted-foreground mb-1.5 font-medium uppercase tracking-wide'>Description</p>
                    <div
                      className='text-xs text-muted-foreground leading-relaxed max-h-28 overflow-y-auto prose-sm'
                      dangerouslySetInnerHTML={{ __html: product.body_html }}
                    />
                  </div>
                )}
              </div>
            </div>
          )}

          {tab === 'pricing' && (
            <div className='grid grid-cols-1 sm:grid-cols-2 gap-4'>
              {/* Pricing card */}
              <div className='space-y-3'>
                <p className='text-[10px] text-muted-foreground font-medium uppercase tracking-wide'>Pricing</p>
                <div className='rounded-lg border border-muted/50 p-3 space-y-0 divide-y divide-muted/30'>
                  <InfoRow label='Price' value={product.variant_price ? `SAR ${parseFloat(product.variant_price).toFixed(2)}` : '-'} />
                  <InfoRow label='Compare at' value={product.variant_compare_at_price ? `SAR ${parseFloat(product.variant_compare_at_price).toFixed(2)}` : '-'} />
                  <InfoRow label='Cost per item' value={product.cost_per_item ? `SAR ${parseFloat(product.cost_per_item).toFixed(2)}` : '-'} />
                  <InfoRow label='Taxable' value={product.variant_taxable ? 'Yes' : 'No'} />
                </div>

                <p className='text-[10px] text-muted-foreground font-medium uppercase tracking-wide'>Saudi Arabia</p>
                <div className='rounded-lg border border-muted/50 p-3 space-y-0 divide-y divide-muted/30'>
                  <InfoRow label='Included' value={product.included_saudi_arabia ? 'Yes' : 'No'} />
                  <InfoRow label='Price (SA)' value={product.price_saudi_arabia ? `SAR ${parseFloat(product.price_saudi_arabia).toFixed(2)}` : '-'} />
                  <InfoRow label='Compare (SA)' value={product.compare_at_price_saudi_arabia ? `SAR ${parseFloat(product.compare_at_price_saudi_arabia).toFixed(2)}` : '-'} />
                </div>
              </div>

              <div className='space-y-3'>
                <p className='text-[10px] text-muted-foreground font-medium uppercase tracking-wide'>Inventory</p>
                <div className='rounded-lg border border-muted/50 p-3 space-y-0 divide-y divide-muted/30'>
                  <InfoRow label='Quantity' value={
                    <Badge variant={stockBadge(product.variant_inventory_qty)} className='text-[10px] h-4 px-1.5 font-mono'>
                      {product.variant_inventory_qty}
                    </Badge>
                  } />
                  <InfoRow label='Policy' value={product.variant_inventory_policy} />
                  <InfoRow label='Fulfillment' value={product.variant_fulfillment_service} />
                  <InfoRow label='Requires shipping' value={product.variant_requires_shipping ? 'Yes' : 'No'} />
                  <InfoRow label='Weight (g)' value={product.variant_grams ? parseFloat(product.variant_grams).toFixed(0) + 'g' : null} />
                </div>

                <p className='text-[10px] text-muted-foreground font-medium uppercase tracking-wide'>Pakistan</p>
                <div className='rounded-lg border border-muted/50 p-3 space-y-0 divide-y divide-muted/30'>
                  <InfoRow label='Included' value={product.included_pakistan ? 'Yes' : 'No'} />
                  <InfoRow label='Price (PK)' value={product.price_pakistan ? `PKR ${parseFloat(product.price_pakistan).toFixed(2)}` : '-'} />
                </div>
              </div>
            </div>
          )}

          {tab === 'details' && (
            <div className='grid grid-cols-1 sm:grid-cols-2 gap-4'>
              <div className='space-y-3'>
                <p className='text-[10px] text-muted-foreground font-medium uppercase tracking-wide'>Options</p>
                <div className='rounded-lg border border-muted/50 p-3 space-y-0 divide-y divide-muted/30'>
                  {product.option1_name && <InfoRow label={product.option1_name} value={product.option1_value} />}
                  {product.option2_name && <InfoRow label={product.option2_name} value={product.option2_value} />}
                  {product.option3_name && <InfoRow label={product.option3_name} value={product.option3_value} />}
                  {!product.option1_name && <p className='text-xs text-muted-foreground py-2'>No options defined</p>}
                </div>

                {product.available_in && product.available_in.length > 0 && (
                  <>
                    <p className='text-[10px] text-muted-foreground font-medium uppercase tracking-wide'>Available In</p>
                    <div className='flex flex-wrap gap-1'>
                      {product.available_in.map((region) => (
                        <Badge key={region} variant='outline' className='text-[10px] h-4 px-1.5'>{region}</Badge>
                      ))}
                    </div>
                  </>
                )}
              </div>

              <div className='space-y-3'>
                <p className='text-[10px] text-muted-foreground font-medium uppercase tracking-wide'>SEO</p>
                <div className='rounded-lg border border-muted/50 p-3 space-y-2'>
                  {product.seo_title && (
                    <div>
                      <p className='text-[10px] text-muted-foreground'>Title</p>
                      <p className='text-xs font-medium'>{product.seo_title}</p>
                    </div>
                  )}
                  {product.seo_description && (
                    <div>
                      <p className='text-[10px] text-muted-foreground'>Description</p>
                      <p className='text-xs text-muted-foreground leading-relaxed'>{product.seo_description}</p>
                    </div>
                  )}
                  {!product.seo_title && !product.seo_description && (
                    <p className='text-xs text-muted-foreground'>No SEO data</p>
                  )}
                </div>

                <p className='text-[10px] text-muted-foreground font-medium uppercase tracking-wide'>Product Info</p>
                <div className='rounded-lg border border-muted/50 p-3 space-y-0 divide-y divide-muted/30'>
                  <InfoRow label='Category' value={product.product_category} />
                  <InfoRow label='ID' value={<span className='font-mono text-[10px]'>{product.id}</span>} />
                  <InfoRow label='Created' value={format(new Date(product.created_at), 'MMM dd, yyyy HH:mm')} />
                </div>
              </div>
            </div>
          )}
        </div>

        <Separator className='mt-3' />
        <div className='flex justify-end pt-2'>
          <Button variant='outline' size='sm' onClick={() => onOpenChange(false)}>Close</Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}

import { useState } from 'react'
import { format } from 'date-fns'
import { ChevronLeft, ChevronRight, Package, Tag, BarChart3, Info, Globe, Download } from 'lucide-react'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Skeleton } from '@/components/ui/skeleton'
import { usePortalProduct } from '@/hooks/usePortal'
import type { Product } from '@/types/product'

const stockColor = (qty: number) => {
  if (qty === 0) return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
  if (qty <= 5) return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'
  return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
  if (value === null || value === undefined || value === '') return null
  return (
    <div className='flex items-start justify-between gap-3 py-1.5'>
      <span className='w-32 shrink-0 text-xs text-muted-foreground'>{label}</span>
      <span className='text-right text-xs font-medium'>{value}</span>
    </div>
  )
}

function ImageGallery({ product, fullImages }: { product: Product; fullImages: Product['images'] }) {
  const images = fullImages && fullImages.length > 0 ? fullImages : (product.images ?? [])
  const [activeIndex, setActiveIndex] = useState(0)
  const mainSrc = images[activeIndex]?.src || product.primary_image

  return (
    <div className='space-y-2'>
      <div className='relative aspect-square w-full overflow-hidden rounded-lg border border-muted/50 bg-muted/20'>
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
              variant='secondary' size='icon'
              className='absolute left-2 top-1/2 h-7 w-7 -translate-y-1/2 shadow'
              onClick={() => setActiveIndex((i) => Math.max(0, i - 1))}
              disabled={activeIndex === 0}
            >
              <ChevronLeft className='h-4 w-4' />
            </Button>
            <Button
              variant='secondary' size='icon'
              className='absolute right-2 top-1/2 h-7 w-7 -translate-y-1/2 shadow'
              onClick={() => setActiveIndex((i) => Math.min(images.length - 1, i + 1))}
              disabled={activeIndex === images.length - 1}
            >
              <ChevronRight className='h-4 w-4' />
            </Button>
            <div className='absolute bottom-2 right-2 rounded bg-black/50 px-1.5 py-0.5 text-[10px] text-white'>
              {activeIndex + 1} / {images.length}
            </div>
          </>
        )}
      </div>

      {images.length > 1 && (
        <div className='flex gap-1.5 overflow-x-auto pb-1'>
          {images.map((img, i) => (
            <button
              key={img.id}
              onClick={() => setActiveIndex(i)}
              className={`h-12 w-12 shrink-0 overflow-hidden rounded border-2 transition-colors ${
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

type Tab = 'overview' | 'pricing' | 'details'

interface Props {
  product: Product | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function PortalProductDetailsDialog({ product, open, onOpenChange }: Props) {
  const [tab, setTab] = useState<Tab>('overview')
  const { product: fullProduct, loading } = usePortalProduct(open && product ? product.id : null)

  const p = fullProduct || product
  if (!p) return null

  const tabs: { key: Tab; label: string; icon: React.ReactNode }[] = [
    { key: 'overview', label: 'Overview', icon: <Info className='h-3.5 w-3.5' /> },
    { key: 'pricing',  label: 'Pricing',  icon: <BarChart3 className='h-3.5 w-3.5' /> },
    { key: 'details',  label: 'Details',  icon: <Tag className='h-3.5 w-3.5' /> },
  ]

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='flex max-h-[90vh] flex-col overflow-hidden sm:max-w-[680px]'>
        <DialogHeader className='pb-0'>
          <div className='min-w-0 pr-6'>
            <DialogTitle className='line-clamp-2 text-base font-semibold leading-tight'>{p.title}</DialogTitle>
            <div className='mt-1.5 flex flex-wrap items-center gap-2'>
              <Badge variant='outline' className='h-4 px-1.5 text-green-600 border-green-200 dark:border-green-800 text-[10px]'>
                <Globe className='mr-1 h-2.5 w-2.5' />Published
              </Badge>
              <Badge variant='outline' className={`h-4 px-1.5 text-[10px] font-mono tabular-nums ${stockColor(p.variant_inventory_qty)}`}>
                {p.variant_inventory_qty} in stock
              </Badge>
              {p.type && (
                <Badge variant='outline' className='h-4 px-1.5 text-[10px] capitalize'>{p.type}</Badge>
              )}
            </div>
          </div>
        </DialogHeader>

        {/* Tab nav */}
        <div className='-mb-px mt-2 flex gap-0 border-b border-muted/50'>
          {tabs.map((t) => (
            <button
              key={t.key}
              onClick={() => setTab(t.key)}
              className={`flex items-center gap-1.5 border-b-2 px-4 py-2 text-xs font-medium transition-colors ${
                tab === t.key
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground'
              }`}
            >
              {t.icon}{t.label}
            </button>
          ))}
        </div>

        <div className='flex-1 overflow-y-auto pt-3'>
          {loading ? (
            <div className='grid grid-cols-1 gap-4 sm:grid-cols-2'>
              <Skeleton className='aspect-square w-full rounded-lg' />
              <div className='space-y-2'>
                {Array.from({ length: 6 }).map((_, i) => (
                  <Skeleton key={i} className='h-8 w-full rounded' />
                ))}
              </div>
            </div>
          ) : (
            <>
              {tab === 'overview' && (
                <div className='grid grid-cols-1 gap-4 sm:grid-cols-2'>
                  <ImageGallery product={p} fullImages={fullProduct?.images} />

                  <div className='space-y-3'>
                    <div className='divide-y divide-muted/30 rounded-lg border border-muted/50 p-3'>
                      <InfoRow label='Vendor' value={p.vendor} />
                      <InfoRow label='Type' value={p.type} />
                      <InfoRow label='SKU' value={p.variant_sku ? <span className='font-mono text-[10px]'>{p.variant_sku}</span> : null} />
                      <InfoRow label='Barcode' value={p.variant_barcode ? <span className='font-mono text-[10px]'>{p.variant_barcode}</span> : null} />
                      <InfoRow label='Added' value={format(new Date(p.created_at), 'MMM dd, yyyy')} />
                    </div>

                    {p.tags && p.tags.length > 0 && (
                      <div>
                        <p className='mb-1.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground'>Tags</p>
                        <div className='flex flex-wrap gap-1'>
                          {p.tags.map((tag) => (
                            <Badge key={tag} variant='outline' className='h-4 px-1.5 text-[10px]'>{tag}</Badge>
                          ))}
                        </div>
                      </div>
                    )}

                    {p.body_html && (
                      <div>
                        <p className='mb-1.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground'>Description</p>
                        <div
                          className='prose-sm max-h-28 overflow-y-auto text-xs leading-relaxed text-muted-foreground'
                          dangerouslySetInnerHTML={{ __html: p.body_html }}
                        />
                      </div>
                    )}
                  </div>
                </div>
              )}

              {tab === 'pricing' && (
                <div className='grid grid-cols-1 gap-4 sm:grid-cols-2'>
                  <div className='space-y-3'>
                    <p className='text-[10px] font-medium uppercase tracking-wide text-muted-foreground'>Pricing</p>
                    <div className='divide-y divide-muted/30 rounded-lg border border-muted/50 p-3'>
                      <InfoRow label='Price' value={p.variant_price ? `SAR ${parseFloat(p.variant_price).toFixed(2)}` : '—'} />
                      <InfoRow label='Compare at' value={p.variant_compare_at_price ? `SAR ${parseFloat(p.variant_compare_at_price).toFixed(2)}` : '—'} />
                      <InfoRow label='Taxable' value={p.variant_taxable ? 'Yes' : 'No'} />
                    </div>

                    <p className='text-[10px] font-medium uppercase tracking-wide text-muted-foreground'>Saudi Arabia</p>
                    <div className='divide-y divide-muted/30 rounded-lg border border-muted/50 p-3'>
                      <InfoRow label='Included' value={p.included_saudi_arabia ? 'Yes' : 'No'} />
                      <InfoRow label='Price (SA)' value={p.price_saudi_arabia ? `SAR ${parseFloat(p.price_saudi_arabia).toFixed(2)}` : '—'} />
                      <InfoRow
                        label='Compare (SA)'
                        value={p.compare_at_price_saudi_arabia ? `SAR ${parseFloat(p.compare_at_price_saudi_arabia).toFixed(2)}` : '—'}
                      />
                    </div>
                  </div>

                  <div className='space-y-3'>
                    <p className='text-[10px] font-medium uppercase tracking-wide text-muted-foreground'>Stock</p>
                    <div className='divide-y divide-muted/30 rounded-lg border border-muted/50 p-3'>
                      <InfoRow
                        label='Quantity'
                        value={
                          <Badge variant='outline' className={`h-4 px-1.5 text-[10px] font-mono tabular-nums ${stockColor(p.variant_inventory_qty)}`}>
                            {p.variant_inventory_qty}
                          </Badge>
                        }
                      />
                      <InfoRow label='Policy' value={p.variant_inventory_policy} />
                      <InfoRow label='Requires shipping' value={p.variant_requires_shipping ? 'Yes' : 'No'} />
                      <InfoRow label='Weight' value={p.variant_grams ? `${parseFloat(p.variant_grams).toFixed(0)}g` : null} />
                    </div>

                    <p className='text-[10px] font-medium uppercase tracking-wide text-muted-foreground'>Pakistan</p>
                    <div className='divide-y divide-muted/30 rounded-lg border border-muted/50 p-3'>
                      <InfoRow label='Included' value={p.included_pakistan ? 'Yes' : 'No'} />
                      <InfoRow label='Price (PK)' value={p.price_pakistan ? `PKR ${parseFloat(p.price_pakistan).toFixed(2)}` : '—'} />
                    </div>
                  </div>
                </div>
              )}

              {tab === 'details' && (
                <div className='grid grid-cols-1 gap-4 sm:grid-cols-2'>
                  <div className='space-y-3'>
                    <p className='text-[10px] font-medium uppercase tracking-wide text-muted-foreground'>Options</p>
                    <div className='divide-y divide-muted/30 rounded-lg border border-muted/50 p-3'>
                      {p.option1_name && <InfoRow label={p.option1_name} value={p.option1_value} />}
                      {p.option2_name && <InfoRow label={p.option2_name} value={p.option2_value} />}
                      {p.option3_name && <InfoRow label={p.option3_name} value={p.option3_value} />}
                      {!p.option1_name && (
                        <p className='py-2 text-xs text-muted-foreground'>No options defined</p>
                      )}
                    </div>

                    {p.available_in && p.available_in.length > 0 && (
                      <>
                        <p className='text-[10px] font-medium uppercase tracking-wide text-muted-foreground'>Available In</p>
                        <div className='flex flex-wrap gap-1'>
                          {p.available_in.map((region) => (
                            <Badge key={region} variant='outline' className='h-4 px-1.5 text-[10px]'>{region}</Badge>
                          ))}
                        </div>
                      </>
                    )}
                  </div>

                  <div className='space-y-3'>
                    <p className='text-[10px] font-medium uppercase tracking-wide text-muted-foreground'>Product Info</p>
                    <div className='divide-y divide-muted/30 rounded-lg border border-muted/50 p-3'>
                      <InfoRow label='Category' value={p.product_category} />
                      <InfoRow label='Created' value={format(new Date(p.created_at), 'MMM dd, yyyy')} />
                    </div>
                  </div>
                </div>
              )}
            </>
          )}
        </div>

        <Separator className='mt-3' />
        <div className='flex justify-end gap-2 pt-2'>
          <Button
            variant='outline'
            size='sm'
            className='gap-1.5'
            onClick={() => {
              const link = document.createElement('a')
              link.href = `/portal/api/products/download?ids=${p.id}`
              link.rel = 'noopener'
              document.body.appendChild(link)
              link.click()
              document.body.removeChild(link)
            }}
          >
            <Download className='h-3.5 w-3.5' />
            Download for Shopify
          </Button>
          <Button variant='outline' size='sm' onClick={() => onOpenChange(false)}>Close</Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}

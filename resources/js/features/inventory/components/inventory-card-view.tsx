import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Eye, Package } from 'lucide-react'
import { type Product } from '@/types/product'
import { useInventoryContext } from './inventory-provider'
import { InventoryCardsSkeleton } from './inventory-skeleton'
import { InventoryPagination } from './inventory-pagination'
import { ProductActions } from './inventory-row-actions'

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

interface InventoryCardViewProps {
  data: Product[]
  meta?: {
    current_page: number
    total: number
    per_page: number
    last_page: number
    from: number
    to: number
  } | null
  loading?: boolean
  onPageChange?: (page: number) => void
  onPageSizeChange?: (pageSize: number) => void
}

export function InventoryCardView({ data, meta, loading, onPageChange, onPageSizeChange }: InventoryCardViewProps) {
  const { setOpen, setCurrentRow } = useInventoryContext()

  if (loading) return <InventoryCardsSkeleton />

  return (
    <div className='space-y-4'>
      {data.length === 0 ? (
        <div className='flex flex-col items-center justify-center py-16 text-center'>
          <Package className='h-12 w-12 text-muted-foreground/30 mb-3' />
          <p className='text-sm font-medium text-muted-foreground'>No products found</p>
        </div>
      ) : (
        <div className='grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5'>
          {data.map((product) => (
            <Card
              key={product.id}
              className='group overflow-hidden border-muted/50 hover:border-muted hover:shadow-sm transition-all duration-200 cursor-pointer'
              onClick={() => {
                setCurrentRow(product)
                setOpen('view')
              }}
            >
              {/* Image */}
              <div className='relative aspect-square bg-muted/20 overflow-hidden'>
                {product.primary_image ? (
                  <img
                    src={product.primary_image}
                    alt={product.title}
                    className='h-full w-full object-cover group-hover:scale-105 transition-transform duration-300'
                  />
                ) : (
                  <div className='flex h-full items-center justify-center'>
                    <Package className='h-10 w-10 text-muted-foreground/20' />
                  </div>
                )}
                {/* Status badge overlay */}
                <div className='absolute top-2 left-2'>
                  <Badge variant={statusVariant[product.status]} className='text-[9px] py-0 h-4 px-1.5 capitalize shadow-sm'>
                    {product.status}
                  </Badge>
                </div>
                {/* Quick view button */}
                <div className='absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100'>
                  <Button
                    size='sm'
                    variant='secondary'
                    className='h-7 text-xs shadow'
                    onClick={(e) => {
                      e.stopPropagation()
                      setCurrentRow(product)
                      setOpen('view')
                    }}
                  >
                    <Eye className='mr-1.5 h-3 w-3' />
                    View
                  </Button>
                </div>
              </div>

              <CardContent className='p-3 space-y-1.5'>
                <div className='flex items-start justify-between gap-1'>
                  <div className='min-w-0'>
                    <p className='text-xs font-semibold leading-tight line-clamp-2'>{product.title}</p>
                    {product.vendor && (
                      <p className='text-[10px] text-muted-foreground mt-0.5'>{product.vendor}</p>
                    )}
                  </div>
                  <div onClick={(e) => e.stopPropagation()} className='shrink-0 -mr-1 -mt-1'>
                    <ProductActions product={product} />
                  </div>
                </div>

                <div className='flex items-center justify-between gap-1'>
                  <div className='flex flex-col'>
                    <span className='text-xs font-bold'>
                      {product.variant_price ? `SAR ${parseFloat(product.variant_price).toFixed(2)}` : '-'}
                    </span>
                    {product.variant_compare_at_price &&
                      parseFloat(product.variant_compare_at_price) > parseFloat(product.variant_price || '0') && (
                      <span className='text-[10px] text-muted-foreground line-through'>
                        SAR {parseFloat(product.variant_compare_at_price).toFixed(2)}
                      </span>
                    )}
                  </div>
                  <Badge variant={stockBadge(product.variant_inventory_qty)} className='text-[9px] py-0 h-4 px-1.5 font-medium tabular-nums shrink-0'>
                    {product.variant_inventory_qty} in stock
                  </Badge>
                </div>

                {product.variant_sku && (
                  <p className='text-[9px] text-muted-foreground/60 font-mono truncate'>
                    {product.variant_sku}
                  </p>
                )}
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {meta && onPageChange && (
        <InventoryPagination
          meta={meta}
          onPageChange={onPageChange}
          onPageSizeChange={onPageSizeChange}
        />
      )}
    </div>
  )
}

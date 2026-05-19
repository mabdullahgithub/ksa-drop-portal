import { useProductStatistics } from '@/hooks/useProducts'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Package, PackageCheck, AlertTriangle, DollarSign } from 'lucide-react'
import { InventoryStatsSkeleton } from './inventory-skeleton'

export function InventoryStats() {
  const { statistics, loading } = useProductStatistics()

  if (loading) return <InventoryStatsSkeleton />
  if (!statistics) return null

  return (
    <div className='grid gap-3 md:grid-cols-2 lg:grid-cols-4'>
      <Card className='border-muted/50'>
        <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-0 pt-0 gap-0'>
          <CardTitle className='text-xs font-medium text-muted-foreground'>Total Products</CardTitle>
          <Package className='h-5 w-5 text-muted-foreground' />
        </CardHeader>
        <CardContent className='pb-0 -mt-3'>
          <div className='text-xl font-bold'>{statistics.total_products}</div>
          <p className='text-[11px] text-muted-foreground mt-0'>
            {statistics.active_products} active, {statistics.draft_products} draft
          </p>
        </CardContent>
      </Card>

      <Card className='border-muted/50'>
        <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-0 pt-0 gap-0'>
          <CardTitle className='text-xs font-medium text-muted-foreground'>Inventory Value</CardTitle>
          <DollarSign className='h-5 w-5 text-muted-foreground' />
        </CardHeader>
        <CardContent className='pb-0 -mt-3'>
          <div className='text-xl font-bold'>
            SAR {statistics.total_inventory_value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
          </div>
          <p className='text-[11px] text-muted-foreground mt-0'>Total stock value</p>
        </CardContent>
      </Card>

      <Card className='border-muted/50'>
        <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-0 pt-0 gap-0'>
          <CardTitle className='text-xs font-medium text-muted-foreground'>Out of Stock</CardTitle>
          <PackageCheck className='h-5 w-5 text-muted-foreground' />
        </CardHeader>
        <CardContent className='pb-0 -mt-3'>
          <div className='text-xl font-bold'>{statistics.out_of_stock}</div>
          <p className='text-[11px] text-muted-foreground mt-0'>Products with 0 qty</p>
        </CardContent>
      </Card>

      <Card className='border-muted/50'>
        <CardHeader className='flex flex-row items-center justify-between space-y-0 pb-0 pt-0 gap-0'>
          <CardTitle className='text-xs font-medium text-muted-foreground'>Low Stock</CardTitle>
          <AlertTriangle className='h-5 w-5 text-muted-foreground' />
        </CardHeader>
        <CardContent className='pb-0 -mt-3'>
          <div className='text-xl font-bold'>{statistics.low_stock}</div>
          <p className='text-[11px] text-muted-foreground mt-0'>5 or fewer units</p>
        </CardContent>
      </Card>
    </div>
  )
}

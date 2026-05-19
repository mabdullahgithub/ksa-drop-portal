import { useState, useEffect } from 'react'
import { Search, X, SlidersHorizontal, LayoutGrid, List } from 'lucide-react'
import { type Table } from '@tanstack/react-table'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import {
  DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent,
  DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useProductFilterOptions } from '@/hooks/useProducts'
import type { ProductFilters, Product } from '@/types/product'
import { InventoryFiltersSkeleton } from './inventory-skeleton'
import { useInventoryContext } from './inventory-provider'

interface InventoryFiltersProps {
  filters: ProductFilters
  onFiltersChange: (filters: Partial<ProductFilters>) => void
  table?: Table<Product>
}

export function InventoryFilters({ filters, onFiltersChange, table }: InventoryFiltersProps) {
  const { options, loading } = useProductFilterOptions()
  const { viewMode, setViewMode } = useInventoryContext()
  const [searchInput, setSearchInput] = useState(filters.search || '')

  useEffect(() => {
    const timer = setTimeout(() => {
      if (searchInput !== filters.search) {
        onFiltersChange({ search: searchInput, page: 1 })
      }
    }, 500)
    return () => clearTimeout(timer)
  }, [searchInput])

  const handleClearFilters = () => {
    setSearchInput('')
    onFiltersChange({ search: '', status: undefined, vendor: undefined, type: undefined, page: 1 })
  }

  const hasActiveFilters = filters.search || filters.status || filters.vendor || filters.type

  if (loading) return <InventoryFiltersSkeleton />

  return (
    <div className='flex items-center gap-2 flex-wrap'>
      <div className='relative flex-1 min-w-[180px]'>
        <Search className='absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground' />
        <Input
          placeholder='Search products, SKU, vendor...'
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
          className='pl-8 h-9 text-sm'
        />
      </div>

      <Select
        value={filters.status || 'all'}
        onValueChange={(v) => onFiltersChange({ status: v === 'all' ? undefined : v, page: 1 })}
      >
        <SelectTrigger className='w-[110px] h-9 shrink-0'>
          <SelectValue placeholder='Status' />
        </SelectTrigger>
        <SelectContent>
          {options?.statuses.map((s) => (
            <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
          ))}
        </SelectContent>
      </Select>

      {options?.vendors && options.vendors.length > 1 && (
        <Select
          value={filters.vendor || 'all'}
          onValueChange={(v) => onFiltersChange({ vendor: v === 'all' ? undefined : v, page: 1 })}
        >
          <SelectTrigger className='w-[130px] h-9 shrink-0'>
            <SelectValue placeholder='Vendor' />
          </SelectTrigger>
          <SelectContent>
            {options.vendors.map((v) => (
              <SelectItem key={v.value} value={v.value}>{v.label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}

      {options?.types && options.types.length > 1 && (
        <Select
          value={filters.type || 'all'}
          onValueChange={(v) => onFiltersChange({ type: v === 'all' ? undefined : v, page: 1 })}
        >
          <SelectTrigger className='w-[120px] h-9 shrink-0'>
            <SelectValue placeholder='Type' />
          </SelectTrigger>
          <SelectContent>
            {options.types.map((t) => (
              <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}

      {hasActiveFilters && (
        <Button variant='ghost' size='icon' onClick={handleClearFilters} className='h-9 w-9 shrink-0' title='Clear filters'>
          <X className='h-4 w-4' />
        </Button>
      )}

      {/* View mode toggle */}
      <div className='flex items-center rounded-md border border-muted/70 overflow-hidden shrink-0 ml-auto'>
        <Button
          variant={viewMode === 'table' ? 'secondary' : 'ghost'}
          size='sm'
          className='h-9 rounded-none px-2.5'
          onClick={() => setViewMode('table')}
          title='List view'
        >
          <List className='h-4 w-4' />
        </Button>
        <Button
          variant={viewMode === 'card' ? 'secondary' : 'ghost'}
          size='sm'
          className='h-9 rounded-none px-2.5'
          onClick={() => setViewMode('card')}
          title='Card view'
        >
          <LayoutGrid className='h-4 w-4' />
        </Button>
      </div>

      {/* Column visibility — table view only */}
      {viewMode === 'table' && table && (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant='outline' size='sm' className='h-9 shrink-0'>
              <SlidersHorizontal className='mr-2 h-4 w-4' />
              View
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align='end' className='w-[180px]'>
            <DropdownMenuLabel>Toggle columns</DropdownMenuLabel>
            <DropdownMenuSeparator />
            {table
              .getAllColumns()
              .filter((col) => typeof col.accessorFn !== 'undefined' && col.getCanHide())
              .map((col) => (
                <DropdownMenuCheckboxItem
                  key={col.id}
                  className='capitalize'
                  checked={col.getIsVisible()}
                  onCheckedChange={(value) => col.toggleVisibility(!!value)}
                >
                  {col.id.replace(/_/g, ' ')}
                </DropdownMenuCheckboxItem>
              ))}
          </DropdownMenuContent>
        </DropdownMenu>
      )}
    </div>
  )
}

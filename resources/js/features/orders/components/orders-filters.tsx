import { useState, useEffect } from 'react'
import { Search, X, SlidersHorizontal, CreditCard, Tag, Truck, Users } from 'lucide-react'
import { type Table } from '@tanstack/react-table'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { MultiSelectFilter } from '@/components/multi-select-filter'
import { useFilterOptions } from '@/hooks/useOrders'
import type { OrderFilters, Order } from '@/types/order'
import { OrdersFiltersSkeleton } from './orders-skeleton'
import { ClientMultiFilter } from './client-multi-filter'

interface OrdersFiltersProps {
  filters: OrderFilters
  onFiltersChange: (filters: Partial<OrderFilters>) => void
  table?: Table<Order>
}

export function OrdersFilters({ filters, onFiltersChange, table }: OrdersFiltersProps) {
  const { options, loading } = useFilterOptions()
  const [searchInput, setSearchInput] = useState(filters.search || '')

  // Debounce search input
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
    onFiltersChange({
      search: '',
      financial_status: [],
      tags: [],
      country: undefined,
      start_date: undefined,
      end_date: undefined,
      client_ids: [],
      client_type: '',
      has_shipment: false,
      shipment_status: [],
      page: 1,
    })
  }

  const hasActiveFilters =
    filters.search ||
    (filters.financial_status && filters.financial_status.length > 0) ||
    (filters.tags && filters.tags.length > 0) ||
    filters.country ||
    (filters.client_ids && filters.client_ids.length > 0) ||
    filters.client_type ||
    (filters.shipment_status && filters.shipment_status.length > 0)

  if (loading) {
    return <OrdersFiltersSkeleton />
  }

  return (
    <div className='flex items-center gap-2 flex-wrap'>
      {/* Search */}
      <div className='relative flex-1 min-w-[180px]'>
        <Search className='absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground' />
        <Input
          placeholder='Search by order #, customer, phone...'
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
          className='pl-8 h-9 text-sm'
        />
      </div>

      {/* Financial Status */}
      <MultiSelectFilter
        label='Payment'
        icon={CreditCard}
        options={options?.financial_statuses ?? []}
        selected={filters.financial_status ?? []}
        onChange={(values) => onFiltersChange({ financial_status: values, page: 1 })}
      />

      {/* Shipment Status */}
      <MultiSelectFilter
        label='Shipment Status'
        icon={Truck}
        options={options?.shipment_statuses ?? []}
        selected={filters.shipment_status ?? []}
        onChange={(values) => onFiltersChange({ shipment_status: values, page: 1 })}
      />

      {/* Tags */}
      {options?.tags && options.tags.length > 0 && (
        <MultiSelectFilter
          label='Tags'
          icon={Tag}
          options={options.tags}
          selected={filters.tags ?? []}
          onChange={(values) => onFiltersChange({ tags: values, page: 1 })}
        />
      )}

      {/* Client Type Filter */}
      <Select
        value={filters.client_type || 'all'}
        onValueChange={(value) =>
          onFiltersChange({
            client_type: value === 'all' ? '' : (value as 'fulfilment' | 'dropshipper'),
            page: 1,
          })
        }
      >
        <SelectTrigger className='h-9 w-auto min-w-[140px] text-sm'>
          <Users className='mr-2 h-3.5 w-3.5 text-muted-foreground shrink-0' />
          <SelectValue placeholder='All Orders' />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value='all'>All Orders</SelectItem>
          <SelectItem value='fulfilment'>Fulfilment</SelectItem>
          <SelectItem value='dropshipper'>Dropshippers</SelectItem>
        </SelectContent>
      </Select>

      {/* Client Multi-Select Filter */}
      {options?.clients && options.clients.length > 0 && (
        <ClientMultiFilter
          clients={options.clients}
          selectedIds={filters.client_ids || []}
          onChange={(ids) => onFiltersChange({ client_ids: ids, page: 1 })}
        />
      )}

      {/* Clear Filters Button */}
      {hasActiveFilters && (
        <Button
          variant='ghost'
          size='icon'
          onClick={handleClearFilters}
          className='h-9 w-9 shrink-0'
          title='Clear all filters'
        >
          <X className='h-4 w-4' />
        </Button>
      )}

      {/* View Dropdown */}
      {table && (
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
              .filter(
                (column) =>
                  typeof column.accessorFn !== 'undefined' && column.getCanHide()
              )
              .map((column) => {
                return (
                  <DropdownMenuCheckboxItem
                    key={column.id}
                    className='capitalize'
                    checked={column.getIsVisible()}
                    onCheckedChange={(value) => column.toggleVisibility(!!value)}
                  >
                    {column.id.replace(/_/g, ' ')}
                  </DropdownMenuCheckboxItem>
                )
              })}
          </DropdownMenuContent>
        </DropdownMenu>
      )}
    </div>
  )
}

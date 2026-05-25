import { useState, useEffect } from 'react'
import { Search, X, SlidersHorizontal } from 'lucide-react'
import { type Table } from '@tanstack/react-table'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
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
      fulfillment_status: undefined,
      financial_status: undefined,
      payment_method: undefined,
      utm_source: undefined,
      country: undefined,
      start_date: undefined,
      end_date: undefined,
      client_ids: [],
      page: 1,
    })
  }

  const hasActiveFilters =
    filters.search ||
    filters.fulfillment_status ||
    filters.financial_status ||
    filters.payment_method ||
    filters.utm_source ||
    filters.country ||
    (filters.client_ids && filters.client_ids.length > 0)

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

      {/* Fulfillment Status */}
      <Select
        value={filters.fulfillment_status || 'all'}
        onValueChange={(value) =>
          onFiltersChange({
            fulfillment_status: value === 'all' ? undefined : value,
            page: 1,
          })
        }
      >
        <SelectTrigger className='w-[115px] h-9 shrink-0'>
          <SelectValue placeholder='Fulfillment' />
        </SelectTrigger>
        <SelectContent>
          {options?.fulfillment_statuses.map((status) => (
            <SelectItem key={status.value} value={status.value}>
              {status.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      {/* Financial Status */}
      <Select
        value={filters.financial_status || 'all'}
        onValueChange={(value) =>
          onFiltersChange({
            financial_status: value === 'all' ? undefined : value,
            page: 1,
          })
        }
      >
        <SelectTrigger className='w-[110px] h-9 shrink-0'>
          <SelectValue placeholder='Payment' />
        </SelectTrigger>
        <SelectContent>
          {options?.financial_statuses.map((status) => (
            <SelectItem key={status.value} value={status.value}>
              {status.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      {/* Payment Method */}
      <Select
        value={filters.payment_method || 'all'}
        onValueChange={(value) =>
          onFiltersChange({
            payment_method: value === 'all' ? undefined : value,
            page: 1,
          })
        }
      >
        <SelectTrigger className='w-[100px] h-9 shrink-0'>
          <SelectValue placeholder='Method' />
        </SelectTrigger>
        <SelectContent>
          {options?.payment_methods.map((method) => (
            <SelectItem key={method.value} value={method.value}>
              {method.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      {/* UTM Source */}
      {options?.utm_sources && options.utm_sources.length > 0 && (
        <Select
          value={filters.utm_source || 'all'}
          onValueChange={(value) =>
            onFiltersChange({
              utm_source: value === 'all' ? undefined : value,
              page: 1,
            })
          }
        >
          <SelectTrigger className='w-[100px] h-9 shrink-0'>
            <SelectValue placeholder='Source' />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value='all'>All</SelectItem>
            {options.utm_sources.map((source) => (
              <SelectItem key={source.value} value={source.value}>
                {source.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}

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

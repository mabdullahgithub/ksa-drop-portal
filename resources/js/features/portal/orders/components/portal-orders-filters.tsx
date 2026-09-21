import { useState, useEffect } from 'react'
import { Search, X, SlidersHorizontal } from 'lucide-react'
import { type Table } from '@tanstack/react-table'
import { Input } from '@/components/ui/input'
import { SearchBeam } from '@/components/search-beam'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { MultiSelectFilter } from '@/components/multi-select-filter'
import { DateRangeFilter } from '@/components/date-range-filter'
import { CityFilter } from '@/components/city-filter'
import type { CityFilterOption } from '@/types/order'

interface PortalOrdersFiltersProps {
  filters: Record<string, any>
  onFiltersChange: (filters: Partial<Record<string, any>>) => void
  filterOptions?: {
    financial_statuses?: Array<{ value: string; label: string }>
    shipment_statuses?: Array<{ value: string; label: string }>
    tags?: Array<{ value: string; label: string }>
    cities?: CityFilterOption[]
  }
  table?: Table<any>
}

export function PortalOrdersFilters({
  filters,
  onFiltersChange,
  filterOptions,
  table,
}: PortalOrdersFiltersProps) {
  // Normalize with ?? so an explicit null (e.g. the options fetch failed) is
  // handled too — a default parameter only covers undefined, not null.
  const options = filterOptions ?? {}
  const [searchInput, setSearchInput] = useState(filters.search || '')

  // Debounce search input
  useEffect(() => {
    const timer = setTimeout(() => {
      if (searchInput !== filters.search) {
        onFiltersChange({ search: searchInput, page: 1 })
      }
    }, 500)

    return () => clearTimeout(timer)
  }, [searchInput, filters.search, onFiltersChange])

  const handleClearFilters = () => {
    setSearchInput('')
    onFiltersChange({
      search: '',
      financial_status: [],
      shipment_status: [],
      tags: [],
      start_date: undefined,
      end_date: undefined,
      tz: undefined,
      cities: [],
      page: 1,
    })
  }

  const hasActiveFilters =
    filters.search ||
    (filters.financial_status && filters.financial_status.length > 0) ||
    (filters.shipment_status && filters.shipment_status.length > 0) ||
    (filters.tags && filters.tags.length > 0) ||
    filters.start_date ||
    filters.end_date ||
    (filters.cities && filters.cities.length > 0)

  return (
    <div className='flex items-center gap-2 flex-wrap'>
      {/* Search */}
      <div className='relative flex-1 min-w-[180px]'>
        <Search className='absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground' />
        <SearchBeam>
          <Input
            placeholder='Search by order #, customer, phone...'
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            className='pl-8 h-9 text-sm'
          />
        </SearchBeam>
      </div>

      {/* Financial Status */}
      {options.financial_statuses && options.financial_statuses.length > 0 && (
        <MultiSelectFilter
          label='Payment'
          options={options.financial_statuses}
          selected={filters.financial_status ?? []}
          onChange={(values) => onFiltersChange({ financial_status: values, page: 1 })}
        />
      )}

      {/* Shipment Status */}
      {options.shipment_statuses && options.shipment_statuses.length > 0 && (
        <MultiSelectFilter
          label='Shipment Status'
          options={options.shipment_statuses}
          selected={filters.shipment_status ?? []}
          onChange={(values) => onFiltersChange({ shipment_status: values, page: 1 })}
        />
      )}

      {/* Order Date (From / To) */}
      <DateRangeFilter
        from={filters.start_date}
        to={filters.end_date}
        onChange={({ from, to }) =>
          onFiltersChange({
            start_date: from,
            end_date: to,
            tz: from || to ? Intl.DateTimeFormat().resolvedOptions().timeZone : undefined,
            page: 1,
          })
        }
      />

      {/* City — searchable by English or Arabic name, or any stored spelling */}
      <CityFilter
        cities={options.cities ?? []}
        selected={filters.cities ?? []}
        onChange={(values) => onFiltersChange({ cities: values, page: 1 })}
      />

      {/* Tags */}
      {options.tags && options.tags.length > 0 && (
        <MultiSelectFilter
          label='Tags'
          options={options.tags}
          selected={filters.tags ?? []}
          onChange={(values) => onFiltersChange({ tags: values, page: 1 })}
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

      {/* Column visibility control */}
      {table && (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant='outline' size='sm' className='h-9 shrink-0 ml-auto'>
              <SlidersHorizontal className='mr-2 h-4 w-4' />
              View
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align='end' className='w-[180px]'>
            <DropdownMenuLabel>Toggle columns</DropdownMenuLabel>
            <DropdownMenuSeparator />
            {table
              .getAllColumns()
              .filter((column) => typeof column.accessorFn !== 'undefined' && column.getCanHide())
              .map((column) => (
                <DropdownMenuCheckboxItem
                  key={column.id}
                  className='capitalize'
                  checked={column.getIsVisible()}
                  onCheckedChange={(value) => column.toggleVisibility(!!value)}
                >
                  {column.id.replace(/_/g, ' ')}
                </DropdownMenuCheckboxItem>
              ))}
          </DropdownMenuContent>
        </DropdownMenu>
      )}
    </div>
  )
}

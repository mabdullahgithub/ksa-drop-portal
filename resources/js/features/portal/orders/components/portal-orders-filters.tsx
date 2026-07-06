import { useState, useEffect } from 'react'
import { Search, X } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { MultiSelectFilter } from '@/components/multi-select-filter'
import type { OrderFilters } from '@/types/order'

interface PortalOrdersFiltersProps {
  filters: Record<string, any>
  onFiltersChange: (filters: Partial<Record<string, any>>) => void
  filterOptions?: {
    financial_statuses?: Array<{ value: string; label: string }>
    shipment_statuses?: Array<{ value: string; label: string }>
    tags?: Array<{ value: string; label: string }>
  }
}

export function PortalOrdersFilters({
  filters,
  onFiltersChange,
  filterOptions = {},
}: PortalOrdersFiltersProps) {
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
      page: 1,
    })
  }

  const hasActiveFilters =
    filters.search ||
    (filters.financial_status && filters.financial_status.length > 0) ||
    (filters.shipment_status && filters.shipment_status.length > 0) ||
    (filters.tags && filters.tags.length > 0)

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
      {filterOptions.financial_statuses && filterOptions.financial_statuses.length > 0 && (
        <MultiSelectFilter
          label='Payment'
          options={filterOptions.financial_statuses}
          selected={filters.financial_status ?? []}
          onChange={(values) => onFiltersChange({ financial_status: values, page: 1 })}
        />
      )}

      {/* Shipment Status */}
      {filterOptions.shipment_statuses && filterOptions.shipment_statuses.length > 0 && (
        <MultiSelectFilter
          label='Shipment Status'
          options={filterOptions.shipment_statuses}
          selected={filters.shipment_status ?? []}
          onChange={(values) => onFiltersChange({ shipment_status: values, page: 1 })}
        />
      )}

      {/* Tags */}
      {filterOptions.tags && filterOptions.tags.length > 0 && (
        <MultiSelectFilter
          label='Tags'
          options={filterOptions.tags}
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
    </div>
  )
}

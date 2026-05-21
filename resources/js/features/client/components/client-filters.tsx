import { Search, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { ClientFilters, ClientFilterOptions } from '@/types/client'

interface ClientFiltersProps {
  filters: ClientFilters
  filterOptions: ClientFilterOptions | null
  onFiltersChange: (filters: Partial<ClientFilters>) => void
}

export function ClientFiltersComponent({ filters, filterOptions, onFiltersChange }: ClientFiltersProps) {
  const handleReset = () => {
    onFiltersChange({
      search: '',
      status: 'all',
      type: 'all',
      page: 1,
    })
  }

  const hasActiveFilters = (filters.search && filters.search !== '') ||
    (filters.status && filters.status !== 'all') ||
    (filters.type && filters.type !== 'all')

  return (
    <div className='flex flex-col gap-3 sm:flex-row sm:items-center'>
      <div className='relative flex-1'>
        <Search className='absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground' />
        <Input
          placeholder='Search clients...'
          value={filters.search || ''}
          onChange={(e) => onFiltersChange({ search: e.target.value, page: 1 })}
          className='pl-8'
        />
      </div>
      <Select
        value={filters.status || 'all'}
        onValueChange={(value) => onFiltersChange({ status: value, page: 1 })}
      >
        <SelectTrigger className='w-[140px]'>
          <SelectValue placeholder='Status' />
        </SelectTrigger>
        <SelectContent>
          {(filterOptions?.statuses || [
            { value: 'all', label: 'All' },
            { value: 'active', label: 'Active' },
            { value: 'inactive', label: 'Inactive' },
            { value: 'suspended', label: 'Suspended' },
          ]).map((opt) => (
            <SelectItem key={opt.value} value={opt.value}>
              {opt.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      <Select
        value={filters.type || 'all'}
        onValueChange={(value) => onFiltersChange({ type: value, page: 1 })}
      >
        <SelectTrigger className='w-[150px]'>
          <SelectValue placeholder='Type' />
        </SelectTrigger>
        <SelectContent>
          {(filterOptions?.types || [
            { value: 'all', label: 'All Types' },
            { value: 'dropshipper', label: 'Dropshipper' },
            { value: 'fulfilment', label: 'Fulfilment' },
          ]).map((opt) => (
            <SelectItem key={opt.value} value={opt.value}>
              {opt.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      {hasActiveFilters && (
        <Button variant='ghost' size='sm' onClick={handleReset}>
          <X className='mr-1 h-4 w-4' />
          Reset
        </Button>
      )}
    </div>
  )
}

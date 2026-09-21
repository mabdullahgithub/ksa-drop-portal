import { MapPin } from 'lucide-react'
import { MultiSelectFilter } from '@/components/multi-select-filter'
import type { CityFilterOption } from '@/types/order'

interface CityFilterProps {
  cities: CityFilterOption[]
  selected: string[]
  onChange: (values: string[]) => void
}

/**
 * Searchable city multi-select for order lists. Each option is a city group
 * from the backend (CityDirectory), so it matches every stored spelling —
 * English or Arabic, any case — and can be searched by any of them.
 */
export function CityFilter({ cities, selected, onChange }: CityFilterProps) {
  return (
    <MultiSelectFilter
      label='City'
      icon={MapPin}
      options={cities.map((city) => ({
        value: city.value,
        label: city.label,
        sublabel: [city.ar, `${city.count.toLocaleString()} ${city.count === 1 ? 'order' : 'orders'}`]
          .filter(Boolean)
          .join(' · '),
        keywords: city.keywords,
      }))}
      selected={selected}
      onChange={onChange}
      searchPlaceholder='Search city (English or العربية)...'
      contentClassName='w-[280px]'
    />
  )
}

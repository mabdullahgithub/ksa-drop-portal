import { useMemo, useState } from 'react'
import { Check, ChevronsUpDown } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { cn } from '@/lib/utils'
import { JNT_LOCATIONS, JNT_PROVINCES, type JntCity } from '@/data/jnt-locations'

interface JntProvinceSelectProps {
  value: string
  onChange: (province: string) => void
  placeholder?: string
  className?: string
}

export function JntProvinceSelect({ value, onChange, placeholder = 'Select province...', className }: JntProvinceSelectProps) {
  const [open, setOpen] = useState(false)

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant='outline'
          role='combobox'
          aria-expanded={open}
          className={cn('h-9 w-full justify-between px-3 font-normal', !value && 'text-muted-foreground', className)}
        >
          <span className='truncate'>{value || placeholder}</span>
          <ChevronsUpDown className='ml-2 h-3.5 w-3.5 shrink-0 text-muted-foreground' />
        </Button>
      </PopoverTrigger>
      <PopoverContent className='w-[--radix-popover-trigger-width] p-0' align='start'>
        <Command>
          <CommandInput placeholder='Search province...' />
          <CommandList>
            <CommandEmpty>No province found.</CommandEmpty>
            <CommandGroup>
              {JNT_PROVINCES.map((province) => (
                <CommandItem
                  key={province}
                  value={province}
                  onSelect={() => {
                    onChange(province)
                    setOpen(false)
                  }}
                >
                  <Check className={cn('mr-2 h-4 w-4', value === province ? 'opacity-100' : 'opacity-0')} />
                  {province}
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}

interface JntCitySelectProps {
  /** Selected province — when set, only that province's cities are listed. */
  province: string
  value: string
  onChange: (city: string) => void
  /** Called when a city is picked while no/another province is selected, so the form can sync the province. */
  onProvinceChange?: (province: string) => void
  placeholder?: string
  className?: string
}

export function JntCitySelect({ province, value, onChange, onProvinceChange, placeholder = 'Select city...', className }: JntCitySelectProps) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')

  const groups = useMemo<[string, JntCity[]][]>(() => {
    if (province && JNT_LOCATIONS[province]) {
      return [[province, JNT_LOCATIONS[province]]]
    }
    return Object.entries(JNT_LOCATIONS)
  }, [province])

  const hasExactMatch = groups.some(([, cities]) =>
    cities.some((c) => c.en.toLowerCase() === search.trim().toLowerCase())
  )

  return (
    <Popover open={open} onOpenChange={(o) => { setOpen(o); if (!o) setSearch('') }}>
      <PopoverTrigger asChild>
        <Button
          variant='outline'
          role='combobox'
          aria-expanded={open}
          className={cn('h-9 w-full justify-between px-3 font-normal', !value && 'text-muted-foreground', className)}
        >
          <span className='truncate'>{value || placeholder}</span>
          <ChevronsUpDown className='ml-2 h-3.5 w-3.5 shrink-0 text-muted-foreground' />
        </Button>
      </PopoverTrigger>
      <PopoverContent className='w-[--radix-popover-trigger-width] p-0' align='start'>
        <Command>
          <CommandInput placeholder='Search city (EN/AR)...' value={search} onValueChange={setSearch} />
          <CommandList>
            <CommandEmpty>No city found.</CommandEmpty>
            {groups.map(([groupProvince, cities]) => (
              <CommandGroup key={groupProvince} heading={province ? undefined : groupProvince}>
                {cities.map((city) => (
                  <CommandItem
                    key={`${groupProvince}-${city.en}`}
                    value={`${groupProvince}-${city.en}`}
                    keywords={[city.en, city.ar]}
                    onSelect={() => {
                      onChange(city.en)
                      if (groupProvince !== province) onProvinceChange?.(groupProvince)
                      setOpen(false)
                      setSearch('')
                    }}
                  >
                    <Check className={cn('mr-2 h-4 w-4 shrink-0', value === city.en ? 'opacity-100' : 'opacity-0')} />
                    <span className='truncate'>{city.en}</span>
                    <span className='ml-auto flex items-center gap-2 pl-2 text-xs text-muted-foreground'>
                      <span dir='rtl'>{city.ar}</span>
                      {city.sla && <span className='rounded bg-muted px-1'>{city.sla}</span>}
                    </span>
                  </CommandItem>
                ))}
              </CommandGroup>
            ))}
            {search.trim() !== '' && !hasExactMatch && (
              <CommandGroup forceMount>
                <CommandItem
                  forceMount
                  value={`__custom__${search}`}
                  onSelect={() => {
                    onChange(search.trim())
                    setOpen(false)
                    setSearch('')
                  }}
                >
                  <span className='text-muted-foreground'>Use custom city:</span>
                  <span className='ml-1 font-medium'>&quot;{search.trim()}&quot;</span>
                </CommandItem>
              </CommandGroup>
            )}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}

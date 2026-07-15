import { useMemo, useState } from 'react'
import { useCommandState } from 'cmdk'
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
    // modal so wheel scrolling works when rendered inside a Dialog (its scroll
    // lock otherwise blocks wheel events on the portaled popover content)
    <Popover open={open} onOpenChange={setOpen} modal>
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

  const groups = useMemo<[string, JntCity[]][]>(() => {
    if (province && JNT_LOCATIONS[province]) {
      return [[province, JNT_LOCATIONS[province]]]
    }
    return Object.entries(JNT_LOCATIONS)
  }, [province])

  // Flat set of the currently-listed city names, used to decide whether the
  // typed search is already an exact city (in which case no "custom" row).
  const cityNames = useMemo(
    () => groups.flatMap(([, cities]) => cities.map((c) => c.en.toLowerCase())),
    [groups]
  )

  return (
    <Popover open={open} onOpenChange={setOpen} modal>
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
          <CommandInput placeholder='Search city (EN/AR)...' />
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
            <CustomCityItem
              cityNames={cityNames}
              onSelect={(city) => {
                onChange(city)
                setOpen(false)
              }}
            />
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}

/**
 * Offers "Use custom city: X" when the typed search isn't an exact match for a
 * listed city. Reads the live search straight from cmdk (via useCommandState)
 * so the CommandInput can stay uncontrolled — a controlled input in cmdk only
 * syncs into the filter through a fragile effect and can stop filtering.
 */
function CustomCityItem({ cityNames, onSelect }: { cityNames: string[]; onSelect: (city: string) => void }) {
  const search = useCommandState((state) => state.search)
  const trimmed = search.trim()

  if (trimmed === '' || cityNames.includes(trimmed.toLowerCase())) return null

  return (
    <CommandGroup forceMount>
      <CommandItem forceMount value={`__custom__${trimmed}`} onSelect={() => onSelect(trimmed)}>
        <span className='text-muted-foreground'>Use custom city:</span>
        <span className='ml-1 font-medium'>&quot;{trimmed}&quot;</span>
      </CommandItem>
    </CommandGroup>
  )
}

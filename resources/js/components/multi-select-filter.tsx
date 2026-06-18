import { useState, type ComponentType } from 'react'
import { Check, ChevronsUpDown, X, type LucideProps } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'

export interface MultiSelectOption {
  value: string
  label: string
  /** Optional secondary line shown beneath the label (e.g. a code or id). */
  sublabel?: string
}

interface MultiSelectFilterProps {
  /** Label shown on the trigger when nothing is selected. */
  label: string
  options: MultiSelectOption[]
  selected: string[]
  onChange: (values: string[]) => void
  icon?: ComponentType<LucideProps>
  /** Placeholder for the search box. Defaults to `Search ${label}...`. */
  searchPlaceholder?: string
  emptyText?: string
  className?: string
  contentClassName?: string
}

/**
 * Reusable searchable multi-select filter. Shows its label (not "All") when
 * empty, the selected value when one is chosen, or a count when several are.
 * Use this for any list-style filter across the platform.
 */
export function MultiSelectFilter({
  label,
  options,
  selected,
  onChange,
  icon: Icon,
  searchPlaceholder,
  emptyText,
  className,
  contentClassName,
}: MultiSelectFilterProps) {
  const [open, setOpen] = useState(false)

  const toggle = (value: string) => {
    if (selected.includes(value)) {
      onChange(selected.filter((v) => v !== value))
    } else {
      onChange([...selected, value])
    }
  }

  const clearAll = (e: React.MouseEvent) => {
    e.stopPropagation()
    onChange([])
  }

  const selectedOptions = options.filter((o) => selected.includes(o.value))

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant='outline'
          size='sm'
          className={cn(
            'h-9 shrink-0 gap-1.5 text-sm font-normal',
            selected.length > 0 && 'border-primary/50',
            className
          )}
        >
          {Icon && <Icon className='h-3.5 w-3.5 shrink-0' />}
          {selected.length === 0 ? (
            <span className='text-muted-foreground'>{label}</span>
          ) : selected.length === 1 ? (
            <span className='max-w-[120px] truncate'>{selectedOptions[0]?.label}</span>
          ) : (
            <span>{selected.length} selected</span>
          )}
          {selected.length > 0 ? (
            <X
              className='h-3.5 w-3.5 shrink-0 text-muted-foreground hover:text-foreground ml-0.5'
              onClick={clearAll}
            />
          ) : (
            <ChevronsUpDown className='h-3.5 w-3.5 shrink-0 text-muted-foreground ml-0.5' />
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent className={cn('w-[240px] p-0', contentClassName)} align='start'>
        <Command>
          <CommandInput
            placeholder={searchPlaceholder ?? `Search ${label.toLowerCase()}...`}
            className='h-9'
          />
          <CommandList>
            <CommandEmpty>{emptyText ?? `No ${label.toLowerCase()} found.`}</CommandEmpty>
            <CommandGroup>
              {options.map((option) => {
                const isSelected = selected.includes(option.value)
                return (
                  <CommandItem
                    key={option.value}
                    value={`${option.label} ${option.sublabel ?? ''}`}
                    onSelect={() => toggle(option.value)}
                    className='gap-2'
                  >
                    <div
                      className={cn(
                        'flex h-4 w-4 items-center justify-center rounded-sm border border-primary shrink-0',
                        isSelected ? 'bg-primary text-primary-foreground' : 'opacity-50'
                      )}
                    >
                      {isSelected && <Check className='h-3 w-3' />}
                    </div>
                    <div className='flex flex-col min-w-0'>
                      <span className='truncate text-sm'>{option.label}</span>
                      {option.sublabel && (
                        <span className='text-xs text-muted-foreground'>{option.sublabel}</span>
                      )}
                    </div>
                  </CommandItem>
                )
              })}
            </CommandGroup>
          </CommandList>
          {selected.length > 0 && (
            <div className='border-t p-2'>
              <div className='flex flex-wrap gap-1'>
                {selectedOptions.slice(0, 3).map((o) => (
                  <Badge
                    key={o.value}
                    variant='secondary'
                    className='text-xs gap-1 h-5 cursor-pointer'
                    onClick={() => toggle(o.value)}
                  >
                    {o.label}
                    <X className='h-3 w-3' />
                  </Badge>
                ))}
                {selectedOptions.length > 3 && (
                  <Badge variant='secondary' className='text-xs h-5'>
                    +{selectedOptions.length - 3} more
                  </Badge>
                )}
              </div>
            </div>
          )}
        </Command>
      </PopoverContent>
    </Popover>
  )
}

import { useEffect, useRef, useState } from 'react'
import { Check, ChevronsUpDown, Loader2 } from 'lucide-react'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { cn } from '@/lib/utils'

export interface LogesTechsVillage {
  id: number | string | null
  name: string
  english_name?: string | null
  city?: string | null
}

interface LogesTechsVillageSelectProps {
  /** Currently selected district name (display value). */
  value: string
  /** Currently selected district id — what actually disambiguates the choice. */
  valueId: string
  onChange: (village: { id: string; name: string }) => void
  placeholder?: string
  className?: string
}

/**
 * District ("village") picker for LogesTechs shipments.
 *
 * Searches server-side rather than filtering a bundled list: LogesTechs returns
 * only ~10 districts per request and the full Saudi list is large, so the query
 * is forwarded to their `/addresses/villages` lookup as the user types.
 *
 * District names are not unique — LogesTechs has two separate "Riyadh" entries
 * under different cities — so the city is always shown alongside the name, and
 * the selected *id* is what gets submitted.
 */
export function LogesTechsVillageSelect({
  value,
  valueId,
  onChange,
  placeholder = 'Search district...',
  className,
}: LogesTechsVillageSelectProps) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [villages, setVillages] = useState<LogesTechsVillage[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  // Guards against a slower earlier request overwriting a newer one's results.
  const requestRef = useRef(0)

  useEffect(() => {
    if (!open) return

    const requestId = ++requestRef.current
    const timer = setTimeout(async () => {
      setLoading(true)
      setError(null)
      try {
        const res = await axios.get('/api/logestechs/villages', {
          params: search.trim() ? { search: search.trim() } : {},
        })
        if (requestRef.current !== requestId) return
        setVillages(res.data.villages || [])
      } catch (err: any) {
        if (requestRef.current !== requestId) return
        setVillages([])
        setError(err.response?.data?.message || 'Could not load districts.')
      } finally {
        if (requestRef.current === requestId) setLoading(false)
      }
    }, 300)

    return () => clearTimeout(timer)
  }, [search, open])

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
        {/* shouldFilter={false} — results are already filtered server-side by
            LogesTechs; filtering again locally would hide valid matches whose
            Arabic name doesn't contain the Latin search text. */}
        <Command shouldFilter={false}>
          <CommandInput placeholder='Search district...' value={search} onValueChange={setSearch} />
          <CommandList>
            {loading && (
              <div className='flex items-center justify-center gap-2 py-6 text-sm text-muted-foreground'>
                <Loader2 className='h-4 w-4 animate-spin' />
                Loading districts...
              </div>
            )}
            {!loading && error && (
              <div className='px-3 py-6 text-center text-sm text-muted-foreground'>{error}</div>
            )}
            {!loading && !error && villages.length === 0 && (
              <CommandEmpty>
                {search.trim() ? 'No district found.' : 'Type to search districts.'}
              </CommandEmpty>
            )}
            {!loading && !error && villages.length > 0 && (
              <CommandGroup>
                {villages.map((v) => {
                  const id = String(v.id ?? '')
                  const selected = id !== '' && id === valueId
                  return (
                    <CommandItem
                      key={id || v.name}
                      value={id || v.name}
                      onSelect={() => {
                        onChange({ id, name: v.name })
                        setOpen(false)
                      }}
                    >
                      <Check className={cn('mr-2 h-4 w-4', selected ? 'opacity-100' : 'opacity-0')} />
                      <span className='flex-1 truncate'>
                        {v.name}
                        {v.city ? <span className='text-muted-foreground'> — {v.city}</span> : null}
                      </span>
                    </CommandItem>
                  )
                })}
              </CommandGroup>
            )}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}

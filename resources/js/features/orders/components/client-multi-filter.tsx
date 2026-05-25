import { useState } from 'react'
import { Check, ChevronsUpDown, Users, X } from 'lucide-react'
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
import type { ClientFilterOption } from '@/types/order'

interface ClientMultiFilterProps {
  clients: ClientFilterOption[]
  selectedIds: number[]
  onChange: (ids: number[]) => void
}

export function ClientMultiFilter({ clients, selectedIds, onChange }: ClientMultiFilterProps) {
  const [open, setOpen] = useState(false)

  const toggleClient = (id: number) => {
    if (selectedIds.includes(id)) {
      onChange(selectedIds.filter((x) => x !== id))
    } else {
      onChange([...selectedIds, id])
    }
  }

  const clearAll = (e: React.MouseEvent) => {
    e.stopPropagation()
    onChange([])
  }

  const selectedClients = clients.filter((c) => selectedIds.includes(c.value))

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant='outline'
          size='sm'
          className={cn(
            'h-9 shrink-0 gap-1.5 text-sm font-normal',
            selectedIds.length > 0 && 'border-primary/50'
          )}
        >
          <Users className='h-3.5 w-3.5 shrink-0' />
          {selectedIds.length === 0 ? (
            <span className='text-muted-foreground'>Client</span>
          ) : selectedIds.length === 1 ? (
            <span className='max-w-[100px] truncate'>{selectedClients[0]?.label}</span>
          ) : (
            <span>{selectedIds.length} clients</span>
          )}
          {selectedIds.length > 0 ? (
            <X
              className='h-3.5 w-3.5 shrink-0 text-muted-foreground hover:text-foreground ml-0.5'
              onClick={clearAll}
            />
          ) : (
            <ChevronsUpDown className='h-3.5 w-3.5 shrink-0 text-muted-foreground ml-0.5' />
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent className='w-[260px] p-0' align='start'>
        <Command>
          <CommandInput placeholder='Search clients...' className='h-9' />
          <CommandList>
            <CommandEmpty>No clients found.</CommandEmpty>
            <CommandGroup>
              {clients.map((client) => {
                const isSelected = selectedIds.includes(client.value)
                return (
                  <CommandItem
                    key={client.value}
                    value={`${client.label} ${client.client_id}`}
                    onSelect={() => toggleClient(client.value)}
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
                      <span className='truncate text-sm'>{client.label}</span>
                      <span className='text-xs text-muted-foreground'>{client.client_id}</span>
                    </div>
                  </CommandItem>
                )
              })}
            </CommandGroup>
          </CommandList>
          {selectedIds.length > 0 && (
            <div className='border-t p-2'>
              <div className='flex flex-wrap gap-1'>
                {selectedClients.slice(0, 3).map((c) => (
                  <Badge
                    key={c.value}
                    variant='secondary'
                    className='text-xs gap-1 h-5 cursor-pointer'
                    onClick={() => toggleClient(c.value)}
                  >
                    {c.label}
                    <X className='h-3 w-3' />
                  </Badge>
                ))}
                {selectedClients.length > 3 && (
                  <Badge variant='secondary' className='text-xs h-5'>
                    +{selectedClients.length - 3} more
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

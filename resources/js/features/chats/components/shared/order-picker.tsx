import { useEffect, useState } from 'react'
import { format } from 'date-fns'
import { Check, ChevronsUpDown, Loader2, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import type { ChatOrderOption } from '../../types'

export interface PickedOrder {
  id: number
  order_number: string
}

/** Pick one of a client's orders by number, searched on the server. */
export function OrderPicker({
  value,
  onChange,
  search,
  disabled,
  placeholder = 'Link an order (optional)',
}: {
  value: PickedOrder | null
  onChange: (order: PickedOrder | null) => void
  search: (q: string) => Promise<ChatOrderOption[]>
  disabled?: boolean
  placeholder?: string
}) {
  const [open, setOpen] = useState(false)
  const [q, setQ] = useState('')
  const [options, setOptions] = useState<ChatOrderOption[]>([])
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (!open) return
    let cancelled = false
    setLoading(true)
    const timer = setTimeout(() => {
      search(q)
        .then((rows) => !cancelled && setOptions(rows))
        .catch(() => !cancelled && setOptions([]))
        .finally(() => !cancelled && setLoading(false))
    }, 250)
    return () => {
      cancelled = true
      clearTimeout(timer)
    }
  }, [open, q, search])

  return (
    <div className='flex items-center gap-1'>
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button type='button' variant='outline' role='combobox' disabled={disabled} className='w-full justify-between font-normal'>
            <span className={cn('truncate', !value && 'text-muted-foreground')}>{value ? `Order ${value.order_number}` : placeholder}</span>
            <ChevronsUpDown className='size-4 shrink-0 opacity-50' />
          </Button>
        </PopoverTrigger>
        <PopoverContent className='w-(--radix-popover-trigger-width) p-0' align='start'>
          <Command shouldFilter={false}>
            <CommandInput placeholder='Search order number…' value={q} onValueChange={setQ} />
            <CommandList>
              {loading ? (
                <div className='flex justify-center py-4'>
                  <Loader2 className='size-4 animate-spin text-muted-foreground' />
                </div>
              ) : (
                <>
                  <CommandEmpty>No orders found.</CommandEmpty>
                  <CommandGroup>
                    {options.map((order) => (
                      <CommandItem
                        key={order.id}
                        value={String(order.id)}
                        onSelect={() => {
                          onChange({ id: order.id, order_number: order.order_number })
                          setOpen(false)
                        }}
                      >
                        <Check className={cn('size-4', value?.id === order.id ? 'opacity-100' : 'opacity-0')} />
                        <span className='flex-1 truncate font-medium'>{order.order_number}</span>
                        <span className='text-xs text-muted-foreground'>
                          {order.created_at ? format(new Date(order.created_at), 'd MMM') : ''}
                        </span>
                      </CommandItem>
                    ))}
                  </CommandGroup>
                </>
              )}
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>
      {value && !disabled && (
        <Button type='button' variant='ghost' size='icon' className='size-9 shrink-0' onClick={() => onChange(null)} aria-label='Remove order'>
          <X className='size-4' />
        </Button>
      )}
    </div>
  )
}

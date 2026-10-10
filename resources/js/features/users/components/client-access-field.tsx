import { useMemo, useState } from 'react'
import { Search } from 'lucide-react'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { ScrollArea } from '@/components/ui/scroll-area'

export interface AssignableClient {
  id: number
  name: string
  code: string
}

export type ClientAccessMode = 'all' | 'dropshippers' | 'fulfilment' | 'assigned'

/** Every choice, in the order it is offered. */
export const CLIENT_ACCESS_MODES: ClientAccessMode[] = ['all', 'dropshippers', 'fulfilment', 'assigned']

const OPTIONS: Record<ClientAccessMode, { label: string; hint: string }> = {
  all: { label: 'All clients', hint: 'Sees every client, now and in future.' },
  dropshippers: { label: 'All dropshippers', hint: 'Sees every dropshipper, including ones added later.' },
  fulfilment: { label: 'All fulfilment clients', hint: 'Sees every fulfilment client, including ones added later.' },
  assigned: {
    label: 'Selected clients only',
    hint: 'Sees only these clients and their orders, shipments, inventory, payments and chats.',
  },
}

interface ClientAccessFieldProps {
  mode: ClientAccessMode
  clientIds: number[]
  onChange: (mode: ClientAccessMode, clientIds: number[]) => void
  clients: AssignableClient[]
  /** The choices this person may hand out: someone limited cannot give more than they handle. */
  modes: ClientAccessMode[]
  error?: string
}

/**
 * Which clients a team member handles: every client, every client of one
 * type, or a chosen few. Anything short of all, and they only see those
 * clients' orders, shipments, inventory, payments and chats.
 */
export function ClientAccessField({ mode, clientIds, onChange, clients, modes, error }: ClientAccessFieldProps) {
  const [search, setSearch] = useState('')
  const selected = useMemo(() => new Set(clientIds), [clientIds])

  const term = search.trim().toLowerCase()
  const shown = term
    ? clients.filter((c) => c.name.toLowerCase().includes(term) || c.code.toLowerCase().includes(term))
    : clients

  const toggle = (id: number) =>
    onChange('assigned', selected.has(id) ? clientIds.filter((c) => c !== id) : [...clientIds, id])

  return (
    <div className='space-y-3'>
      <Label>Clients</Label>

      <RadioGroup
        value={mode}
        onValueChange={(value) => onChange(value as ClientAccessMode, clientIds)}
        className='gap-2 sm:grid-cols-2'
      >
        {CLIENT_ACCESS_MODES.filter((value) => modes.includes(value)).map((value) => (
          <label key={value} className='flex cursor-pointer items-start gap-2.5 rounded-md border p-2.5'>
            <RadioGroupItem value={value} className='mt-0.5' />
            <span>
              <span className='block text-sm font-medium'>{OPTIONS[value].label}</span>
              <span className='block text-xs text-muted-foreground'>{OPTIONS[value].hint}</span>
            </span>
          </label>
        ))}
      </RadioGroup>

      {mode === 'assigned' && (
        <div className='space-y-2'>
          <div className='flex items-center gap-2'>
            <div className='relative flex-1'>
              <Search className='pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground' />
              <Input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder='Search clients…'
                className='h-8 ps-8'
              />
            </div>
            <span className='text-xs tabular-nums text-muted-foreground'>{clientIds.length} selected</span>
          </div>

          <ScrollArea className='h-48 rounded-md border p-2'>
            {shown.length === 0 ? (
              <p className='py-6 text-center text-sm text-muted-foreground'>
                {clients.length === 0 ? 'There are no clients to assign yet.' : 'No client matches.'}
              </p>
            ) : (
              <div className='space-y-0.5'>
                {shown.map((client) => (
                  <label
                    key={client.id}
                    className='flex cursor-pointer items-center gap-2 rounded px-1.5 py-1 hover:bg-accent/60'
                  >
                    <Checkbox checked={selected.has(client.id)} onCheckedChange={() => toggle(client.id)} />
                    <span className='min-w-0 flex-1 truncate text-sm'>{client.name}</span>
                    <span className='text-xs text-muted-foreground'>{client.code}</span>
                  </label>
                ))}
              </div>
            )}
          </ScrollArea>

          {clientIds.length === 0 && (
            <p className='text-xs text-amber-700 dark:text-amber-400'>
              With no client selected this person will see no clients and no orders.
            </p>
          )}
        </div>
      )}

      {error && <p className='text-sm text-destructive'>{error}</p>}
    </div>
  )
}

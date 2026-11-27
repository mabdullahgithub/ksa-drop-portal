import { useEffect, useState } from 'react'
import axios from 'axios'
import { toast } from 'sonner'
import { Check, ChevronsUpDown, Loader2 } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { errorMessage, teamChats, uuid } from '../api'
import { CATEGORY_LABELS, MAX_BODY, PRIORITY_META } from '../constants'
import type { ChatCategory, ChatConversation, ChatPriority } from '../types'
import { OrderPicker, type PickedOrder } from './shared/order-picker'

interface ClientOption {
  id: number
  company_name: string
  short_id: string | null
}

/**
 * The team opens a conversation with a client (`start chats`): a stock
 * warning, a question about an order. It is the opener's from the start and
 * waits on the client.
 */
export function NewChat({
  open,
  onOpenChange,
  onCreated,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  onCreated: (conversation: ChatConversation) => void
}) {
  const [client, setClient] = useState<ClientOption | null>(null)
  const [subject, setSubject] = useState('')
  const [category, setCategory] = useState<ChatCategory>('general')
  const [priority, setPriority] = useState<ChatPriority>('normal')
  const [order, setOrder] = useState<PickedOrder | null>(null)
  const [body, setBody] = useState('')
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (open) return
    setClient(null)
    setSubject('')
    setCategory('general')
    setPriority('normal')
    setOrder(null)
    setBody('')
  }, [open])

  useEffect(() => setOrder(null), [client?.id])

  const ready = client && subject.trim() && body.trim()

  const submit = async () => {
    if (!ready) return
    setSaving(true)
    try {
      const conversation = await teamChats.start({
        client_id: client.id,
        subject: subject.trim(),
        category,
        priority,
        order_id: order?.id,
        body: body.trim(),
        files: [],
        clientUuid: uuid(),
      })
      toast.success(`Chat started with ${client.company_name}`)
      onOpenChange(false)
      onCreated(conversation)
    } catch (error) {
      toast.error(errorMessage(error, 'Could not start the chat'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='sm:max-w-lg'>
        <DialogHeader>
          <DialogTitle>New chat</DialogTitle>
          <DialogDescription>Start a conversation with a client. They see it on their Support page and are notified.</DialogDescription>
        </DialogHeader>

        <div className='grid gap-4'>
          <div className='grid gap-1.5'>
            <Label>Client</Label>
            <ClientPicker value={client} onChange={setClient} />
          </div>

          <div className='grid gap-1.5'>
            <Label htmlFor='new-chat-subject'>Subject</Label>
            <Input id='new-chat-subject' value={subject} maxLength={160} onChange={(e) => setSubject(e.target.value)} placeholder='e.g. Low stock on your best seller' />
          </div>

          <div className='grid grid-cols-2 gap-3'>
            <div className='grid gap-1.5'>
              <Label>Category</Label>
              <Select value={category} onValueChange={(v) => setCategory(v as ChatCategory)}>
                <SelectTrigger className='w-full'>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {(Object.keys(CATEGORY_LABELS) as ChatCategory[]).map((key) => (
                    <SelectItem key={key} value={key}>
                      {CATEGORY_LABELS[key]}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className='grid gap-1.5'>
              <Label>Priority</Label>
              <Select value={priority} onValueChange={(v) => setPriority(v as ChatPriority)}>
                <SelectTrigger className='w-full'>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {(Object.keys(PRIORITY_META) as ChatPriority[]).map((key) => (
                    <SelectItem key={key} value={key}>
                      {PRIORITY_META[key].label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className='grid gap-1.5'>
            <Label>Order</Label>
            <OrderPicker
              value={order}
              onChange={setOrder}
              disabled={!client}
              placeholder={client ? 'Link an order (optional)' : 'Pick a client first'}
              search={(q) => teamChats.orders(client!.id, q)}
            />
          </div>

          <div className='grid gap-1.5'>
            <Label htmlFor='new-chat-body'>Message</Label>
            <Textarea id='new-chat-body' value={body} maxLength={MAX_BODY} rows={4} onChange={(e) => setBody(e.target.value)} placeholder='Write the first message…' />
          </div>
        </div>

        <DialogFooter>
          <Button variant='outline' onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={submit} disabled={!ready || saving}>
            {saving && <Loader2 className='size-4 animate-spin' />}
            Start chat
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function ClientPicker({ value, onChange }: { value: ClientOption | null; onChange: (client: ClientOption) => void }) {
  const [open, setOpen] = useState(false)
  const [q, setQ] = useState('')
  const [options, setOptions] = useState<ClientOption[]>([])
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (!open) return
    let cancelled = false
    setLoading(true)
    const timer = setTimeout(() => {
      axios
        .get('/api/chats/lookup/clients', { params: { q } })
        .then(({ data }) => !cancelled && setOptions(data.data))
        .catch(() => !cancelled && setOptions([]))
        .finally(() => !cancelled && setLoading(false))
    }, 250)
    return () => {
      cancelled = true
      clearTimeout(timer)
    }
  }, [open, q])

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button type='button' variant='outline' role='combobox' className='w-full justify-between font-normal'>
          <span className={cn('truncate', !value && 'text-muted-foreground')}>{value ? value.company_name : 'Search clients…'}</span>
          <ChevronsUpDown className='size-4 shrink-0 opacity-50' />
        </Button>
      </PopoverTrigger>
      <PopoverContent className='w-(--radix-popover-trigger-width) p-0' align='start'>
        <Command shouldFilter={false}>
          <CommandInput placeholder='Company name or ID…' value={q} onValueChange={setQ} />
          <CommandList>
            {loading ? (
              <div className='flex justify-center py-4'>
                <Loader2 className='size-4 animate-spin text-muted-foreground' />
              </div>
            ) : (
              <>
                <CommandEmpty>No clients found.</CommandEmpty>
                <CommandGroup>
                  {options.map((client) => (
                    <CommandItem
                      key={client.id}
                      value={String(client.id)}
                      onSelect={() => {
                        onChange(client)
                        setOpen(false)
                      }}
                    >
                      <Check className={cn('size-4', value?.id === client.id ? 'opacity-100' : 'opacity-0')} />
                      <span className='flex-1 truncate'>{client.company_name}</span>
                      {client.short_id && <span className='text-xs text-muted-foreground'>{client.short_id}</span>}
                    </CommandItem>
                  ))}
                </CommandGroup>
              </>
            )}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}

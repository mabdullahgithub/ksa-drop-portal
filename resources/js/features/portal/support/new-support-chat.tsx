import { useEffect, useMemo, useRef, useState } from 'react'
import { usePage } from '@inertiajs/react'
import { toast } from 'sonner'
import {
  CircleHelp,
  CreditCard,
  FileText,
  Loader2,
  Package,
  Paperclip,
  Truck,
  User,
  Warehouse,
  Wrench,
  X,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { errorMessage, portalChats, portalItemSources, uuid } from '@/features/chats/api'
import { ItemButtons } from '@/features/chats/components/shared/item-buttons'
import { ItemPicker } from '@/features/chats/components/shared/item-picker'
import { ItemChip } from '@/features/chats/components/shared/shared-items'
import type { PageProps } from '@/types'
import { ACCEPT_FILES, CATEGORY_LABELS, MAX_BODY, MAX_FILE_MB, MAX_FILES } from '@/features/chats/constants'
import { formatBytes } from '@/features/chats/components/shared/chat-bits'
import type { ChatCategory, ChatConversation, ChatSharedItem } from '@/features/chats/types'

const CATEGORY_ICONS: Record<ChatCategory, React.ElementType> = {
  order_issue: Package,
  shipment: Truck,
  inventory: Warehouse,
  billing: CreditCard,
  account: User,
  technical: Wrench,
  general: CircleHelp,
}

export interface SupportDraft {
  category?: ChatCategory
  order?: { id: number; order_number: string } | null
  subject?: string
}

/** The client starts a conversation: what it is about, the message, and any orders or products with it. */
export function NewSupportChat({
  open,
  onOpenChange,
  draft,
  onCreated,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  draft: SupportDraft | null
  onCreated: (conversation: ChatConversation) => void
}) {
  const [category, setCategory] = useState<ChatCategory>('general')
  const [subject, setSubject] = useState('')
  const [body, setBody] = useState('')
  const [files, setFiles] = useState<File[]>([])
  const [items, setItems] = useState<ChatSharedItem[]>([])
  const [picking, setPicking] = useState<ChatSharedItem['type'] | null>(null)
  // The same rule as the server's: fulfilment clients with My Inventory on.
  const { auth } = usePage<PageProps>().props
  const hasInventory = !!auth.client?.is_fulfilment && !!auth.portal_features?.includes('inventory')
  const itemSources = useMemo(
    () => ({ orders: portalItemSources.orders, products: hasInventory ? portalItemSources.products : undefined }),
    [hasInventory]
  )
  const [saving, setSaving] = useState(false)
  const fileInput = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (!open) return
    setCategory(draft?.category ?? 'general')
    setSubject(draft?.subject ?? (draft?.order ? `Issue with order ${draft.order.order_number}` : ''))
    setBody('')
    setFiles([])
    // Opened from an order ("Report an issue"): it starts out attached.
    setItems(draft?.order ? [{ type: 'order', id: draft.order.id, title: draft.order.order_number }] : [])
  }, [open, draft])

  const ready = subject.trim() && (body.trim() || files.length > 0 || items.length > 0)

  const addFiles = (list: FileList | null) => {
    if (!list) return
    const ok = Array.from(list).filter((f) => f.size <= MAX_FILE_MB * 1024 * 1024)
    if (ok.length < list.length) toast.error(`Files must be under ${MAX_FILE_MB} MB`)
    setFiles((current) => [...current, ...ok].slice(0, MAX_FILES))
  }

  const submit = async () => {
    if (!ready) return
    setSaving(true)
    try {
      const conversation = await portalChats.start({
        subject: subject.trim(),
        category,
        // The first order attached is the one the chat is linked to.
        order_id: items.find((item) => item.type === 'order')?.id,
        body: body.trim(),
        files,
        // Ids only; sent as orders[] and products[].
        items,
        clientUuid: uuid(),
      })
      toast.success('Your message was sent. We will reply here shortly.')
      onOpenChange(false)
      onCreated(conversation)
    } catch (error) {
      toast.error(errorMessage(error, 'Could not send your message'))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='max-h-[92svh] overflow-y-auto sm:max-w-xl'>
        <DialogHeader>
          <DialogTitle>Contact support</DialogTitle>
          <DialogDescription>Tell us what is going on. Our team replies right here, and you are notified when we do.</DialogDescription>
        </DialogHeader>

        <div className='grid gap-4'>
          <div className='grid gap-1.5'>
            <Label>What is it about?</Label>
            <div className='grid grid-cols-2 gap-1.5 sm:grid-cols-4'>
              {(Object.keys(CATEGORY_LABELS) as ChatCategory[]).map((key) => {
                const Icon = CATEGORY_ICONS[key]
                return (
                  <button
                    key={key}
                    type='button'
                    onClick={() => setCategory(key)}
                    className={cn(
                      'flex flex-col items-center gap-1 rounded-lg border px-2 py-2.5 text-center text-xs font-medium transition-colors',
                      category === key ? 'border-primary bg-primary/5 text-primary' : 'hover:bg-muted'
                    )}
                    aria-pressed={category === key}
                  >
                    <Icon className='size-4' />
                    {CATEGORY_LABELS[key]}
                  </button>
                )
              })}
            </div>
          </div>

          <div className='grid gap-1.5'>
            <Label htmlFor='support-subject'>Subject</Label>
            <Input id='support-subject' value={subject} maxLength={160} onChange={(e) => setSubject(e.target.value)} placeholder='A short summary' />
          </div>

          <div className='grid gap-1.5'>
            <Label htmlFor='support-body'>Message</Label>
            <Textarea id='support-body' rows={5} maxLength={MAX_BODY} value={body} onChange={(e) => setBody(e.target.value)} placeholder='Describe the problem or your question…' />
          </div>

          <div className='grid gap-1.5'>
            <div className='flex flex-wrap gap-2'>
              {files.map((file, index) => (
                <span key={`${file.name}-${index}`} className='inline-flex max-w-56 items-center gap-1.5 rounded-md border px-2 py-1 text-xs'>
                  <FileText className='size-3.5 shrink-0 text-muted-foreground' />
                  <span className='truncate'>{file.name}</span>
                  <span className='text-muted-foreground'>{formatBytes(file.size)}</span>
                  <button type='button' onClick={() => setFiles(files.filter((_, i) => i !== index))} aria-label={`Remove ${file.name}`}>
                    <X className='size-3' />
                  </button>
                </span>
              ))}
            </div>
            {items.length > 0 && (
              <div className='flex flex-wrap gap-2'>
                {items.map((item) => (
                  <ItemChip
                    key={`${item.type}-${item.id}`}
                    item={item}
                    onRemove={() => setItems((current) => current.filter((x) => !(x.type === item.type && x.id === item.id)))}
                  />
                ))}
              </div>
            )}
            <div className='flex flex-wrap gap-2'>
              {files.length < MAX_FILES && (
                <Button type='button' variant='outline' size='sm' onClick={() => fileInput.current?.click()}>
                  <Paperclip className='size-4' /> Attach photos or files
                </Button>
              )}
              <ItemButtons sources={itemSources} onPick={setPicking} />
            </div>
            <ItemPicker kind={picking} onClose={() => setPicking(null)} sources={itemSources} selected={items} onAttach={setItems} />
            <input ref={fileInput} type='file' multiple accept={ACCEPT_FILES} className='hidden' onChange={(e) => { addFiles(e.target.files); e.target.value = '' }} />
          </div>
        </div>

        <DialogFooter>
          <Button variant='outline' onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={submit} disabled={!ready || saving}>
            {saving && <Loader2 className='size-4 animate-spin' />}
            Send
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

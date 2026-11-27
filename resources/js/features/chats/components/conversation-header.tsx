import { useEffect, useState } from 'react'
import axios from 'axios'
import { toast } from 'sonner'
import { ArrowLeft, ChevronDown, Flag, Link2, MoreVertical, PanelRightClose, PanelRightOpen, Tag } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { errorMessage, teamChats } from '../api'
import { CATEGORY_LABELS, PRIORITY_META, STATUS_META, TEAM_MOVES } from '../constants'
import type { ChatCategory, ChatConversation, ChatPriority, ChatStatus, ChatTeamDetail } from '../types'
import { StatusBadge } from './shared/chat-bits'
import { OrderPicker, type PickedOrder } from './shared/order-picker'

/**
 * Above the thread: who and what about, and every action on the chat, each
 * shown only to those whose permission allows it.
 */
export function ConversationHeader({
  detail,
  onBack,
  onChanged,
  contextOpen,
  onToggleContext,
}: {
  detail: ChatTeamDetail
  onBack: () => void
  onChanged: (conversation: ChatConversation) => void
  contextOpen: boolean
  onToggleContext: () => void
}) {
  const c = detail.conversation
  const can = detail.permissions
  const [busy, setBusy] = useState(false)
  const [linkOpen, setLinkOpen] = useState(false)

  const run = async (request: () => Promise<{ data: { conversation: ChatConversation } }>, done?: string) => {
    setBusy(true)
    try {
      const { data } = await request()
      onChanged(data.conversation)
      if (done) toast.success(done)
    } catch (error) {
      toast.error(errorMessage(error, 'That did not work'))
    } finally {
      setBusy(false)
    }
  }

  const setStatus = (status: ChatStatus) =>
    run(() => axios.patch(`/api/chats/${c.id}/status`, { status }), `Marked ${STATUS_META[status].label.toLowerCase()}`)
  const update = (patch: Partial<{ priority: ChatPriority; category: ChatCategory; order_id: number | null }>) =>
    run(() => axios.patch(`/api/chats/${c.id}`, patch))

  return (
    <div className='flex items-center gap-2 border-b px-3 py-2.5'>
      <Button size='icon' variant='ghost' className='size-8 shrink-0 md:hidden' onClick={onBack} aria-label='Back to the inbox'>
        <ArrowLeft className='size-4 rtl:rotate-180' />
      </Button>

      <div className='min-w-0 flex-1'>
        <div className='truncate text-sm font-semibold'>{c.subject}</div>
        <div className='flex items-center gap-1.5 truncate text-xs text-muted-foreground'>
          <span className='truncate'>{c.client?.company_name}</span>
          <span>·</span>
          <span>{c.reference}</span>
          <span className='hidden sm:inline'>· {CATEGORY_LABELS[c.category]}</span>
          {c.order && <span className='hidden lg:inline'>· Order {c.order.order_number}</span>}
        </div>
      </div>

      {/* Status */}
      {can.status ? (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant='ghost' size='sm' className='h-8 gap-1 px-1.5' disabled={busy}>
              <StatusBadge status={c.status} side='team' />
              <ChevronDown className='size-3.5 text-muted-foreground' />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align='end'>
            <DropdownMenuLabel>Move to</DropdownMenuLabel>
            {TEAM_MOVES[c.status].map((status) => (
              <DropdownMenuItem key={status} onSelect={() => setStatus(status)}>
                <span className={cn('size-2 rounded-full', STATUS_META[status].dot)} />
                {status === 'open' && (c.status === 'resolved' || c.status === 'closed') ? 'Reopen' : STATUS_META[status].label}
              </DropdownMenuItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>
      ) : (
        <StatusBadge status={c.status} side='team' className='hidden sm:inline-flex' />
      )}

      {/* Priority */}
      {can.edit && c.priority ? (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant='ghost' size='sm' className='h-8 gap-1 px-1.5' disabled={busy} aria-label={`Priority: ${PRIORITY_META[c.priority].label}`}>
              <PriorityLabel priority={c.priority} labelClassName='hidden sm:inline' />
              <ChevronDown className='size-3.5 text-muted-foreground' />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align='end' className='w-40'>
            <DropdownMenuLabel>Priority</DropdownMenuLabel>
            <DropdownMenuRadioGroup value={c.priority} onValueChange={(v) => update({ priority: v as ChatPriority })}>
              {(Object.keys(PRIORITY_META) as ChatPriority[]).map((p) => (
                <DropdownMenuRadioItem key={p} value={p}>
                  <span className={PRIORITY_META[p].className}>{PRIORITY_META[p].label}</span>
                </DropdownMenuRadioItem>
              ))}
            </DropdownMenuRadioGroup>
          </DropdownMenuContent>
        </DropdownMenu>
      ) : (
        c.priority && c.priority !== 'normal' && <PriorityLabel priority={c.priority} className='hidden sm:inline-flex' />
      )}

      <Button
        size='icon'
        variant='ghost'
        className='hidden size-8 shrink-0 xl:inline-flex'
        onClick={onToggleContext}
        aria-label={contextOpen ? 'Hide details' : 'Show details'}
      >
        {contextOpen ? <PanelRightClose className='size-4' /> : <PanelRightOpen className='size-4' />}
      </Button>

      {can.edit && (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button size='icon' variant='ghost' className='size-8 shrink-0' aria-label='More actions'>
              <MoreVertical className='size-4' />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align='end' className='w-52'>
            <DropdownMenuSub>
              <DropdownMenuSubTrigger className='gap-2'>
                <Tag className='size-4 text-muted-foreground' /> Category
              </DropdownMenuSubTrigger>
              <DropdownMenuSubContent>
                <DropdownMenuRadioGroup value={c.category} onValueChange={(v) => update({ category: v as ChatCategory })}>
                  {(Object.keys(CATEGORY_LABELS) as ChatCategory[]).map((k) => (
                    <DropdownMenuRadioItem key={k} value={k}>
                      {CATEGORY_LABELS[k]}
                    </DropdownMenuRadioItem>
                  ))}
                </DropdownMenuRadioGroup>
              </DropdownMenuSubContent>
            </DropdownMenuSub>
            <DropdownMenuItem onSelect={() => setLinkOpen(true)}>
              <Link2 className='size-4' /> {c.order ? 'Change linked order' : 'Link an order'}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      )}

      <LinkOrderDialog
        open={linkOpen}
        onOpenChange={setLinkOpen}
        clientId={c.client?.id ?? 0}
        current={c.order ? { id: c.order.id, order_number: c.order.order_number } : null}
        onSave={async (order) => {
          await update({ order_id: order?.id ?? null })
          setLinkOpen(false)
        }}
      />
    </div>
  )
}

function PriorityLabel({
  priority,
  className,
  labelClassName,
}: {
  priority: ChatPriority
  className?: string
  labelClassName?: string
}) {
  return (
    <span className={cn('inline-flex shrink-0 items-center gap-1.5 text-xs', PRIORITY_META[priority].className, className)}>
      <Flag className='size-3' />
      <span className={labelClassName}>{PRIORITY_META[priority].label}</span>
    </span>
  )
}

function LinkOrderDialog({
  open,
  onOpenChange,
  clientId,
  current,
  onSave,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  clientId: number
  current: PickedOrder | null
  onSave: (order: PickedOrder | null) => Promise<void>
}) {
  const [order, setOrder] = useState<PickedOrder | null>(current)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (open) setOrder(current)
  }, [open, current])

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='sm:max-w-md'>
        <DialogHeader>
          <DialogTitle>Linked order</DialogTitle>
          <DialogDescription>Link the order this chat is about. Only this client&apos;s orders are listed.</DialogDescription>
        </DialogHeader>
        <OrderPicker value={order} onChange={setOrder} search={(q) => teamChats.orders(clientId, q)} />
        <DialogFooter>
          <Button variant='outline' onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button
            disabled={saving || order?.id === current?.id}
            onClick={async () => {
              setSaving(true)
              await onSave(order)
              setSaving(false)
            }}
          >
            Save
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

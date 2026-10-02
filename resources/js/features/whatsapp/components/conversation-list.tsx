import { formatDistanceToNowStrict, isToday, format } from 'date-fns'
import { Loader2, MessageCircleOff } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { ScrollArea } from '@/components/ui/scroll-area'
import { WHATSAPP_STATUS_META } from '@/features/orders/data/call-status'
import { Badge } from '@/components/ui/badge'
import { RowTicks } from './delivery-ticks'
import type { ConversationSummary } from '../types'

/**
 * Radix's ScrollArea viewport wraps children in a `display: table` div, which
 * sizes to its content — so `w-full` and `truncate` inside measure against that
 * expanded width, overflow the pane, and clip with no ellipsis. Forcing the
 * wrapper back to `block` makes children respect the container width.
 */
const VIEWPORT_BLOCK = '[&>[data-slot=scroll-area-viewport]>div]:!block'


function initials(name: string | null, fallback: string) {
  const source = (name ?? '').trim() || fallback
  return source
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}

/** Collapse whitespace and cap the preview — the row shows ~40 chars at this width. */
function preview(body: string | null) {
  if (!body) return '—'
  const flat = body.replace(/\s+/g, ' ').trim()
  return flat.length > 80 ? `${flat.slice(0, 80)}…` : flat
}

/** Relative for anything recent, absolute once it stops being useful. */
function shortTime(value: string | null) {
  if (!value) return ''
  const date = new Date(value)
  if (isToday(date)) return format(date, 'HH:mm')
  return formatDistanceToNowStrict(date, { addSuffix: false })
}

export function ConversationList({
  conversations,
  selectedId,
  onSelect,
  loading,
}: {
  conversations: ConversationSummary[]
  selectedId: number | null
  onSelect: (conversation: ConversationSummary) => void
  loading: boolean
}) {
  if (loading && conversations.length === 0) {
    return (
      <div className='flex flex-1 items-center justify-center py-12'>
        <Loader2 className='h-5 w-5 animate-spin text-muted-foreground' />
      </div>
    )
  }

  if (conversations.length === 0) {
    return (
      <div className='flex flex-1 flex-col items-center justify-center gap-2 px-6 py-12 text-center'>
        <MessageCircleOff className='h-8 w-8 text-muted-foreground/50' />
        <p className='text-sm font-medium'>No conversations</p>
        <p className='text-xs text-muted-foreground'>
          A conversation starts when an agent marks a call &ldquo;No Answer&rdquo;.
        </p>
      </div>
    )
  }

  return (
    <ScrollArea className={`min-h-0 flex-1 ${VIEWPORT_BLOCK}`}>
      <div className='divide-y'>
        {conversations.map((c) => {
          const meta = WHATSAPP_STATUS_META[c.whatsapp_status]
          const isSelected = c.id === selectedId
          const outbound = c.last_message_direction === 'outbound'

          return (
            <button
              key={c.id}
              type='button'
              onClick={() => onSelect(c)}
              className={cn(
                'flex w-full min-w-0 items-start gap-3 px-3 py-3 text-start transition-colors',
                'hover:bg-muted/60 focus-visible:bg-muted/60 focus-visible:outline-none',
                isSelected && 'bg-muted'
              )}
            >
              <Avatar className='h-9 w-9 shrink-0'>
                <AvatarFallback className='bg-emerald-100 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'>
                  {initials(c.customer_name, c.order_number)}
                </AvatarFallback>
              </Avatar>

              <div className='min-w-0 flex-1'>
                <div className='flex items-baseline justify-between gap-2'>
                  <span className='truncate text-sm font-medium'>
                    {c.customer_name || 'Unknown customer'}
                  </span>
                  <span className='shrink-0 text-[11px] text-muted-foreground'>
                    {shortTime(c.last_message_at)}
                  </span>
                </div>

                <div className='mt-0.5 flex min-w-0 items-center gap-1.5'>
                  {outbound && <RowTicks status={c.last_outbound_status} />}
                  <span className='min-w-0 flex-1 truncate text-xs text-muted-foreground'>
                    {preview(c.last_message)}
                  </span>
                </div>

                <div className='mt-1.5 flex flex-wrap items-center gap-1.5'>
                  <span className='font-mono text-[10px] text-muted-foreground'>
                    {c.order_number}
                  </span>
                  <Badge
                    variant='outline'
                    className={cn('h-4 px-1 text-[10px] font-normal', meta?.className)}
                  >
                    {meta?.label ?? c.whatsapp_status}
                  </Badge>
                </div>
              </div>

              {/* Unread dot: the customer said something nobody has acted on. */}
              {c.needs_attention && (
                <span
                  className='mt-1.5 h-2 w-2 shrink-0 rounded-full bg-emerald-500'
                  title='Awaiting agent action'
                />
              )}
            </button>
          )
        })}
      </div>
    </ScrollArea>
  )
}

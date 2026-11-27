import { format, isThisYear, isToday, isYesterday } from 'date-fns'
import { Loader2 } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import type { ChatConversation, ChatSide } from '../../types'
import { StatusDot } from './chat-bits'

export function listTime(value: string | null) {
  if (!value) return ''
  const date = new Date(value)
  if (isToday(date)) return format(date, 'h:mm a')
  if (isYesterday(date)) return 'Yesterday'
  return format(date, isThisYear(date) ? 'd MMM' : 'd MMM yyyy')
}

/**
 * The conversations down the left, kept quiet: who (or what about), the
 * last line, when, and a dot for status. Unread is bold with a count.
 */
export function ConversationList({
  side,
  items,
  selectedId,
  onSelect,
  loading,
  hasMore,
  loadingMore,
  onMore,
  empty,
  outside,
}: {
  side: ChatSide
  items: ChatConversation[]
  selectedId: number | null
  onSelect: (conversation: ChatConversation) => void
  loading: boolean
  hasMore: boolean
  loadingMore: boolean
  onMore: () => void
  empty: React.ReactNode
  /** The open chat when the current filters don't include it. */
  outside?: ChatConversation | null
}) {
  if (loading) {
    return (
      <div className='space-y-3 p-3'>
        {Array.from({ length: 6 }).map((_, i) => (
          <div key={i} className='space-y-1.5 px-1'>
            <Skeleton className='h-3 w-1/2' />
            <Skeleton className='h-3 w-5/6' />
          </div>
        ))}
      </div>
    )
  }

  if (items.length === 0 && !outside) return <div className='flex flex-1 items-center justify-center p-4'>{empty}</div>

  return (
    <div className='flex min-h-0 flex-1 flex-col overflow-y-auto p-1.5'>
      {outside && (
        <div className='mb-1.5 border-b pb-1.5'>
          <p className='px-3 pb-1 text-[10px] text-muted-foreground'>Open now · not in this filter</p>
          <Row side={side} conversation={outside} selected={outside.id === selectedId} onSelect={onSelect} />
        </div>
      )}
      {items.length === 0 && <div className='flex flex-1 items-center justify-center p-4'>{empty}</div>}
      <ul className='space-y-px'>
        {items.map((conversation) => (
          <li key={conversation.id}>
            <Row side={side} conversation={conversation} selected={conversation.id === selectedId} onSelect={onSelect} />
          </li>
        ))}
      </ul>
      {hasMore && (
        <div className='flex justify-center py-2'>
          <Button variant='ghost' size='sm' className='h-7 text-xs text-muted-foreground' onClick={onMore} disabled={loadingMore}>
            {loadingMore ? <Loader2 className='size-3.5 animate-spin' /> : 'Load more'}
          </Button>
        </div>
      )}
    </div>
  )
}

function Row({
  side,
  conversation: c,
  selected,
  onSelect,
}: {
  side: ChatSide
  conversation: ChatConversation
  selected: boolean
  onSelect: (conversation: ChatConversation) => void
}) {
  const unread = c.unread_count > 0
  const last = c.last_message
  const ours = last?.sender_type === (side === 'team' ? 'staff' : 'client')
  const prefix = last?.kind === 'note' ? 'Note: ' : ours ? 'You: ' : ''
  const preview = !last
    ? ''
    : last.voice
      ? 'Voice message'
      : last.shared
        ? last.shared === 'order'
          ? 'Shared an order'
          : 'Shared a product'
        : last.attachment_only
          ? 'Attachment'
          : last.body
  const urgent = side === 'team' && (c.priority === 'urgent' || c.priority === 'high')

  return (
    <button
      type='button'
      onClick={() => onSelect(c)}
      className={cn(
        'flex w-full flex-col gap-0.5 rounded-md px-3 py-2 text-start transition-colors hover:bg-muted/60',
        selected && 'bg-muted'
      )}
      aria-current={selected || undefined}
    >
      <div className='flex items-center gap-2'>
        <StatusDot status={c.status} className='size-1.5' />
        <span className={cn('min-w-0 flex-1 truncate text-[13px]', unread ? 'font-semibold' : 'font-medium')}>
          {side === 'team' ? (c.client?.company_name ?? 'Unknown client') : c.subject}
        </span>
        {urgent && <span className='size-1.5 shrink-0 rounded-full bg-red-500' title={c.priority === 'urgent' ? 'Urgent' : 'High priority'} />}
        <span className='shrink-0 text-[11px] text-muted-foreground'>{listTime(c.last_message_at)}</span>
      </div>

      {side === 'team' && <div className='truncate ps-3.5 text-xs text-foreground/80'>{c.subject}</div>}

      <div className='flex items-center gap-2 ps-3.5'>
        <p className={cn('min-w-0 flex-1 truncate text-xs', unread ? 'text-foreground' : 'text-muted-foreground')}>
          {prefix}
          {preview}
        </p>
        {unread && (
          <span className='shrink-0 rounded-full bg-primary px-1.5 text-[10px] leading-4 font-medium text-primary-foreground'>
            {c.unread_count > 99 ? '99+' : c.unread_count}
          </span>
        )}
      </div>
    </button>
  )
}

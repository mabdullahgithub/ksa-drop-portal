import { Fragment, useEffect, useLayoutEffect, useRef } from 'react'
import { format, isToday, isYesterday } from 'date-fns'
import { AlertCircle, CheckCheck, Clock, Loader2, Lock, MessageSquareDashed, RotateCw, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Attachments, EventLine, MessageText } from './chat-bits'
import { SharedItemCards } from './shared-items'
import type { ChatMessage, ChatSide } from '../../types'

function dayLabel(value: string) {
  const date = new Date(value)
  if (isToday(date)) return 'Today'
  if (isYesterday(date)) return 'Yesterday'
  return format(date, 'd MMMM yyyy')
}

/** Two messages that read as one run: same writer, same kind, same day. */
function sameWriter(a: ChatMessage | undefined, b: ChatMessage | undefined) {
  if (!a || !b) return false
  if (a.kind !== b.kind || a.sender_type !== b.sender_type || a.sender?.name !== b.sender?.name) return false
  return !a.created_at || !b.created_at || dayLabel(a.created_at) === dayLabel(b.created_at)
}

/** The time goes under a run's last bubble: before someone else, a status line or a five minute pause. */
function endsRun(message: ChatMessage, next: ChatMessage | undefined) {
  if (!next || next.kind === 'event' || !sameWriter(message, next)) return true
  return new Date(next.created_at ?? 0).getTime() - new Date(message.created_at ?? 0).getTime() > 5 * 60 * 1000
}

/**
 * The conversation, grouped by day. Ported from the template's chat: this
 * side's messages sit right on the brand colour, the other side's left on
 * muted; the team's internal notes are amber with a lock; events are a
 * quiet centred line.
 */
export function MessageThread({
  messages,
  side,
  loading,
  hasMore,
  loadingOlder,
  onLoadOlder,
  readUpTo,
  typing,
  onRetry,
  onDiscard,
  emptyText = 'No messages yet',
  meId,
}: {
  messages: ChatMessage[]
  side: ChatSide
  loading: boolean
  hasMore: boolean
  loadingOlder: boolean
  onLoadOlder: () => void
  /** The other side has read up to this message id. */
  readUpTo: number
  typing: string | null
  onRetry: (clientUuid: string) => void
  onDiscard: (clientUuid: string) => void
  emptyText?: string
  /** The signed-in person: their own messages carry no name. */
  meId: number
}) {
  const scroller = useRef<HTMLDivElement>(null)
  const stickToBottom = useRef(true)
  const prependAnchor = useRef<{ height: number; top: number } | null>(null)
  const firstId = messages[0]?.id

  const mineType = side === 'team' ? 'staff' : 'client'

  // Older messages went on top: keep the view where it was.
  useLayoutEffect(() => {
    const el = scroller.current
    if (el && prependAnchor.current) {
      el.scrollTop = el.scrollHeight - prependAnchor.current.height + prependAnchor.current.top
      prependAnchor.current = null
    }
  }, [firstId])

  // New at the bottom: follow it if the reader was at the bottom, or it is theirs.
  const last = messages[messages.length - 1]
  useEffect(() => {
    const el = scroller.current
    if (!el) return
    if (stickToBottom.current || last?.pending) el.scrollTop = el.scrollHeight
  }, [messages.length, last?.id, last?.pending, typing])

  const onScroll = () => {
    const el = scroller.current
    if (!el) return
    stickToBottom.current = el.scrollHeight - el.scrollTop - el.clientHeight < 80
    if (el.scrollTop < 60 && hasMore && !loadingOlder) {
      prependAnchor.current = { height: el.scrollHeight, top: el.scrollTop }
      onLoadOlder()
    }
  }

  if (loading) {
    return (
      <div className='flex flex-1 items-center justify-center'>
        <Loader2 className='size-5 animate-spin text-muted-foreground' />
      </div>
    )
  }

  // The newest of this side's messages the other side has read gets "Seen".
  const seenId = [...messages].reverse().find((m) => !m.pending && m.sender_type === mineType && m.kind === 'message' && m.id <= readUpTo)?.id

  return (
    <div ref={scroller} onScroll={onScroll} className='flex min-h-0 flex-1 flex-col overflow-y-auto px-3 py-4 sm:px-5'>
      {hasMore && (
        <div className='flex justify-center pb-2'>
          {loadingOlder ? (
            <Loader2 className='size-4 animate-spin text-muted-foreground' />
          ) : (
            <Button variant='ghost' size='sm' className='h-7 text-xs' onClick={onLoadOlder}>
              Load earlier messages
            </Button>
          )}
        </div>
      )}

      {messages.length === 0 ? (
        <div className='flex flex-1 flex-col items-center justify-center gap-2 text-center'>
          <MessageSquareDashed className='size-8 text-muted-foreground/50' />
          <p className='text-sm text-muted-foreground'>{emptyText}</p>
        </div>
      ) : (
        <div className='mt-auto flex flex-col gap-1'>
          {messages.map((message, index) => {
            const previous = messages[index - 1]
            // A status line in between does not make the same person new again.
            const previousMessage = messages.slice(0, index).findLast((m) => m.kind !== 'event')
            const day = message.created_at ? dayLabel(message.created_at) : null
            const newDay = day && (!previous?.created_at || dayLabel(previous.created_at) !== day)

            return (
              <Fragment key={message.client_uuid && message.pending ? message.client_uuid : message.id}>
                {newDay && (
                  <div className='my-4 flex items-center gap-3 text-[11px] text-muted-foreground'>
                    <span className='h-px flex-1 bg-border' />
                    {day}
                    <span className='h-px flex-1 bg-border' />
                  </div>
                )}
                {message.kind === 'event' ? (
                  <EventLine message={message} side={side} />
                ) : (
                  <Bubble
                    message={message}
                    side={side}
                    mine={message.sender_type === mineType}
                    first={!sameWriter(previousMessage, message)}
                    last={endsRun(message, messages[index + 1])}
                    own={message.sender?.id === meId}
                    seen={message.id === seenId}
                    onRetry={onRetry}
                    onDiscard={onDiscard}
                  />
                )}
              </Fragment>
            )
          })}
        </div>
      )}

      {typing && (
        <div className='mt-2 flex items-center gap-2 text-xs text-muted-foreground' aria-live='polite'>
          <span className='flex gap-0.5'>
            {[0, 150, 300].map((delay) => (
              <span key={delay} className='size-1.5 animate-bounce rounded-full bg-muted-foreground/60' style={{ animationDelay: `${delay}ms` }} />
            ))}
          </span>
          {typing} is typing…
        </div>
      )}
    </div>
  )
}

/** A role as people say it: "superadmin" → "Superadmin", "call_agent" → "Call agent". */
function roleLabel(role: string | null | undefined) {
  if (!role) return null
  const text = role.replace(/[_-]+/g, ' ')
  return text.charAt(0).toUpperCase() + text.slice(1)
}

function Bubble({
  message,
  side,
  mine,
  first,
  last,
  own,
  seen,
  onRetry,
  onDiscard,
}: {
  message: ChatMessage
  side: ChatSide
  mine: boolean
  first: boolean
  last: boolean
  own: boolean
  seen: boolean
  onRetry: (clientUuid: string) => void
  onDiscard: (clientUuid: string) => void
}) {
  const note = message.kind === 'note'
  const onBrand = mine && !note
  const time = message.created_at ? format(new Date(message.created_at), 'h:mm a') : ''
  const role = side === 'team' && message.sender_type === 'staff' ? roleLabel(message.sender?.role) : null

  // A name only where it tells you something, and once per run: a colleague
  // on the team's screen, the support agent on the client's. Never your own,
  // and not the client's on the team's side (the header says who they are).
  const writer = !own && message.sender_type === 'staff' ? (message.sender?.name ?? null) : null
  const label = note ? (writer ? `Internal note · ${writer}` : 'Internal note') : writer

  // Everything else about a message is there on hover.
  const details = [message.sender?.name, role, message.created_at ? format(new Date(message.created_at), 'd MMM yyyy, h:mm a') : null]
    .filter(Boolean)
    .join(' · ')
  const showSeen = mine && !note && seen
  const showMeta = last || showSeen || !!message.pending

  return (
    <div
      className={cn(
        'flex min-w-0 flex-col',
        // Notes belong to neither side: they sit in the middle.
        note ? 'items-center' : mine ? 'items-end' : 'items-start',
        first ? 'mt-2.5' : 'mt-0.5'
      )}
    >
      <div className={cn('flex max-w-[85%] min-w-0 flex-col sm:max-w-[70%]', note ? 'items-center' : mine ? 'items-end' : 'items-start')}>
        {first && label && (
          <span className='mb-0.5 flex items-center gap-1 px-1 text-[10px] text-muted-foreground'>
            {note && <Lock className='size-2.5' />}
            {label}
          </span>
        )}

        <div
          title={details}
          className={cn(
            'flex flex-col gap-1.5 px-2.5 py-1.5',
            note
              ? 'rounded-lg border border-dashed border-amber-300/70 bg-amber-50/60 text-amber-950 dark:border-amber-700/50 dark:bg-amber-950/20 dark:text-amber-100'
              : mine
                ? 'rounded-2xl rounded-br-sm bg-[#f26722] text-white'
                : 'rounded-2xl rounded-bl-sm bg-muted text-foreground',
            message.pending === 'sending' && 'opacity-60',
            message.pending === 'failed' && 'ring-1 ring-destructive/60'
          )}
        >
          {message.items?.length > 0 && <SharedItemCards items={message.items} side={side} onDark={onBrand} />}
          {message.attachments.length > 0 && <Attachments files={message.attachments} onDark={onBrand} />}
          {message.body && <MessageText text={message.body} onDark={onBrand} />}
        </div>

        {showMeta && (
        <div className='mt-0.5 flex items-center gap-1 px-1 text-[10px] text-muted-foreground'>
          {message.pending === 'failed' ? (
            <>
              <AlertCircle className='size-3 text-destructive' />
              <span className='text-destructive'>Not sent</span>
              <button type='button' onClick={() => onRetry(message.client_uuid!)} className='inline-flex items-center gap-0.5 font-medium hover:underline'>
                <RotateCw className='size-3' /> Retry
              </button>
              <button type='button' onClick={() => onDiscard(message.client_uuid!)} className='inline-flex items-center gap-0.5 hover:underline'>
                <X className='size-3' /> Discard
              </button>
            </>
          ) : message.pending === 'sending' ? (
            <>
              <Clock className='size-3' /> Sending…
            </>
          ) : (
            <>
              <span>{time}</span>
              {showSeen && (
                <span className='inline-flex items-center gap-0.5'>
                  <CheckCheck className='size-3' /> Seen
                </span>
              )}
            </>
          )}
        </div>
        )}
      </div>
    </div>
  )
}

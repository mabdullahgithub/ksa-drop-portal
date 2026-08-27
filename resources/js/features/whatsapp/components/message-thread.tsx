import { Fragment } from 'react'
import { format, isToday, isYesterday } from 'date-fns'
import { Loader2, MessageSquareDashed, Info } from 'lucide-react'
import { cn } from '@/lib/utils'
import { ScrollArea } from '@/components/ui/scroll-area'
import { DeliveryTicks } from './delivery-ticks'
import type { ThreadMessage } from '../types'

/**
 * Radix's ScrollArea viewport wraps children in a `display: table` div, which
 * sizes to its content — so `w-full` and `truncate` inside measure against that
 * expanded width, overflow the pane, and clip with no ellipsis. Forcing the
 * wrapper back to `block` makes children respect the container width.
 */
const VIEWPORT_BLOCK = '[&>[data-slot=scroll-area-viewport]>div]:!block'


const TEMPLATE_LABELS: Record<string, string> = {
  order_pending: 'Initial confirmation',
  followup: '24h follow-up',
}

function dayLabel(value: string) {
  const date = new Date(value)
  if (isToday(date)) return 'Today'
  if (isYesterday(date)) return 'Yesterday'
  return format(date, 'd MMMM yyyy')
}

/**
 * The conversation itself, grouped by day like any messaging client.
 *
 * Outbound sits right and tinted, inbound sits left on the card colour — the
 * arrangement every agent already reads fluently, so nothing here needs a
 * legend.
 */
export function MessageThread({
  messages,
  loading,
}: {
  messages: ThreadMessage[]
  loading: boolean
}) {
  if (loading) {
    return (
      <div className='flex flex-1 items-center justify-center'>
        <Loader2 className='h-5 w-5 animate-spin text-muted-foreground' />
      </div>
    )
  }

  if (messages.length === 0) {
    return (
      <div className='flex flex-1 flex-col items-center justify-center gap-2 text-center'>
        <MessageSquareDashed className='h-8 w-8 text-muted-foreground/50' />
        <p className='text-sm text-muted-foreground'>No messages yet</p>
      </div>
    )
  }

  const grouped = messages.reduce<Record<string, ThreadMessage[]>>((acc, message) => {
    const key = dayLabel(message.created_at)
    ;(acc[key] ??= []).push(message)
    return acc
  }, {})

  return (
    <ScrollArea className={`min-h-0 flex-1 ${VIEWPORT_BLOCK}`}>
      <div className='flex flex-col gap-4 p-4 sm:p-6'>
        {Object.entries(grouped).map(([day, dayMessages]) => (
          <Fragment key={day}>
            <div className='flex items-center justify-center'>
              <span className='rounded-full bg-muted px-3 py-1 text-[11px] font-medium text-muted-foreground'>
                {day}
              </span>
            </div>

            {dayMessages.map((message) => {
              const outbound = message.direction === 'outbound'

              return (
                <div
                  key={message.id}
                  className={cn('flex w-full', outbound ? 'justify-end' : 'justify-start')}
                >
                  <div
                    className={cn(
                      'max-w-[85%] rounded-2xl border px-3.5 py-2.5 shadow-sm sm:max-w-[70%]',
                      outbound
                        ? 'rounded-br-sm border-emerald-100 bg-emerald-50 dark:border-emerald-900/50 dark:bg-emerald-950/40'
                        : 'rounded-bl-sm bg-card'
                    )}
                  >
                    {outbound && message.template_key && (
                      <div className='mb-1 flex items-center gap-1 text-[10px] font-medium uppercase tracking-wide text-emerald-700/70 dark:text-emerald-400/70'>
                        <Info className='h-3 w-3' />
                        {TEMPLATE_LABELS[message.template_key] ?? message.template_key}
                      </div>
                    )}

                    <p className='whitespace-pre-wrap break-words text-sm leading-relaxed'>
                      {message.body || '—'}
                    </p>

                    <div
                      className={cn(
                        'mt-1 flex items-center gap-1.5',
                        outbound ? 'justify-end' : 'justify-start'
                      )}
                    >
                      <span className='text-[10px] text-muted-foreground'>
                        {format(new Date(message.created_at), 'HH:mm')}
                      </span>
                      {outbound && <DeliveryTicks message={message} />}
                    </div>

                    {message.error_message && (
                      <p className='mt-1 text-[11px] text-red-600 dark:text-red-400'>
                        {message.error_code ? `${message.error_code}: ` : ''}
                        {message.error_message}
                      </p>
                    )}
                  </div>
                </div>
              )
            })}
          </Fragment>
        ))}
      </div>
    </ScrollArea>
  )
}

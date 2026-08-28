import { Check, CheckCheck, Clock, AlertCircle } from 'lucide-react'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { format } from 'date-fns'
import type { ThreadMessage } from '../types'

/**
 * WhatsApp's own tick language, because agents already know how to read it:
 * one tick sent, two ticks delivered, two blue ticks read.
 *
 * Meta's message lifecycle is accepted → sent → delivered → read, with `failed`
 * as the only terminal failure — there is no `undelivered`, the reason lives in
 * the error code instead.
 *
 * The tooltip carries the part the ticks can't: a message with no blue ticks
 * has NOT necessarily gone unread — WhatsApp only reports a read receipt when
 * the recipient has them switched on. Stating that explicitly stops agents
 * drawing the wrong conclusion from a grey double tick.
 */
export function DeliveryTicks({
  message,
  className = '',
}: {
  message: Pick<ThreadMessage, 'status' | 'sent_at' | 'delivered_at' | 'read_at' | 'error_message'>
  className?: string
}) {
  const stamp = (value: string | null) => (value ? format(new Date(value), 'dd MMM yyyy, HH:mm') : null)

  let icon = <Clock className='h-3.5 w-3.5' />
  let tone = 'text-muted-foreground'
  let label = 'Accepted by WhatsApp — not sent yet'

  if (message.status === 'failed') {
    icon = <AlertCircle className='h-3.5 w-3.5' />
    tone = 'text-red-500'
    label = message.error_message
      ? `Failed: ${message.error_message}`
      : 'Failed — the number may not be registered on WhatsApp'
  } else if (message.read_at) {
    icon = <CheckCheck className='h-3.5 w-3.5' />
    tone = 'text-sky-500'
    label = `Read ${stamp(message.read_at)}`
  } else if (message.delivered_at) {
    icon = <CheckCheck className='h-3.5 w-3.5' />
    tone = 'text-muted-foreground'
    label = `Delivered ${stamp(message.delivered_at)} · no read receipt (the customer may have them turned off)`
  } else if (message.status === 'sent' || message.sent_at) {
    icon = <Check className='h-3.5 w-3.5' />
    tone = 'text-muted-foreground'
    label = `Sent ${stamp(message.sent_at)} · not yet delivered`
  }

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span className={`inline-flex ${tone} ${className}`}>{icon}</span>
      </TooltipTrigger>
      <TooltipContent className='max-w-64'>
        <p className='text-xs'>{label}</p>
      </TooltipContent>
    </Tooltip>
  )
}

/**
 * Compact variant for the conversation list, which only knows the newest
 * outbound message's coarse status.
 */
export function RowTicks({ status }: { status: string | null }) {
  if (!status) return null

  if (status === 'failed') {
    return <AlertCircle className='h-3.5 w-3.5 shrink-0 text-red-500' />
  }
  if (status === 'read') {
    return <CheckCheck className='h-3.5 w-3.5 shrink-0 text-sky-500' />
  }
  if (status === 'delivered') {
    return <CheckCheck className='h-3.5 w-3.5 shrink-0 text-muted-foreground' />
  }
  if (status === 'sent') {
    return <Check className='h-3.5 w-3.5 shrink-0 text-muted-foreground' />
  }
  return <Clock className='h-3.5 w-3.5 shrink-0 text-muted-foreground' />
}

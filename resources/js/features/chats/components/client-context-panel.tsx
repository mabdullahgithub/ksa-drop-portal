import { Link } from '@inertiajs/react'
import { format, formatDistanceStrict, formatDistanceToNowStrict } from 'date-fns'
import { ExternalLink } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { usePermissions } from '@/hooks/use-permissions'
import { CATEGORY_LABELS, PRIORITY_META } from '../constants'
import type { ChatMessage, ChatTeamDetail } from '../types'
import { describeEvent, StatusBadge, StatusDot } from './shared/chat-bits'
import { listTime } from './shared/conversation-list'

const LOG_LIMIT = 15

/**
 * Beside the thread: the client, the order the chat is about, the chat's own
 * details, what recently happened in it, and the client's other chats.
 */
export function ClientContextPanel({
  detail,
  messages,
  onOpen,
}: {
  detail: ChatTeamDetail
  /** The thread on screen, so actions show in the log as they happen. */
  messages: ChatMessage[]
  onOpen: (id: number) => void
}) {
  const { can } = usePermissions()
  const c = detail.conversation
  const client = detail.context.client
  const when = (value: string | null | undefined) => (value ? format(new Date(value), 'd MMM yyyy, h:mm a') : '—')
  const log = recentLog(detail, messages)

  return (
    <div className='h-full divide-y overflow-y-auto text-xs'>
      {client && (
        <Section title='Client'>
          <div className='mb-2.5'>
            <div className='flex items-center gap-2'>
              <span className='min-w-0 truncate text-sm font-semibold'>{client.company_name}</span>
              {client.short_id && (
                <span className='shrink-0 rounded border px-1 font-mono text-[10px] text-muted-foreground'>{client.short_id}</span>
              )}
            </div>
            <div className='mt-0.5 flex items-center gap-1.5 text-muted-foreground'>
              <span>{client.type_label}</span>
              <span>·</span>
              <span className={cn('size-1.5 rounded-full', client.status === 'active' ? 'bg-emerald-500' : 'bg-amber-500')} />
              <span className='capitalize'>{client.status}</span>
            </div>
          </div>

          <Fields>
            {client.contact_person && <Field label='Contact'>{client.contact_person}</Field>}
            {client.phone && (
              <Field label='Phone'>
                <a href={`tel:${client.phone}`} dir='ltr' className='hover:underline'>{client.phone}</a>
              </Field>
            )}
            {client.email && (
              <Field label='Email'>
                <a href={`mailto:${client.email}`} title={client.email} className='hover:underline'>{client.email}</a>
              </Field>
            )}
            {client.city && <Field label='City'>{client.city}</Field>}
          </Fields>

          {can('view client details') && (
            <Button variant='outline' size='sm' className='mt-3 h-7 w-full text-xs' asChild>
              <Link href={`/client/${client.id}`}>
                Open client <ExternalLink className='size-3' />
              </Link>
            </Button>
          )}
        </Section>
      )}

      {c.order && (
        <Section title='Linked order'>
          <Fields>
            <Field label='Order'>{c.order.order_number}</Field>
            <Field label='Fulfilment'>
              <span className='capitalize'>{c.order.status?.replace(/_/g, ' ') ?? '—'}</span>
            </Field>
            {c.order.total != null && (
              <Field label='Total'>
                {c.order.currency} {Number(c.order.total).toFixed(2)}
              </Field>
            )}
          </Fields>
          {can('view orders') && (
            <Button variant='outline' size='sm' className='mt-3 h-7 w-full text-xs' asChild>
              <Link href={`/orders?order=${encodeURIComponent(c.order.order_number)}`}>
                Open order <ExternalLink className='size-3' />
              </Link>
            </Button>
          )}
        </Section>
      )}

      <Section title='Chat details'>
        <Fields>
          <Field label='Reference'>
            <span className='font-mono'>{c.reference}</span>
          </Field>
          <Field label='Status'>
            <StatusBadge status={c.status} side='team' className='text-foreground' />
          </Field>
          <Field label='Priority'>
            <span className={c.priority ? PRIORITY_META[c.priority].className : ''}>{c.priority ? PRIORITY_META[c.priority].label : '—'}</span>
          </Field>
          <Field label='Category'>{CATEGORY_LABELS[c.category]}</Field>
          <Field label='Opened by'>{c.opened_by === 'client' ? 'Client' : 'Team'}</Field>
          <Field label='Opened'>{when(c.created_at)}</Field>
          {c.opened_by === 'client' && (
            <Field label='First reply'>
              {c.first_response_at && c.created_at
                ? `after ${formatDistanceStrict(new Date(c.created_at), new Date(c.first_response_at))}`
                : 'Not yet'}
            </Field>
          )}
          {!!c.reopened_count && <Field label='Reopened'>{c.reopened_count}×</Field>}
        </Fields>
      </Section>

      <Section title='Recent activity'>
        <ol>
          {log.map((entry, index) => (
            <li key={entry.key} className='relative flex gap-2.5 pb-3 last:pb-0'>
              {index < log.length - 1 && <span aria-hidden className='absolute start-[3px] top-3 bottom-0 w-px bg-border' />}
              <span aria-hidden className={cn('relative mt-[5px] size-[7px] shrink-0 rounded-full', entry.dot)} />
              <div className='min-w-0'>
                <p className='leading-snug break-words'>{entry.text}</p>
                {entry.at && (
                  <time dateTime={entry.at} title={when(entry.at)} className='text-[10px] text-muted-foreground'>
                    {formatDistanceToNowStrict(new Date(entry.at), { addSuffix: true })}
                  </time>
                )}
              </div>
            </li>
          ))}
        </ol>
      </Section>

      {detail.context.other_conversations.length > 0 && (
        <Section title='Other chats'>
          <ul className='-mx-2 space-y-px'>
            {detail.context.other_conversations.map((other) => (
              <li key={other.id}>
                <button type='button' onClick={() => onOpen(other.id)} className='flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start hover:bg-muted'>
                  <StatusDot status={other.status} className='size-1.5' />
                  <span className='min-w-0 flex-1 truncate'>{other.subject}</span>
                  <span className='shrink-0 text-[10px] text-muted-foreground'>{listTime(other.last_message_at)}</span>
                </button>
              </li>
            ))}
          </ul>
        </Section>
      )}
    </div>
  )
}

interface LogEntry {
  key: string
  text: string
  at: string | null
  dot: string
}

/** Newest first: status and detail changes and notes, ending with how the chat began. */
function recentLog(detail: ChatTeamDetail, messages: ChatMessage[]): LogEntry[] {
  const byId = new Map<number, ChatMessage>()
  for (const message of [...detail.context.activity, ...messages]) {
    if (!message.pending && message.kind !== 'message' && message.conversation_id === detail.conversation.id) {
      byId.set(message.id, message)
    }
  }

  const entries: LogEntry[] = [...byId.values()]
    .sort((a, b) => b.id - a.id)
    .slice(0, LOG_LIMIT)
    .map((message) => ({
      key: String(message.id),
      text:
        message.kind === 'note'
          ? `${message.sender?.name ?? 'Someone'} added an internal note`
          : describeEvent(message, 'team').text,
      at: message.created_at,
      dot: message.kind === 'note' ? 'bg-amber-500' : 'bg-muted-foreground/60',
    }))

  const { opened_by, created_at } = detail.conversation
  const opener = detail.context.opened_by_name
  entries.push({
    key: 'opened',
    text: `${opener ?? (opened_by === 'client' ? 'The client' : 'The team')} opened this chat`,
    at: created_at,
    dot: 'bg-primary',
  })

  return entries
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className='p-3.5'>
      <h3 className='mb-2.5 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase'>{title}</h3>
      {children}
    </section>
  )
}

function Fields({ children }: { children: React.ReactNode }) {
  return <dl className='space-y-1.5'>{children}</dl>
}

/** Label on the left, value on the right: every row lines up. */
function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className='flex items-baseline justify-between gap-3'>
      <dt className='shrink-0 text-muted-foreground'>{label}</dt>
      <dd className='min-w-0 truncate text-end font-medium'>{children}</dd>
    </div>
  )
}

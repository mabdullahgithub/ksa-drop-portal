import { Link } from '@inertiajs/react'
import { format, formatDistanceStrict } from 'date-fns'
import { Building2, ExternalLink, Mail, MapPin, Package, Phone, User } from 'lucide-react'
import { usePermissions } from '@/hooks/use-permissions'
import { CATEGORY_LABELS, PRIORITY_META } from '../constants'
import type { ChatTeamDetail } from '../types'
import { StatusDot } from './shared/chat-bits'
import { listTime } from './shared/conversation-list'

/**
 * Beside the thread: the client, the order the chat is about, the chat's
 * own timings, and the client's other conversations.
 */
export function ClientContextPanel({ detail, onOpen }: { detail: ChatTeamDetail; onOpen: (id: number) => void }) {
  const { can } = usePermissions()
  const c = detail.conversation
  const client = detail.context.client
  const when = (value: string | null | undefined) => (value ? format(new Date(value), 'd MMM yyyy, h:mm a') : '—')

  return (
    <div className='flex h-full flex-col gap-5 overflow-y-auto p-4 text-sm'>
      {client && (
        <section>
          <h3 className='mb-2 text-xs font-medium text-muted-foreground'>Client</h3>
          <div className='space-y-1.5'>
            <div className='flex items-center gap-2 font-medium'>
              <Building2 className='size-4 text-muted-foreground' />
              <span className='truncate'>{client.company_name}</span>
              {client.short_id && <span className='text-xs text-muted-foreground'>{client.short_id}</span>}
            </div>
            <div className='text-xs text-muted-foreground'>
              {client.type_label} · <span className='capitalize'>{client.status}</span>
            </div>
            {client.contact_person && (
              <Line icon={User}>{client.contact_person}</Line>
            )}
            {client.phone && (
              <Line icon={Phone}>
                <a href={`tel:${client.phone}`} dir='ltr' className='hover:underline'>{client.phone}</a>
              </Line>
            )}
            {client.email && (
              <Line icon={Mail}>
                <a href={`mailto:${client.email}`} className='truncate hover:underline'>{client.email}</a>
              </Line>
            )}
            {client.city && <Line icon={MapPin}>{client.city}</Line>}
            {can('view client details') && (
              <Link href={`/client/${client.id}`} className='inline-flex items-center gap-1 pt-1 text-xs font-medium text-primary hover:underline'>
                Open client <ExternalLink className='size-3' />
              </Link>
            )}
          </div>
        </section>
      )}

      {c.order && (
        <section>
          <h3 className='mb-2 text-xs font-medium text-muted-foreground'>Linked order</h3>
          <div className='rounded-md border p-2.5'>
            <div className='flex items-center gap-2 font-medium'>
              <Package className='size-4 text-muted-foreground' />
              {c.order.order_number}
            </div>
            <div className='mt-1 flex justify-between text-xs text-muted-foreground'>
              <span className='capitalize'>{c.order.status?.replace(/_/g, ' ') ?? '—'}</span>
              {c.order.total != null && (
                <span>
                  {c.order.currency} {Number(c.order.total).toFixed(2)}
                </span>
              )}
            </div>
            {can('view orders') && (
              <Link
                href={`/orders?search=${encodeURIComponent(c.order.order_number)}`}
                className='mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline'
              >
                Find in Orders <ExternalLink className='size-3' />
              </Link>
            )}
          </div>
        </section>
      )}

      <section>
        <h3 className='mb-2 text-xs font-medium text-muted-foreground'>This chat</h3>
        <dl className='grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 text-xs'>
          <dt className='text-muted-foreground'>Reference</dt>
          <dd>{c.reference}</dd>
          <dt className='text-muted-foreground'>Category</dt>
          <dd>{CATEGORY_LABELS[c.category]}</dd>
          <dt className='text-muted-foreground'>Priority</dt>
          <dd className={c.priority ? PRIORITY_META[c.priority].className : ''}>{c.priority ? PRIORITY_META[c.priority].label : '—'}</dd>
          <dt className='text-muted-foreground'>Opened by</dt>
          <dd>{c.opened_by === 'client' ? 'Client' : 'Team'}</dd>
          <dt className='text-muted-foreground'>Opened</dt>
          <dd>{when(c.created_at)}</dd>
          {c.opened_by === 'client' && (
            <>
              <dt className='text-muted-foreground'>First reply</dt>
              <dd>
                {c.first_response_at && c.created_at
                  ? `after ${formatDistanceStrict(new Date(c.created_at), new Date(c.first_response_at))}`
                  : 'Not yet'}
              </dd>
            </>
          )}
          {!!c.reopened_count && (
            <>
              <dt className='text-muted-foreground'>Reopened</dt>
              <dd>{c.reopened_count}×</dd>
            </>
          )}
        </dl>
      </section>

      {detail.context.other_conversations.length > 0 && (
        <section>
          <h3 className='mb-2 text-xs font-medium text-muted-foreground'>Other chats</h3>
          <ul className='space-y-1'>
            {detail.context.other_conversations.map((other) => (
              <li key={other.id}>
                <button type='button' onClick={() => onOpen(other.id)} className='flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-xs hover:bg-muted'>
                  <StatusDot status={other.status} />
                  <span className='min-w-0 flex-1 truncate'>{other.subject}</span>
                  <span className='shrink-0 text-muted-foreground'>{listTime(other.last_message_at)}</span>
                </button>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  )
}

function Line({ icon: Icon, children }: { icon: React.ElementType; children: React.ReactNode }) {
  return (
    <div className='flex min-w-0 items-center gap-2 text-xs'>
      <Icon className='size-3.5 shrink-0 text-muted-foreground' />
      <span className='min-w-0 truncate'>{children}</span>
    </div>
  )
}

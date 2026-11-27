import { useEffect, useMemo, useRef, useState } from 'react'
import axios from 'axios'
import { format } from 'date-fns'
import { toast } from 'sonner'
import { Copy, Loader2 } from 'lucide-react'
import { Popover, PopoverAnchor, PopoverContent } from '@/components/ui/popover'
import type { ChatMessage, ChatMessageOrigin } from '../../types'
import { clock } from './voice-player'

/** Where the person right-clicked. */
export interface MenuPoint {
  x: number
  y: number
}

type Row = [label: string, value: string]
type Section = { title: string; rows: Row[] }

/** Rows with nothing to say are left out. */
const rowsOf = (rows: Array<[string, string | null | undefined]>): Row[] => rows.filter((row): row is Row => !!row[1])

/** A role as people say it: "superadmin" → "Superadmin", "call_agent" → "Call agent". */
function roleLabel(role: string | null | undefined) {
  if (!role) return null
  const text = role.replace(/[_-]+/g, ' ')
  return text.charAt(0).toUpperCase() + text.slice(1)
}

function kindOf(message: ChatMessage) {
  if (message.kind === 'note') return 'Internal note'
  if (message.attachments.some((file) => file.is_voice)) return 'Voice message'
  if (!message.body && message.items?.length) return 'Shared items'
  if (!message.body && message.attachments.length) return 'Attachment'
  return 'Message'
}

function messageSection(message: ChatMessage, own: boolean, readUpTo: number, reference?: string): Section {
  const name = message.sender?.name ?? (message.sender_type === 'client' ? 'Client' : 'Support')
  const status = message.pending
    ? message.pending === 'sending' ? 'Sending' : 'Not sent'
    : message.sender_type === 'staff' && message.kind === 'message'
      ? message.id <= readUpTo ? 'Seen' : 'Sent'
      : null

  return {
    title: 'Message',
    rows: rowsOf([
      ['Sent by', own ? `${name} (you)` : name],
      ['Role', message.sender_type === 'client' ? 'Client' : roleLabel(message.sender?.role) ?? 'Team'],
      ['User ID', message.sender?.id ? String(message.sender.id) : null],
      ['Date', message.created_at ? format(new Date(message.created_at), 'd MMM yyyy, h:mm:ss a xxx') : null],
      ['Type', kindOf(message)],
      ['Status', status],
      ['Shared', (message.items ?? []).map((i) => (i.type === 'order' ? `Order ${i.title}` : `Product ${i.title}${i.code ? ` (${i.code})` : ''}`)).join(', ')],
      ['Files', message.attachments.map((file) => (file.is_voice ? `Voice, ${clock(file.duration ?? 0)}` : file.name)).join(', ')],
      ['Chat', reference],
      ['Message ID', message.pending ? null : String(message.id)],
      ['Text', message.body],
    ]),
  }
}

function originSections(origin: ChatMessageOrigin): Section[] {
  const { location, network, reported = {} } = origin
  const local = !!origin.ip && /^(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|::1$|f[cd])/i.test(origin.ip)
  const unknown = local ? 'Local network' : 'Unknown'

  return [
    {
      title: 'Location & network',
      rows: rowsOf([
        ['IP address', origin.ip],
        ['Location', location ? [location.city, location.region, location.country].filter(Boolean).join(', ') : unknown],
        ['Coordinates', location?.latitude != null ? `${location.latitude}, ${location.longitude} (approx.)` : null],
        ['ISP', network?.isp ?? unknown],
        ['ASN', network?.asn],
        ['Connection', [reported.connection_type, reported.connection, reported.downlink, reported.rtt && `RTT ${reported.rtt}`].filter(Boolean).join(' · ')],
        ['Timezone', reported.timezone],
      ]),
    },
    {
      title: 'Browser & device',
      rows: rowsOf([
        ['Device', origin.device],
        ['Browser', reported.brands],
        ['Platform', reported.platform],
        ['Model', reported.model],
        ['Screen', reported.screen],
        ['Window', reported.viewport],
        ['Language', reported.language],
        ['User agent', origin.user_agent],
      ]),
    },
  ]
}

async function copyAll(sections: Section[]) {
  const text = sections
    .map((section) => [`# ${section.title}`, ...section.rows.map(([label, value]) => `${label}: ${value}`)].join('\n'))
    .join('\n\n')
  try {
    await navigator.clipboard.writeText(text)
    toast.success('Message details copied')
  } catch {
    toast.error('Could not copy the details')
  }
}

/**
 * The team's details of one message, opened by a right-click where the
 * pointer is (`view chat message details`). Where it was sent from is only
 * fetched now: it never travels with the messages themselves.
 */
export function MessageDetails({
  at,
  message,
  own,
  readUpTo,
  reference,
  onClose,
}: {
  at: MenuPoint
  message: ChatMessage
  own: boolean
  /** The client has read up to this message id. */
  readUpTo: number
  reference?: string
  onClose: () => void
}) {
  // The popover hangs from the point itself rather than from an element.
  const anchor = useMemo(() => ({ getBoundingClientRect: () => new DOMRect(at.x, at.y, 0, 0) }), [at])
  const anchorRef = useRef(anchor)
  anchorRef.current = anchor

  // undefined: loading. null: nothing was recorded.
  const [origin, setOrigin] = useState<ChatMessageOrigin | null | undefined>(message.pending ? null : undefined)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    if (message.pending) return
    let cancelled = false
    axios
      .get(`/api/chats/${message.conversation_id}/messages/${message.id}/details`)
      .then(({ data }) => !cancelled && setOrigin(data.origin ?? null))
      .catch(() => {
        if (cancelled) return
        setFailed(true)
        setOrigin(null)
      })
    return () => {
      cancelled = true
    }
  }, [message.conversation_id, message.id, message.pending])

  // Scrolling the chat moves the message away from the box: close it.
  const box = useRef<HTMLDivElement>(null)
  useEffect(() => {
    const onScroll = (event: Event) => {
      if (!box.current?.contains(event.target as Node)) onClose()
    }
    window.addEventListener('scroll', onScroll, true)
    return () => window.removeEventListener('scroll', onScroll, true)
  }, [onClose])

  const sections = [messageSection(message, own, readUpTo, reference), ...(origin ? originSections(origin) : [])]

  return (
    <Popover open onOpenChange={(open) => !open && onClose()}>
      <PopoverAnchor virtualRef={anchorRef} />
      <PopoverContent ref={box} side='bottom' align='start' sideOffset={4} collisionPadding={8} className='w-72 p-0 text-[10px] leading-snug'>
        <div className='max-h-[min(22rem,var(--radix-popover-content-available-height))] overflow-y-auto'>
          {sections.map((section) => (
            <section key={section.title} className='border-b px-2.5 py-1.5 last:border-b-0'>
              <h4 className='mb-1 text-[9px] font-semibold tracking-wider text-muted-foreground uppercase'>{section.title}</h4>
              <dl className='space-y-0.5'>
                {section.rows.map(([label, value]) => (
                  <div key={label} className='flex gap-2'>
                    <dt className='w-16 shrink-0 text-muted-foreground'>{label}</dt>
                    <dd dir='auto' className='min-w-0 flex-1 break-words whitespace-pre-wrap select-text'>
                      {value}
                    </dd>
                  </div>
                ))}
              </dl>
            </section>
          ))}

          {origin === undefined && (
            <div className='flex items-center gap-1.5 px-2.5 py-2 text-muted-foreground'>
              <Loader2 className='size-3 animate-spin' /> Loading where it was sent from…
            </div>
          )}
          {origin === null && !message.pending && (
            <p className='px-2.5 py-2 text-muted-foreground'>
              {failed ? 'Could not load where it was sent from.' : 'Where it was sent from was not recorded for this message.'}
            </p>
          )}
        </div>

        <div className='flex items-center justify-between gap-2 border-t px-1.5 py-1'>
          <button
            type='button'
            onClick={() => {
              void copyAll(sections)
              onClose()
            }}
            disabled={origin === undefined}
            className='inline-flex items-center gap-1.5 rounded px-1.5 py-1 font-medium transition-colors hover:bg-muted disabled:opacity-50'
          >
            <Copy className='size-3' /> Copy message details
          </button>
          {/* The location database's licence asks for this credit. */}
          {origin?.location && (
            <a href='https://db-ip.com' target='_blank' rel='noopener noreferrer' className='shrink-0 text-[9px] text-muted-foreground hover:underline'>
              IP Geolocation by DB-IP
            </a>
          )}
        </div>
      </PopoverContent>
    </Popover>
  )
}

import { MessageCircle, AlertCircle, Clock, CheckCircle2, Eye, PhoneOff } from 'lucide-react'
import { StatCard, compactNumber } from '@/components/stat-card'
import type { InboxStats as InboxStatsData } from '../types'

/**
 * KPI row for the inbox — a handful of headline numbers, so stat cards rather
 * than a chart. The card itself is shared with the dashboard; see
 * `components/stat-card.tsx` for the colour reasoning.
 *
 * The dashboard renders this same row and passes `href` so each card links
 * back to the inbox; on the inbox itself there is nowhere to go, so it is
 * left off there.
 */
export function InboxStats({ stats, href }: { stats: InboxStatsData | null; href?: string }) {
  if (!stats) return null

  const awaiting = stats.sent + stats.followup_sent

  return (
    <div className='grid grid-cols-3 gap-x-2 gap-y-0.5 sm:grid-cols-6'>
      <StatCard
        icon={<MessageCircle className='h-3.5 w-3.5' />}
        label='Conversations'
        value={stats.all}
        tone='teal'
        href={href}
      />
      <StatCard
        icon={<AlertCircle className='h-3.5 w-3.5' />}
        label='Needs action'
        value={stats.needs_attention}
        tone='orange'
        href={href}
        hint='Customers who replied and are still waiting on an agent.'
      />
      <StatCard
        icon={<Clock className='h-3.5 w-3.5' />}
        label='Awaiting reply'
        value={awaiting}
        tone='blue'
        href={href}
        hint='Messaged, no response yet. The sweep follows these up at 24h and closes them at 48h.'
      />
      <StatCard
        icon={<CheckCircle2 className='h-3.5 w-3.5' />}
        label='Confirmed'
        value={stats.confirmed}
        tone='emerald'
        href={href}
        hint='Customer confirmed the order over WhatsApp.'
      />
      <StatCard
        icon={<Eye className='h-3.5 w-3.5' />}
        label='Read'
        value={stats.read}
        sub={`/ ${compactNumber(stats.delivered)}`}
        tone='violet'
        href={href}
        hint='A floor, not a true readership figure: WhatsApp only reports a read receipt when the customer has them switched on, so genuine reads are always at least this many.'
      />
      <StatCard
        icon={<PhoneOff className='h-3.5 w-3.5' />}
        label='Unreachable'
        value={stats.failed}
        tone='rose'
        href={href}
        hint='WhatsApp could not deliver — usually the number is not registered. These need a phone call instead.'
      />
    </div>
  )
}

import { CheckCircle2, Clock, Inbox, Timer } from 'lucide-react'
import { StatCard, type StatCardTone } from '@/components/stat-card'
import type { ChatStats } from '../types'

/**
 * The inbox numbers (`view chat stats`) beside the connection status: the
 * portal's stat cards, with the shipment cards' dotted texture, in mini size.
 */
export function InboxStats({ stats }: { stats: ChatStats | null }) {
  const tiles: Array<{ label: string; value: number | string | undefined; icon: React.ElementType; tone: StatCardTone }> = [
    { label: 'Open', value: stats?.open, icon: Inbox, tone: 'blue' },
    { label: 'Waiting on us', value: stats?.waiting_on_us, icon: Clock, tone: 'orange' },
    { label: 'Resolved today', value: stats?.resolved_today, icon: CheckCircle2, tone: 'emerald' },
    {
      // How long clients waited for a reply, on average, over the last 7 days.
      label: 'Avg response · 7d',
      value: stats ? (stats.avg_response_minutes === null ? '—' : formatMinutes(stats.avg_response_minutes)) : undefined,
      icon: Timer,
      tone: 'violet',
    },
  ]

  return (
    <div className='grid w-full grid-cols-2 gap-1 sm:flex sm:w-auto sm:justify-center'>
      {tiles.map(({ label, value, icon: Icon, tone }) => (
        <div key={label} className='sm:w-28'>
          <StatCard size='mini' texture='dots' tone={tone} label={label} value={value ?? '·'} icon={<Icon className='h-2.5 w-2.5' />} />
        </div>
      ))}
    </div>
  )
}

function formatMinutes(minutes: number) {
  if (minutes < 1) return '<1m'
  if (minutes < 60) return `${minutes}m`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ${minutes % 60}m`
  return `${Math.floor(hours / 24)}d ${hours % 24}h`
}

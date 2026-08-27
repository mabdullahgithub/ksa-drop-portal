import { MessageCircle, AlertCircle, Clock, CheckCircle2, Eye, PhoneOff } from 'lucide-react'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'
import type { InboxStats as InboxStatsData } from '../types'

/**
 * Solid-fill cards with an overhanging bubble badge.
 *
 * The fill is now the dominant channel, so both properties were computed, not
 * eyeballed:
 *  - White text clears WCAG AA (4.5:1) on every fill — the lighter -600 steps
 *    of orange and emerald failed at 3.6/3.8:1 and were dropped to -700.
 *  - As a set the fills pass CVD separation (worst ΔE 12.0) and the
 *    normal-vision floor (25.4), so the cards stay tellable apart.
 * Each card still carries an icon and a label, so colour is never the only cue.
 */
const TONES = {
  teal: { fill: 'bg-teal-700', badge: 'bg-teal-900' },
  orange: { fill: 'bg-orange-700', badge: 'bg-orange-900' },
  blue: { fill: 'bg-blue-600', badge: 'bg-blue-800' },
  emerald: { fill: 'bg-emerald-700', badge: 'bg-emerald-900' },
  violet: { fill: 'bg-violet-600', badge: 'bg-violet-800' },
  rose: { fill: 'bg-rose-600', badge: 'bg-rose-800' },
} as const

type Tone = keyof typeof TONES

/** 1,284 / 12.9K — keeps the value on one line at any volume. */
function compact(value: number) {
  if (value < 1000) return String(value)
  if (value < 1_000_000) return `${(value / 1000).toFixed(value < 10_000 ? 1 : 0)}K`
  return `${(value / 1_000_000).toFixed(1)}M`
}

function Card({
  icon,
  label,
  value,
  sub,
  tone,
  hint,
}: {
  icon: React.ReactNode
  label: string
  value: number
  sub?: string
  tone: Tone
  hint?: string
}) {
  const { fill, badge } = TONES[tone]

  const card = (
    // Outer wrapper stays unclipped so the badge can overhang; the inner
    // surface clips the decorative blobs.
    <div className='relative pt-2.5 pe-2'>
      <div className={cn('relative overflow-hidden rounded-xl px-3 pb-2.5 pt-3.5', fill)}>
        {/* Decorative blobs — purely atmospheric, hidden from assistive tech. */}
        <span aria-hidden className='pointer-events-none absolute -bottom-5 -start-4 h-14 w-14 rounded-full bg-black/10' />
        <span aria-hidden className='pointer-events-none absolute bottom-3.5 start-8 h-2.5 w-2.5 rounded-full bg-black/10' />
        <span aria-hidden className='pointer-events-none absolute -top-6 start-4 h-12 w-12 rounded-full bg-white/10' />

        <div className='relative'>
          {/* Proportional figures: a standalone value, not a column to align. */}
          <div className='text-xl font-bold leading-none tracking-tight text-white'>
            {compact(value)}
            {sub && <span className='ms-1 text-[11px] font-medium text-white/70'>{sub}</span>}
          </div>
          <p className='mt-1.5 truncate text-[11px] font-medium leading-snug text-white/85'>{label}</p>
        </div>
      </div>

      {/* Bubble badge, overhanging the top-start corner. */}
      <span className='absolute end-1 top-0 z-10'>
        <span
          className={cn(
            'relative grid h-7 w-7 place-items-center rounded-full text-white shadow-md ring-1 ring-background',
            badge
          )}
        >
          {icon}
          <span
            aria-hidden
            className={cn('absolute -bottom-0.5 end-1.5 h-2.5 w-2.5 rotate-45 rounded-[2px] ring-1 ring-background', badge)}
          />
        </span>
      </span>
    </div>
  )

  if (!hint) return card

  return (
    <Tooltip>
      <TooltipTrigger asChild>{card}</TooltipTrigger>
      <TooltipContent className='max-w-64'>
        <p className='text-xs'>{hint}</p>
      </TooltipContent>
    </Tooltip>
  )
}

/**
 * KPI row for the inbox — a handful of headline numbers, so stat cards rather
 * than a chart.
 */
export function InboxStats({ stats }: { stats: InboxStatsData | null }) {
  if (!stats) return null

  const awaiting = stats.sent + stats.followup_sent

  return (
    <div className='grid grid-cols-3 gap-x-2 gap-y-0.5 sm:grid-cols-6'>
      <Card
        icon={<MessageCircle className='h-3.5 w-3.5' />}
        label='Conversations'
        value={stats.all}
        tone='teal'
      />
      <Card
        icon={<AlertCircle className='h-3.5 w-3.5' />}
        label='Needs action'
        value={stats.needs_attention}
        tone='orange'
        hint='Customers who replied and are still waiting on an agent.'
      />
      <Card
        icon={<Clock className='h-3.5 w-3.5' />}
        label='Awaiting reply'
        value={awaiting}
        tone='blue'
        hint='Messaged, no response yet. The sweep follows these up at 24h and closes them at 48h.'
      />
      <Card
        icon={<CheckCircle2 className='h-3.5 w-3.5' />}
        label='Confirmed'
        value={stats.confirmed}
        tone='emerald'
        hint='Customer confirmed the order over WhatsApp.'
      />
      <Card
        icon={<Eye className='h-3.5 w-3.5' />}
        label='Read'
        value={stats.read}
        sub={`/ ${stats.delivered}`}
        tone='violet'
        hint='A floor, not a true readership figure: WhatsApp only reports a read receipt when the customer has them switched on, so genuine reads are always at least this many.'
      />
      <Card
        icon={<PhoneOff className='h-3.5 w-3.5' />}
        label='Unreachable'
        value={stats.failed}
        tone='rose'
        hint='WhatsApp could not deliver — usually the number is not registered. These need a phone call instead.'
      />
    </div>
  )
}

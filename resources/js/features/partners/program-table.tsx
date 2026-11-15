import type { ProgramSummary } from './types'
import { formatPoints } from './types'

/** The points schedule, read from live settings so the page never lies. */
export function ProgramTable({ program, compact = false }: { program: ProgramSummary; compact?: boolean }) {
  const rows: { event: string; points: string; note?: string }[] = [
    { event: 'Application approved', points: formatPoints(program.approval_bonus_points), note: 'One-time welcome bonus' },
    {
      event: 'A seller you referred ships their first delivered order',
      points: formatPoints(program.activation_points),
      note: 'Once per referred seller',
    },
    {
      event: 'Every delivered shipment of a referred seller',
      points: `${formatPoints(program.delivery_points)} × tier`,
      note: `For ${program.delivery_months} months after their first delivery`,
    },
    {
      event: `A referred seller reaches ${formatPoints(program.milestone_deliveries)} delivered shipments`,
      points: formatPoints(program.milestone_points),
      note: 'Once per referred seller',
    },
  ]

  return (
    <div className='overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800'>
      <table className='w-full text-sm'>
        <thead className='bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900/60 dark:text-slate-400'>
          <tr>
            <th className='px-4 py-3 font-medium'>What happens</th>
            <th className='px-4 py-3 text-right font-medium'>Points</th>
          </tr>
        </thead>
        <tbody className='divide-y divide-slate-200 dark:divide-slate-800'>
          {rows.map((row) => (
            <tr key={row.event}>
              <td className='px-4 py-3'>
                <div className='font-medium text-slate-900 dark:text-slate-100'>{row.event}</div>
                {!compact && row.note && <div className='text-xs text-slate-500 dark:text-slate-400'>{row.note}</div>}
              </td>
              <td className='whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-orange-600 dark:text-orange-400'>
                +{row.points}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export function TierTable({ program }: { program: ProgramSummary }) {
  return (
    <div className='grid gap-3 sm:grid-cols-3'>
      {program.tiers.map((tier, i) => (
        <div
          key={tier.name}
          className='rounded-2xl border border-slate-200 p-5 dark:border-slate-800'
        >
          <div className='text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400'>{tier.name}</div>
          <div className='mt-2 text-2xl font-semibold tabular-nums'>{tier.multiplier.toFixed(2)}×</div>
          <div className='mt-1 text-sm text-slate-600 dark:text-slate-400'>
            {i === 0 ? 'From your first active seller' : `From ${tier.min_active_clients} active sellers`}
          </div>
        </div>
      ))}
    </div>
  )
}

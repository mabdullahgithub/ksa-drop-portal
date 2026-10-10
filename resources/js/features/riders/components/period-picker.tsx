import { addDays, format } from 'date-fns'
import { DateRangeFilter } from '@/components/date-range-filter'
import { businessToday } from '@/lib/business-time'
import { cn } from '@/lib/utils'
import { MAX_DAYS } from '../data/performance'

/** Shortcuts beside the date picker. The longest the server answers is a quarter. */
const PERIODS = [
  { value: 'today', label: 'Today', days: 1 },
  { value: '7d', label: '7 days', days: 7 },
  { value: '30d', label: '30 days', days: 30 },
  { value: '90d', label: '90 days', days: 90 },
] as const

export type Period = (typeof PERIODS)[number]['value']

/** A shortcut, or any dates picked from the calendar. */
export type PeriodChoice = { period: Period | 'custom'; from?: string; to?: string }

export const DEFAULT_PERIOD: Period = '30d'

/** A period as KSA calendar days, both ends inclusive, ending today. */
function periodRange(period: Period) {
  const today = businessToday()
  const days = PERIODS.find((p) => p.value === period)!.days

  return { from: format(addDays(today, 1 - days), 'yyyy-MM-dd'), to: format(today, 'yyyy-MM-dd') }
}

/** The dates a choice stands for. Half a custom range (first click in the calendar) reads as that one day. */
export function rangeOf(choice: PeriodChoice) {
  if (choice.period !== 'custom') return periodRange(choice.period)
  const from = choice.from ?? choice.to
  const to = choice.to ?? choice.from
  return from && to ? { from, to } : periodRange(DEFAULT_PERIOD)
}

/** The dates of a rider's sheet: a shortcut, or any range picked from the calendar. */
export function PeriodPicker({ value, onChange }: { value: PeriodChoice; onChange: (choice: PeriodChoice) => void }) {
  return (
    <div className='mt-1 flex flex-wrap items-center gap-2'>
      <div className='grid min-w-64 flex-1 grid-cols-4 gap-1 rounded-lg bg-muted p-1'>
        {PERIODS.map((option) => (
          <button
            key={option.value}
            type='button'
            aria-pressed={value.period === option.value}
            onClick={() => onChange({ period: option.value })}
            className={cn(
              'h-8 rounded-md text-sm font-medium transition-colors',
              value.period === option.value ? 'bg-background shadow-xs' : 'text-muted-foreground hover:text-foreground'
            )}
          >
            {option.label}
          </button>
        ))}
      </div>
      <DateRangeFilter
        label='Custom dates'
        from={value.period === 'custom' ? value.from : undefined}
        to={value.period === 'custom' ? value.to : undefined}
        onChange={({ from, to }) => onChange(from || to ? { period: 'custom', from, to } : { period: DEFAULT_PERIOD })}
        maxDays={MAX_DAYS}
        className='h-10'
      />
    </div>
  )
}

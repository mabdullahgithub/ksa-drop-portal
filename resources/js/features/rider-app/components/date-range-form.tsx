import { useState } from 'react'
import { useI18n } from '../i18n'
import { daysBetween, MAX_DAYS, today } from '../lib/days'
import type { HistoryDates } from '../types'

type Props = {
  /** The dates on screen now, to start from. */
  initial: HistoryDates
  onApply: (dates: HistoryDates) => void
}

/**
 * From and To for the Delivered list, with the phone's own date picker.
 * Either order works: the earlier day is the start.
 */
export function DateRangeForm({ initial, onApply }: Props) {
  const { t } = useI18n()
  const [from, setFrom] = useState(initial.from)
  const [to, setTo] = useState(initial.to)

  const complete = from !== '' && to !== ''
  const [start, end] = from <= to ? [from, to] : [to, from]
  const tooLong = complete && daysBetween(start, end) > MAX_DAYS

  return (
    <div className='mx-5 mt-2 border-t border-foreground/[0.08] pt-4'>
      <p className='text-[11px] font-semibold uppercase tracking-wider text-muted-foreground'>{t('dates_title')}</p>

      <div className='mt-2 grid grid-cols-2 gap-2'>
        <DayField label={t('dates_from')} value={from} onChange={setFrom} />
        <DayField label={t('dates_to')} value={to} onChange={setTo} />
      </div>

      {tooLong && (
        <p role='alert' className='mt-2 text-[13px] font-medium text-red-600 dark:text-red-400'>
          {t('dates_too_long', { n: MAX_DAYS })}
        </p>
      )}

      <button
        type='button'
        disabled={!complete || tooLong}
        onClick={() => onApply({ from: start, to: end })}
        className='glass-tint glass-press mt-3 h-12 w-full rounded-full text-[15px] font-bold disabled:opacity-40'
      >
        {t('dates_show')}
      </button>
    </div>
  )
}

function DayField({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
  return (
    <label className='block min-w-0'>
      <span className='mb-1 block px-1 text-[13px] font-medium text-muted-foreground'>{label}</span>
      {/* iOS draws a date field centred and as wide as it likes unless told otherwise. */}
      <input
        type='date'
        value={value}
        max={today()}
        onChange={(event) => onChange(event.target.value)}
        className='block h-12 w-full min-w-0 appearance-none rounded-2xl bg-canvas px-3.5 text-start font-semibold tabular-nums outline-none focus:outline-2 focus:outline-brand dark:[color-scheme:dark] [&::-webkit-date-and-time-value]:text-start'
      />
    </label>
  )
}

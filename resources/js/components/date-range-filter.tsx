import { useState } from 'react'
import { CalendarDays, ChevronsUpDown, X } from 'lucide-react'
import { format, parse } from 'date-fns'
import type { DateRange } from 'react-day-picker'
import { Button } from '@/components/ui/button'
import { Calendar } from '@/components/ui/calendar'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { cn } from '@/lib/utils'

const VALUE_FORMAT = 'yyyy-MM-dd'

interface DateRangeFilterProps {
  /** Label shown on the trigger when no dates are chosen. */
  label?: string
  /** Start date as `yyyy-MM-dd`. */
  from?: string
  /** End date as `yyyy-MM-dd`. */
  to?: string
  onChange: (range: { from?: string; to?: string }) => void
  className?: string
}

const toDate = (value?: string) =>
  value ? parse(value, VALUE_FORMAT, new Date()) : undefined

const toValue = (date?: Date) => (date ? format(date, VALUE_FORMAT) : undefined)

/**
 * Date range (From / To) filter matching the MultiSelectFilter trigger style.
 * Either end can be left open. Values are plain `yyyy-MM-dd` strings.
 */
export function DateRangeFilter({
  label = 'Date',
  from,
  to,
  onChange,
  className,
}: DateRangeFilterProps) {
  const [open, setOpen] = useState(false)
  const range: DateRange | undefined =
    from || to ? { from: toDate(from), to: toDate(to) } : undefined
  const hasValue = Boolean(from || to)

  const handleSelect = (next: DateRange | undefined) => {
    onChange({ from: toValue(next?.from), to: toValue(next?.to) })
  }

  const clear = (e: React.MouseEvent) => {
    e.stopPropagation()
    onChange({ from: undefined, to: undefined })
  }

  const display = (value?: string) => format(toDate(value)!, 'MMM d, yyyy')
  const summary = from && to
    ? from === to ? display(from) : `${display(from)} – ${display(to)}`
    : from
      ? `From ${display(from)}`
      : to
        ? `Until ${display(to)}`
        : label

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant='outline'
          size='sm'
          className={cn(
            'h-9 shrink-0 gap-1.5 text-sm font-normal',
            hasValue && 'border-primary/50',
            className
          )}
        >
          <CalendarDays className='h-3.5 w-3.5 shrink-0' />
          <span className={cn(!hasValue && 'text-muted-foreground')}>{summary}</span>
          {hasValue ? (
            <X
              className='h-3.5 w-3.5 shrink-0 text-muted-foreground hover:text-foreground ml-0.5'
              onClick={clear}
            />
          ) : (
            <ChevronsUpDown className='h-3.5 w-3.5 shrink-0 text-muted-foreground ml-0.5' />
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent className='w-auto p-0' align='start'>
        <div className='flex items-center gap-2 border-b px-3 py-2 text-xs'>
          <div className='flex-1'>
            <div className='text-muted-foreground'>From</div>
            <div className='font-medium'>{from ? display(from) : '—'}</div>
          </div>
          <div className='flex-1'>
            <div className='text-muted-foreground'>To</div>
            <div className='font-medium'>{to ? display(to) : '—'}</div>
          </div>
        </div>
        <Calendar
          mode='range'
          defaultMonth={range?.from ?? range?.to}
          selected={range}
          onSelect={handleSelect}
          numberOfMonths={1}
          disabled={{ after: new Date() }}
        />
        {hasValue && (
          <div className='flex justify-end border-t px-3 py-2'>
            <Button variant='ghost' size='sm' className='h-7 text-xs' onClick={clear}>
              Clear dates
            </Button>
          </div>
        )}
      </PopoverContent>
    </Popover>
  )
}

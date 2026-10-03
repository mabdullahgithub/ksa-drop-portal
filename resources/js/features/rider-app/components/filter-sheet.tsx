import { Check } from 'lucide-react'
import { cn } from '@/lib/utils'
import { SheetDescription, SheetTitle } from '@/components/ui/sheet'
import { BottomSheet } from './bottom-sheet'

export type FilterOption<T extends string> = { value: T; label: string; count?: number }

type Props<T extends string> = {
  open: boolean
  onClose: () => void
  title: string
  options: FilterOption<T>[]
  value: T
  onChange: (value: T) => void
  /** More ways to filter, under the options (the Delivered list's date fields). */
  children?: React.ReactNode
}

/** The search bar's filter button: pick one option, the sheet closes. */
export function FilterSheet<T extends string>({ open, onClose, title, options, value, onChange, children }: Props<T>) {
  return (
    <BottomSheet open={open} onClose={onClose} className='max-h-[92dvh] border-0 bg-surface'>
      <div className='mx-auto mt-2.5 h-1.5 w-10 shrink-0 rounded-full bg-foreground/15' />
      <SheetTitle className='px-5 pb-2 pt-4 text-[20px] font-bold'>{title}</SheetTitle>
      <SheetDescription className='sr-only'>{title}</SheetDescription>

      {/* Scrolls on a short phone, where the options and the dates don't all fit. */}
      <div className='overflow-y-auto overscroll-contain pb-[calc(env(safe-area-inset-bottom)+16px)]'>
        <div className='px-3'>
          {options.map((option) => {
            const selected = option.value === value
            return (
              <button
                key={option.value}
                type='button'
                onClick={() => {
                  onChange(option.value)
                  onClose()
                }}
                aria-pressed={selected}
                className='flex h-14 w-full items-center gap-3 rounded-2xl px-3 text-start text-[16px] font-semibold active:bg-foreground/5'
              >
                <span className='flex-1'>{option.label}</span>
                {option.count !== undefined && <span className='font-medium tabular-nums text-muted-foreground'>{option.count}</span>}
                <span
                  className={cn(
                    'flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2',
                    selected ? 'border-brand bg-brand text-white' : 'border-foreground/20'
                  )}
                >
                  {selected && <Check className='h-4 w-4' strokeWidth={3} />}
                </span>
              </button>
            )
          })}
        </div>

        {children}
      </div>
    </BottomSheet>
  )
}

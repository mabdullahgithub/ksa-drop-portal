import { format, formatDistanceToNow, parseISO } from 'date-fns'

/**
 * "3 days ago", with the exact timestamp on hover. Items sit in the bin until
 * someone clears them, so relative age is the useful figure at a glance.
 */
export function DeletedAtCell({ value }: { value: string | null }) {
  if (!value) return <span className='text-muted-foreground'>—</span>

  const date = parseISO(value)

  return (
    <span className='text-muted-foreground whitespace-nowrap' title={format(date, 'PPpp')}>
      {formatDistanceToNow(date, { addSuffix: true })}
    </span>
  )
}
